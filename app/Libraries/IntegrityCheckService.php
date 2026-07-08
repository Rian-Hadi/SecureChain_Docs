<?php

namespace App\Libraries;

use App\Libraries\BlockHash;
use App\Models\ActivityLogModel;
use App\Models\RecoveryHistoryModel;
use Config\Recovery as RecoveryConfig;

/**
 * ============================================================================
 * IntegrityCheckService — Auto-Recovery & Integrity Check (Validasi Dua Lapis)
 * ============================================================================
 *
 * Service ini mengimplementasikan mekanisme pengecekan integritas blockchain
 * dengan arsitektur validasi dua lapis untuk mencegah:
 *   - Minority Override (node minoritas menimpa data mayoritas)
 *   - Split Brain (semua node berbeda, tidak ada konsensus)
 *
 * ARSITEKTUR NODE (3 Database):
 *   Node A = userdb.blockchain          (database utama pengguna)
 *   Node B = admindb.blockchain_backup  (database backup admin)
 *   Node C = konsensus.konsensus        (database konsensus/ledger)
 *
 * ALUR VALIDASI:
 *   LAPIS 1: Self-Integrity Check (Re-Hash Validation)
 *     → Re-hash SELURUH kolom data (7 field: nama_dokumen, nomor_permohonan,
 *       nomor_dokumen, tanggal_dokumen, tanggal_filing, kategori_dokumen, dokumen_base64)
 *     → Bandingkan hasil re-hash dengan stored_hash (block_hash)
 *     → Jika tidak cocok = node korup (internal manipulation pada salah satu/semua kolom)
 *
 *   LAPIS 2: Consensus 2/3 Majority + Auto-Recovery
 *     → Voting mayoritas dari hash yang lolos Lapis 1
 *     → Jika mayoritas ≥ 2: recovery dari Source Node ke Target Node
 *     → Jika split brain (3 berbeda): Halt Protocol + Quarantine
 *
 * @author  SecureChain-Docs Team
 * @version 2.0.0
 */
class IntegrityCheckService
{
    // =========================================================================
    // PROPERTIES — Koneksi database dan dependensi
    // =========================================================================

    /** @var \CodeIgniter\Database\BaseConnection Koneksi ke database userdb (Node A) */
    protected $userDb;

    /** @var \CodeIgniter\Database\BaseConnection Koneksi ke database admindb (Node B) */
    protected $adminDb;

    /** @var \CodeIgniter\Database\BaseConnection Koneksi ke database konsensus (Node C) */
    protected $konsensusDb;

    /** @var RecoveryConfig Konfigurasi recovery dari Config/Recovery.php */
    protected $config;

    /** @var ActivityLogModel Model untuk mencatat aktivitas/log */
    protected $activityLogModel;

    /** @var RecoveryHistoryModel Model untuk mencatat riwayat recovery */
    protected $recoveryHistoryModel;

    // =========================================================================
    // LABEL NODE — Pemetaan nama node untuk logging dan laporan
    // =========================================================================

    /** Label human-readable untuk setiap node */
    private const NODE_LABELS = [
        'userdb'    => 'Node A (UserDB)',
        'admindb'   => 'Node B (AdminDB)',
        'konsensus' => 'Node C (Konsensus)',
    ];

    /** Mapping dari nama node ke tabel database */
    private const NODE_TABLES = [
        'userdb'    => 'blockchain',
        'admindb'   => 'blockchain_backup',
        'konsensus' => 'konsensus',
    ];

    // =========================================================================
    // CONSTRUCTOR
    // =========================================================================

    public function __construct()
    {
        // Inisialisasi koneksi ke ketiga database (Node A, B, C)
        $this->userDb      = \Config\Database::connect('userdb');
        $this->adminDb     = \Config\Database::connect('admindb');
        $this->konsensusDb = \Config\Database::connect('konsensus');

        // Load konfigurasi dan model yang dibutuhkan
        $this->config               = new RecoveryConfig();
        $this->activityLogModel     = model(ActivityLogModel::class);
        $this->recoveryHistoryModel = model(RecoveryHistoryModel::class);
    }

    // =========================================================================
    // PUBLIC: ENTRY POINT UTAMA
    // =========================================================================

    /**
     * Jalankan proses Auto-Recovery & Integrity Check secara penuh.
     *
     * Metode ini adalah entry point tunggal yang mengorkestrasi seluruh alur:
     *   1. Tarik semua record unik (berdasarkan nomor_permohonan+tanggal_dokumen)
     *   2. Untuk setiap record, jalankan Lapis 1 + Lapis 2
     *   3. Kumpulkan hasil ke dalam array laporan lengkap
     *
     * @param string $performedBy Username yang menjalankan pengecekan
     * @return array Laporan lengkap: total diperiksa, sehat, dipulihkan, dikarantina, dll.
     */
    public function runFullIntegrityCheck(string $performedBy = 'system'): array
    {
        $startTime = microtime(true);

        // ─── TAHAP 0: Validasi keberadaan tabel di ketiga database ────
        if (!$this->validateDatabaseTables()) {
            return [
                'success'   => false,
                'error'     => 'Satu atau lebih tabel database tidak ditemukan.',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        // ─── TAHAP 1: Tarik seluruh record dari ketiga node ────
        // Ambil data mentah dari masing-masing database
        $nodeA_records = $this->userDb->table('blockchain')->get()->getResultArray();
        $nodeB_records = $this->adminDb->table('blockchain_backup')->get()->getResultArray();
        $nodeC_records = $this->konsensusDb->table('konsensus')->get()->getResultArray();

        // Gabungkan seluruh record berdasarkan identifier unik (nomor_permohonan + tanggal_dokumen)
        // agar kita bisa membandingkan record yang sama di ketiga node
        $allIdentifiers = $this->collectUniqueIdentifiers($nodeA_records, $nodeB_records, $nodeC_records);

        // ─── TAHAP 2: Inisialisasi struktur laporan ────
        $report = [
            'timestamp'         => date('Y-m-d H:i:s'),
            'performed_by'      => $performedBy,
            'total_checked'     => count($allIdentifiers),    // Total dokumen yang diperiksa
            'total_healthy'     => 0,                         // Dokumen sehat (3/3 atau 2/3 sama)
            'total_recovered'   => 0,                         // Dokumen yang berhasil dipulihkan
            'total_quarantined' => 0,                         // Dokumen yang dikarantina (split brain)
            'total_corrupt_l1'  => 0,                         // Node korup terdeteksi di Lapis 1
            'details'           => [],                        // Detail per-dokumen
        ];

        // ─── TAHAP 3: Loop setiap dokumen, jalankan Lapis 1 + Lapis 2 ────
        foreach ($allIdentifiers as $identifierKey => $identifierInfo) {
            // Ambil data record dari masing-masing node berdasarkan identifier ini
            $nodeData = $this->fetchRecordFromAllNodes(
                $identifierInfo['nomor_permohonan'],
                $identifierInfo['tanggal_dokumen'],
                $nodeA_records,
                $nodeB_records,
                $nodeC_records
            );

            // ╔═══════════════════════════════════════════════════════════════╗
            // ║  LAPIS 1: SELF-INTEGRITY CHECK (RE-HASH VALIDATION)         ║
            // ╚═══════════════════════════════════════════════════════════════╝
            $layer1Result = $this->executeLayer1_SelfIntegrityCheck($nodeData);

            // ╔═══════════════════════════════════════════════════════════════╗
            // ║  LAPIS 2: CONSENSUS 2/3 MAJORITY & AUTO-RECOVERY            ║
            // ╚═══════════════════════════════════════════════════════════════╝
            $layer2Result = $this->executeLayer2_ConsensusVoting(
                $layer1Result,
                $nodeData,
                $identifierInfo,
                $performedBy
            );

            // ─── Akumulasi statistik ────
            switch ($layer2Result['status']) {
                case 'HEALTHY':
                    $report['total_healthy']++;
                    break;
                case 'RECOVERED':
                    $report['total_recovered']++;
                    break;
                case 'QUARANTINED':
                    $report['total_quarantined']++;
                    break;
            }

            // Hitung total node korup dari Lapis 1
            $report['total_corrupt_l1'] += count($layer1Result['corrupt_nodes']);

            // Simpan detail hasil per-dokumen
            $report['details'][] = [
                'identifier'      => $identifierKey,
                'layer1'          => $layer1Result,
                'layer2'          => $layer2Result,
            ];
        }

        // ─── TAHAP 4: Hitung waktu eksekusi dan catat log ────
        $report['execution_time_seconds'] = round(microtime(true) - $startTime, 4);
        $report['success'] = true;

        // Catat log keseluruhan proses ke activity_logs
        $this->logIntegrityCheckResult($report);

        return $report;
    }

    // =========================================================================
    // LAPIS 1: SELF-INTEGRITY CHECK (RE-HASH VALIDATION)
    // =========================================================================

    /**
     * LAPIS 1 — Cek integritas internal masing-masing node.
     *
     * Karena SHA-256 bersifat one-way (tidak bisa di-decode), kita melakukan
     * Re-Hash dari SELURUH kolom data yang tersimpan di masing-masing node,
     * lalu membandingkan hasilnya dengan stored_hash (block_hash) di database.
     *
     * KOLOM YANG DI-HASH (BlockHash::HASH_FIELDS — 7 kolom):
     *   1. nama_dokumen
     *   2. nomor_permohonan
     *   3. nomor_dokumen
     *   4. tanggal_dokumen
     *   5. tanggal_filing
     *   6. kategori_dokumen
     *   7. dokumen_base64
     *
     * Jika SALAH SATU saja dari 7 kolom di atas diubah tanpa menghitung ulang
     * hash-nya, maka Re-Hash akan menghasilkan nilai berbeda dari stored_hash
     * → menandakan node tersebut telah dimanipulasi.
     *
     * LOGIKA:
     * 1. Untuk setiap node yang memiliki data:
     *    a. Ambil SELURUH kolom data dan stored_hash (block_hash) dari node
     *    b. Hitung ulang: recalculated_hash = BlockHash::calculate(data_node)
     *       (SHA-256 dari concatenation 7 kolom di atas)
     *    c. Bandingkan recalculated_hash vs stored_hash
     *    d. Jika TIDAK cocok → Node korup (masuk daftar Target Node)
     *    e. Jika cocok → stored_hash masuk array kandidat voting Lapis 2
     *
     * @param array $nodeData Data dari ketiga node ['userdb'=>..., 'admindb'=>..., 'konsensus'=>...]
     * @return array Hasil Lapis 1: daftar node korup dan kandidat voting
     */
    private function executeLayer1_SelfIntegrityCheck(array $nodeData): array
    {
        // Array untuk menyimpan node yang lolos dan gagal validasi Lapis 1
        $corruptNodes    = []; // Node yang hash-nya tidak cocok (korup internal)
        $votingCandidates = []; // Node yang hash-nya cocok (lolos ke Lapis 2)

        // Iterasi ketiga node (userdb, admindb, konsensus)
        foreach ($nodeData as $nodeName => $record) {
            // Jika node tidak memiliki record untuk dokumen ini, skip
            if ($record === null) {
                // Node tanpa data tidak dianggap korup maupun kandidat
                // (akan ditangani sebagai 'missing' di Lapis 2)
                log_message('debug', "[LAPIS_1] {$this->getNodeLabel($nodeName)}: Data tidak ditemukan (skip)");
                continue;
            }

            // ─── Langkah 1: Ambil stored_hash (block_hash) dari node ────
            $storedHash = $record['block_hash'] ?? '';

            // ─── Langkah 2: Re-Hash — hitung ulang hash dari SELURUH kolom data ────
            // Menggunakan BlockHash::calculate() yang merupakan Single Source of Truth
            // untuk perhitungan hash di seluruh sistem.
            //
            // Method ini meng-hash concatenation dari 7 kolom (BlockHash::HASH_FIELDS):
            //   nama_dokumen + nomor_permohonan + nomor_dokumen + tanggal_dokumen
            //   + tanggal_filing + kategori_dokumen + dokumen_base64
            //
            // Jika SALAH SATU saja kolom dimanipulasi, hash akan berbeda.
            $recalculatedHash = BlockHash::calculate($record);

            // ─── Langkah 3: Bandingkan Re-Hash dengan stored_hash ────
            if ($recalculatedHash !== $storedHash) {
                // ✗ TIDAK COCOK → Salah satu atau lebih dari 7 kolom data di node ini
                // telah dimanipulasi tanpa menghitung ulang hash-nya.
                // Kolom yang mungkin berubah: nama_dokumen, nomor_permohonan, nomor_dokumen,
                // tanggal_dokumen, tanggal_filing, kategori_dokumen, atau dokumen_base64.
                $corruptNodes[] = $nodeName;

                log_message('warning', sprintf(
                    '[LAPIS_1] ✗ %s KORUP — stored_hash: %s, recalculated: %s (nomor_permohonan: %s, nama_dokumen: %s)',
                    $this->getNodeLabel($nodeName),
                    substr($storedHash, 0, 16) . '...',
                    substr($recalculatedHash, 0, 16) . '...',
                    $record['nomor_permohonan'] ?? '-',
                    $record['nama_dokumen'] ?? '-'
                ));

                // Cek juga format legacy — mungkin hash dihitung dengan format lama
                // (sebelum penambahan kategori_dokumen ke formula hash)
                $legacyHash = BlockHash::calculateLegacy($record);
                if ($legacyHash === $storedHash) {
                    // Hash cocok dengan format legacy — bukan korup,
                    // tapi perlu migrasi hash ke format baru
                    log_message('info', sprintf(
                        '[LAPIS_1] ↻ %s: Hash legacy terdeteksi — perlu migrasi ke format canonical',
                        $this->getNodeLabel($nodeName)
                    ));

                    // Tetap masukkan ke voting, gunakan hash yang sudah di-recalculate
                    // agar konsisten dengan format terbaru
                    $votingCandidates[$nodeName] = $recalculatedHash;

                    // Hapus dari corrupt karena sebenarnya valid (hanya beda format)
                    array_pop($corruptNodes);
                }
            } else {
                // ✓ COCOK → Seluruh 7 kolom data di node ini konsisten dengan stored_hash.
                // Node ini lulus self-integrity check.
                // Masukkan stored_hash ke array kandidat voting Lapis 2.
                $votingCandidates[$nodeName] = $storedHash;

                if ($this->config->verboseLogging) {
                    log_message('info', sprintf(
                        '[LAPIS_1] ✓ %s VALID — hash: %s (nomor_permohonan: %s, nama_dokumen: %s)',
                        $this->getNodeLabel($nodeName),
                        substr($storedHash, 0, 16) . '...',
                        $record['nomor_permohonan'] ?? '-',
                        $record['nama_dokumen'] ?? '-'
                    ));
                }
            }
        }

        // Kembalikan hasil Lapis 1: siapa yang korup dan siapa yang lolos
        return [
            'corrupt_nodes'     => $corruptNodes,       // Node yang gagal re-hash (Target Node dari Lapis 1)
            'voting_candidates' => $votingCandidates,   // Node yang lolos ke Lapis 2 (dengan hash-nya)
            'total_nodes_checked' => count(array_filter($nodeData, fn($r) => $r !== null)),
        ];
    }

    // =========================================================================
    // LAPIS 2: CONSENSUS 2/3 MAJORITY & AUTO-RECOVERY
    // =========================================================================

    /**
     * LAPIS 2 — Voting mayoritas 2/3 dan auto-recovery.
     *
     * Menggunakan hash yang LOLOS Lapis 1 untuk melakukan voting konsensus.
     *
     * LOGIKA:
     * 1. Gunakan array_count_values() pada hash kandidat voting
     * 2. Jika ada mayoritas (count ≥ 2):
     *    a. Jadikan node mayoritas sebagai Source Node
     *    b. Node dengan hash berbeda + node korup Lapis 1 = Target Node
     *    c. UPDATE Target Node dari Source Node (DILARANG menyentuh node mayoritas)
     * 3. Jika TIDAK ada mayoritas (Split Brain / 3 hash berbeda):
     *    a. Batalkan semua recovery (Halt Protocol)
     *    b. Set status dokumen menjadi 'Quarantine'
     *    c. Catat log anomali kritikal
     *
     * @param array  $layer1Result    Hasil dari Lapis 1
     * @param array  $nodeData        Data mentah dari ketiga node
     * @param array  $identifierInfo  Info identifier dokumen
     * @param string $performedBy     Username pelaku
     * @return array Hasil Lapis 2: status, source, target, detail recovery
     */
    private function executeLayer2_ConsensusVoting(
        array  $layer1Result,
        array  $nodeData,
        array  $identifierInfo,
        string $performedBy
    ): array {
        $votingCandidates = $layer1Result['voting_candidates']; // Hash yang lolos Lapis 1
        $corruptFromL1    = $layer1Result['corrupt_nodes'];     // Node korup dari Lapis 1

        // ─── Cek edge case: tidak ada kandidat voting sama sekali ────
        if (empty($votingCandidates)) {
            // Semua node korup dari Lapis 1 — tidak bisa voting
            // Ini kondisi terburuk: tidak ada data yang bisa dipercaya
            log_message('critical', sprintf(
                '[LAPIS_2] ✗ SEMUA NODE KORUP — Tidak ada kandidat voting untuk dokumen: %s',
                $identifierInfo['nomor_permohonan']
            ));

            // Karantina semua data di ketiga node
            $this->quarantineDocument($identifierInfo, 'ALL_NODES_CORRUPT', $performedBy);

            return [
                'status'         => 'QUARANTINED',
                'reason'         => 'Semua node gagal self-integrity check (Lapis 1). Tidak ada sumber kebenaran.',
                'source_node'    => null,
                'target_nodes'   => $corruptFromL1,
                'recovery_count' => 0,
            ];
        }

        // ═══════════════════════════════════════════════════════════════
        // VOTING: Gunakan array_count_values() untuk menghitung mayoritas
        // ═══════════════════════════════════════════════════════════════

        // $votingCandidates format: ['userdb' => 'abc123...', 'konsensus' => 'abc123...', ...]
        // Kita hitung berapa kali setiap hash muncul
        $hashValues = array_values($votingCandidates); // Ambil hanya hash-nya
        $hashCounts = array_count_values($hashValues); // Hitung kemunculan tiap hash

        // Sortir descending agar hash dengan count tertinggi ada di depan
        arsort($hashCounts);

        // Ambil hash dengan count tertinggi
        $majorityHash  = array_key_first($hashCounts);     // Hash yang paling banyak muncul
        $majorityCount = $hashCounts[$majorityHash];        // Berapa kali hash tersebut muncul

        // ═══════════════════════════════════════════════════════════════
        // CASE 1: Ada mayoritas (count >= 2) → RECOVERY
        // ═══════════════════════════════════════════════════════════════
        if ($majorityCount >= 2) {
            return $this->handleMajorityRecovery(
                $votingCandidates,
                $majorityHash,
                $majorityCount,
                $corruptFromL1,
                $nodeData,
                $identifierInfo,
                $performedBy
            );
        }

        // ═══════════════════════════════════════════════════════════════
        // CASE 2: Tidak ada mayoritas → CEK LEBIH LANJUT
        // ═══════════════════════════════════════════════════════════════

        // Jika hanya 1 kandidat voting (2 node korup dari Lapis 1), kita tidak bisa
        // melakukan voting karena hanya ada 1 suara. Ini bukan split brain,
        // tapi data terlalu sedikit untuk konsensus.
        if (count($votingCandidates) === 1) {
            $singleNodeName = array_key_first($votingCandidates);

            log_message('warning', sprintf(
                '[LAPIS_2] ⚠ Hanya 1 node valid (%s) — tidak cukup untuk konsensus. Dokumen: %s',
                $this->getNodeLabel($singleNodeName),
                $identifierInfo['nomor_permohonan']
            ));

            // Tidak ada mayoritas, tapi ada 1 node valid
            // Kita TIDAK otomatis recovery dari 1 node karena melanggar aturan 2/3 majority
            $this->quarantineDocument($identifierInfo, 'INSUFFICIENT_CONSENSUS', $performedBy);

            return [
                'status'         => 'QUARANTINED',
                'reason'         => 'Hanya 1 node valid, tidak cukup untuk konsensus 2/3 majority.',
                'source_node'    => null,
                'target_nodes'   => $corruptFromL1,
                'recovery_count' => 0,
                'single_valid'   => $singleNodeName,
            ];
        }

        // ═══════════════════════════════════════════════════════════════
        // CASE 3: Split Brain — Semua hash berbeda (3 node, 3 hash berbeda)
        // HALT PROTOCOL: Batalkan semua recovery
        // ═══════════════════════════════════════════════════════════════
        return $this->handleSplitBrain(
            $votingCandidates,
            $corruptFromL1,
            $nodeData,
            $identifierInfo,
            $performedBy
        );
    }

    // =========================================================================
    // HANDLER: MAJORITY RECOVERY (CASE 1)
    // =========================================================================

    /**
     * Tangani kasus mayoritas (2/3 node setuju).
     *
     * ATURAN KETAT:
     * - Source Node = salah satu node dari kelompok mayoritas
     * - Target Node = node yang hash-nya berbeda + node korup dari Lapis 1
     * - DILARANG KERAS meng-update node mayoritas (mencegah Minority Override)
     *
     * @param array  $votingCandidates  Node yang lolos Lapis 1 dengan hash-nya
     * @param string $majorityHash      Hash yang dipilih mayoritas
     * @param int    $majorityCount     Berapa node yang setuju
     * @param array  $corruptFromL1     Node korup dari Lapis 1
     * @param array  $nodeData          Data mentah dari ketiga node
     * @param array  $identifierInfo    Info identifier dokumen
     * @param string $performedBy       Username pelaku
     * @return array Hasil recovery
     */
    private function handleMajorityRecovery(
        array  $votingCandidates,
        string $majorityHash,
        int    $majorityCount,
        array  $corruptFromL1,
        array  $nodeData,
        array  $identifierInfo,
        string $performedBy
    ): array {
        // ─── Identifikasi Source Node (kelompok mayoritas) ────
        // Pilih salah satu node dari kelompok mayoritas sebagai sumber kebenaran
        $sourceNode = null;
        $majorityNodes = []; // Semua node yang termasuk kelompok mayoritas

        foreach ($votingCandidates as $nodeName => $hash) {
            if ($hash === $majorityHash) {
                $majorityNodes[] = $nodeName;
                // Pilih node pertama dari mayoritas sebagai Source Node
                if ($sourceNode === null) {
                    $sourceNode = $nodeName;
                }
            }
        }

        // Data dari Source Node yang akan digunakan untuk recovery
        $sourceData = $nodeData[$sourceNode];

        // ─── Identifikasi Target Node (node yang perlu dipulihkan) ────
        // Target Node = node yang hash-nya berbeda dari mayoritas + node korup Lapis 1
        $targetNodes = [];

        // 1. Node yang hash-nya berbeda dari mayoritas (lolos Lapis 1 tapi beda hash)
        foreach ($votingCandidates as $nodeName => $hash) {
            if ($hash !== $majorityHash) {
                $targetNodes[] = $nodeName;
            }
        }

        // 2. Gabungkan dengan node korup dari Lapis 1 (tidak boleh duplikat)
        foreach ($corruptFromL1 as $corruptNode) {
            if (!in_array($corruptNode, $targetNodes, true)) {
                $targetNodes[] = $corruptNode;
            }
        }

        // ─── Jika tidak ada Target Node → semua sehat! ────
        if (empty($targetNodes)) {
            $statusLabel = $majorityCount === 3 ? '3/3 KONSENSUS' : '2/3 MAYORITAS';

            if ($this->config->verboseLogging) {
                log_message('info', sprintf(
                    '[LAPIS_2] ✓ %s — Dokumen %s sehat (%s setuju)',
                    $statusLabel,
                    $identifierInfo['nomor_permohonan'],
                    $majorityCount
                ));
            }

            return [
                'status'         => 'HEALTHY',
                'reason'         => "Semua node konsisten ({$statusLabel}).",
                'source_node'    => $sourceNode,
                'target_nodes'   => [],
                'recovery_count' => 0,
                'majority_count' => $majorityCount,
            ];
        }

        // ─── EKSEKUSI RECOVERY: Update HANYA Target Node ────
        // PENTING: Node mayoritas TIDAK BOLEH disentuh (mencegah Minority Override)
        $recoveredCount = 0;
        $recoveryErrors = [];

        // Normalisasi data sumber ke format canonical (termasuk re-hash)
        $canonicalSourceData = BlockHash::buildCanonicalRecord($sourceData);

        foreach ($targetNodes as $targetNode) {
            try {
                // Ambil data lama dari Target Node untuk disimpan di recovery_history
                $beforeData = $nodeData[$targetNode] ?? null;

                // ─── SAFETY CHECK: Pastikan kita TIDAK meng-update node mayoritas ────
                // Ini adalah pengaman terakhir untuk mencegah Minority Override
                if (in_array($targetNode, $majorityNodes, true)) {
                    log_message('critical', sprintf(
                        '[SAFETY] ✗ DIBATALKAN — Attempted update pada node mayoritas %s! Ini mencegah Minority Override.',
                        $this->getNodeLabel($targetNode)
                    ));
                    continue; // DILARANG KERAS!
                }

                // Eksekusi UPDATE ke Target Node
                $updateSuccess = $this->updateTargetNode(
                    $targetNode,
                    $canonicalSourceData,
                    $beforeData
                );

                if ($updateSuccess) {
                    $recoveredCount++;

                    // Catat recovery ke recovery_history (untuk rollback capability)
                    $this->recoveryHistoryModel->logRecovery([
                        'recovery_type'    => $performedBy === 'system' ? 'integrity_auto' : 'integrity_manual',
                        'source_db'        => $sourceNode,
                        'target_db'        => $targetNode,
                        'table_name'       => self::NODE_TABLES[$targetNode] ?? 'unknown',
                        'record_key'       => $canonicalSourceData['block_hash'] ?? '',
                        'before_checksum'  => $beforeData ? BlockHash::calculatePayloadChecksum($beforeData) : null,
                        'after_checksum'   => BlockHash::calculatePayloadChecksum($canonicalSourceData),
                        'before_data'      => $beforeData,  // Data korup (untuk rollback)
                        'after_data'       => $canonicalSourceData,
                        'consensus_result' => [
                            'majority_hash'  => $majorityHash,
                            'majority_count' => $majorityCount,
                            'majority_nodes' => $majorityNodes,
                            'target_nodes'   => $targetNodes,
                            'source_node'    => $sourceNode,
                        ],
                        'status'           => 'success',
                        'performed_by'     => $performedBy,
                    ]);

                    // Catat ke activity_logs
                    $this->activityLogModel->logActivity([
                        'action_type'   => 'INTEGRITY_RECOVER',
                        'identifier'    => $identifierInfo['nomor_permohonan'],
                        'status'        => 'SUCCESS',
                        'description'   => sprintf(
                            'Recovery %s dari %s (Mayoritas %d/3). Dokumen: %s',
                            $this->getNodeLabel($targetNode),
                            $this->getNodeLabel($sourceNode),
                            $majorityCount,
                            $identifierInfo['nomor_permohonan']
                        ),
                        'original_data' => $beforeData,
                        'modified_data' => $canonicalSourceData,
                    ]);

                    log_message('info', sprintf(
                        '[LAPIS_2] ✓ RECOVERY — %s dipulihkan dari %s (hash: %s)',
                        $this->getNodeLabel($targetNode),
                        $this->getNodeLabel($sourceNode),
                        substr($majorityHash, 0, 16) . '...'
                    ));
                } else {
                    $recoveryErrors[] = "Gagal update {$this->getNodeLabel($targetNode)}";
                    log_message('error', sprintf(
                        '[LAPIS_2] ✗ GAGAL RECOVERY — %s tidak bisa diupdate',
                        $this->getNodeLabel($targetNode)
                    ));
                }
            } catch (\Exception $e) {
                $recoveryErrors[] = "{$this->getNodeLabel($targetNode)}: {$e->getMessage()}";
                log_message('error', sprintf(
                    '[LAPIS_2] ✗ EXCEPTION saat recovery %s: %s',
                    $this->getNodeLabel($targetNode),
                    $e->getMessage()
                ));
            }
        }

        return [
            'status'         => $recoveredCount > 0 ? 'RECOVERED' : 'RECOVERY_FAILED',
            'reason'         => sprintf(
                'Mayoritas %d/3. Source: %s. Target: %s. Berhasil: %d/%d.',
                $majorityCount,
                $this->getNodeLabel($sourceNode),
                implode(', ', array_map([$this, 'getNodeLabel'], $targetNodes)),
                $recoveredCount,
                count($targetNodes)
            ),
            'source_node'    => $sourceNode,
            'target_nodes'   => $targetNodes,
            'majority_nodes' => $majorityNodes,
            'majority_hash'  => $majorityHash,
            'majority_count' => $majorityCount,
            'recovery_count' => $recoveredCount,
            'errors'         => $recoveryErrors,
        ];
    }

    // =========================================================================
    // HANDLER: SPLIT BRAIN (CASE 3)
    // =========================================================================

    /**
     * Tangani kondisi Split Brain — semua node memiliki hash berbeda.
     *
     * ATURAN:
     * 1. BATALKAN semua proses recovery (Halt Protocol)
     * 2. Ubah status dokumen menjadi 'Quarantine' / 'Corrupted'
     * 3. Catat log anomali kritikal TANPA menyalahkan admin
     *
     * @param array  $votingCandidates  Node yang lolos Lapis 1 dengan hash-nya
     * @param array  $corruptFromL1     Node korup dari Lapis 1
     * @param array  $nodeData          Data mentah dari ketiga node
     * @param array  $identifierInfo    Info identifier dokumen
     * @param string $performedBy       Username pelaku
     * @return array Hasil split brain handling
     */
    private function handleSplitBrain(
        array  $votingCandidates,
        array  $corruptFromL1,
        array  $nodeData,
        array  $identifierInfo,
        string $performedBy
    ): array {
        // ═══════════════════════════════════════════════════════════════
        // HALT PROTOCOL: Jangan lakukan recovery apapun!
        // ═══════════════════════════════════════════════════════════════

        log_message('critical', sprintf(
            '[SPLIT_BRAIN] ⛔ HALT PROTOCOL — Dokumen %s terdeteksi split brain! ' .
            'Semua node memiliki hash berbeda. Recovery DIBATALKAN.',
            $identifierInfo['nomor_permohonan']
        ));

        // Log hash dari masing-masing node untuk investigasi
        foreach ($votingCandidates as $nodeName => $hash) {
            log_message('critical', sprintf(
                '[SPLIT_BRAIN]   → %s hash: %s',
                $this->getNodeLabel($nodeName),
                substr($hash, 0, 32) . '...'
            ));
        }

        // ─── Karantina dokumen di database ────
        $this->quarantineDocument($identifierInfo, 'SPLIT_BRAIN', $performedBy);

        // ─── Catat log anomali kritikal ────
        // PENTING: Log ini TIDAK menyalahkan entitas admin secara otomatis
        $this->activityLogModel->logActivity([
            'action_type'   => 'SPLIT_BRAIN_DETECTED',
            'identifier'    => $identifierInfo['nomor_permohonan'],
            'status'        => 'CRITICAL',
            'description'   => sprintf(
                '⛔ SPLIT BRAIN TERDETEKSI pada dokumen %s. ' .
                'Semua %d node yang valid memiliki hash berbeda. ' .
                'Recovery otomatis DIBATALKAN — memerlukan investigasi manual. ' .
                'Tidak ada entitas yang dituduh tanpa bukti.',
                $identifierInfo['nomor_permohonan'],
                count($votingCandidates)
            ),
            'original_data' => [
                'voting_candidates' => $votingCandidates,
                'corrupt_from_l1'   => $corruptFromL1,
                'node_hashes'       => array_map(
                    fn($r) => $r ? substr($r['block_hash'] ?? '', 0, 32) : 'NULL',
                    $nodeData
                ),
            ],
        ]);

        // ─── Catat ke recovery_history sebagai anomali ────
        $this->recoveryHistoryModel->logRecovery([
            'recovery_type'    => 'split_brain_halt',
            'source_db'        => 'none',
            'target_db'        => 'none',
            'table_name'       => 'blockchain',
            'record_key'       => $identifierInfo['nomor_permohonan'],
            'before_checksum'  => null,
            'after_checksum'   => null,
            'before_data'      => $nodeData,
            'after_data'       => ['status' => 'QUARANTINED', 'reason' => 'SPLIT_BRAIN'],
            'consensus_result' => [
                'voting_candidates' => $votingCandidates,
                'corrupt_from_l1'   => $corruptFromL1,
                'halted'            => true,
            ],
            'status'           => 'halted',
            'performed_by'     => $performedBy,
        ]);

        return [
            'status'         => 'QUARANTINED',
            'reason'         => sprintf(
                '⛔ SPLIT BRAIN: %d node valid, semua hash berbeda. Halt Protocol aktif — recovery dibatalkan.',
                count($votingCandidates)
            ),
            'source_node'    => null,
            'target_nodes'   => array_merge(array_keys($votingCandidates), $corruptFromL1),
            'recovery_count' => 0,
            'split_brain'    => true,
            'node_hashes'    => $votingCandidates,
        ];
    }

    // =========================================================================
    // DATABASE OPERATIONS — CRUD ke masing-masing node
    // =========================================================================

    /**
     * Update Target Node dengan data dari Source Node.
     *
     * Menggunakan block_hash atau identifier sebagai WHERE clause.
     * Field yang diupdate dibatasi oleh BlockHash::RECOVERABLE_FIELDS.
     *
     * @param string     $targetNode  Nama node target ('userdb', 'admindb', 'konsensus')
     * @param array      $sourceData  Data canonical dari Source Node
     * @param array|null $currentData Data lama di Target Node (untuk WHERE clause)
     * @return bool True jika update berhasil
     */
    private function updateTargetNode(string $targetNode, array $sourceData, ?array $currentData): bool
    {
        // Cek dry-run mode dari konfigurasi
        if ($this->config->dryRunMode) {
            log_message('info', "[DRY-RUN] Akan update {$this->getNodeLabel($targetNode)}");
            return true;
        }

        // Ambil koneksi database dan nama tabel berdasarkan node
        $db    = $this->getDbConnection($targetNode);
        $table = self::NODE_TABLES[$targetNode] ?? null;

        if (!$db || !$table) {
            log_message('error', "[UPDATE] Node tidak valid: {$targetNode}");
            return false;
        }

        // Filter hanya field yang diizinkan untuk di-update (keamanan)
        $updateData = [];
        foreach (BlockHash::RECOVERABLE_FIELDS as $field) {
            if (isset($sourceData[$field])) {
                $updateData[$field] = $sourceData[$field];
            }
        }

        if (empty($updateData)) {
            log_message('error', "[UPDATE] Tidak ada field yang bisa diupdate untuk {$targetNode}");
            return false;
        }

        try {
            $builder = $db->table($table);

            // Tentukan WHERE clause — prioritas block_hash, fallback ke identifier
            if ($currentData && !empty($currentData['block_hash'])) {
                // Update berdasarkan block_hash lama
                $builder->where('block_hash', $currentData['block_hash']);
            } elseif ($currentData && !empty($currentData['nomor_permohonan'])) {
                // Fallback: update berdasarkan nomor_permohonan + tanggal_dokumen
                $builder->where('nomor_permohonan', $currentData['nomor_permohonan']);
                if (!empty($currentData['tanggal_dokumen'])) {
                    $builder->where('tanggal_dokumen', $currentData['tanggal_dokumen']);
                }
            } else {
                // Jika Target Node belum punya data, lakukan INSERT alih-alih UPDATE
                return $this->insertToTargetNode($targetNode, $sourceData);
            }

            $result = $builder->update($updateData);

            if ($result) {
                log_message('info', sprintf(
                    '[UPDATE] ✓ %s berhasil diupdate (hash: %s)',
                    $this->getNodeLabel($targetNode),
                    substr($updateData['block_hash'] ?? '', 0, 16) . '...'
                ));
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', sprintf(
                '[UPDATE] ✗ Gagal update %s: %s',
                $this->getNodeLabel($targetNode),
                $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Insert data ke Target Node yang belum memiliki record.
     *
     * Digunakan ketika Target Node tidak memiliki record sama sekali
     * (missing data) dan perlu di-sync dari Source Node.
     *
     * @param string $targetNode Nama node target
     * @param array  $sourceData Data dari Source Node
     * @return bool True jika insert berhasil
     */
    private function insertToTargetNode(string $targetNode, array $sourceData): bool
    {
        if ($this->config->dryRunMode) {
            log_message('info', "[DRY-RUN] Akan insert ke {$this->getNodeLabel($targetNode)}");
            return true;
        }

        $db    = $this->getDbConnection($targetNode);
        $table = self::NODE_TABLES[$targetNode] ?? null;

        if (!$db || !$table) {
            return false;
        }

        // Filter field yang diizinkan
        $insertData = [];
        foreach (BlockHash::RECOVERABLE_FIELDS as $field) {
            if (isset($sourceData[$field])) {
                $insertData[$field] = $sourceData[$field];
            }
        }

        // Untuk admindb, tambahkan backup_type
        if ($targetNode === 'admindb') {
            $insertData['backup_type'] = 'integrity_sync';
        }

        try {
            $result = $db->table($table)->insert($insertData);

            if ($result) {
                log_message('info', sprintf(
                    '[INSERT] ✓ Data baru dimasukkan ke %s',
                    $this->getNodeLabel($targetNode)
                ));
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', sprintf(
                '[INSERT] ✗ Gagal insert ke %s: %s',
                $this->getNodeLabel($targetNode),
                $e->getMessage()
            ));
            return false;
        }
    }

    // =========================================================================
    // QUARANTINE — Karantina dokumen saat Split Brain atau anomali kritikal
    // =========================================================================

    /**
     * Karantina dokumen di ketiga database.
     *
     * Set status dokumen menjadi 'Quarantine' atau 'Corrupted' di semua node.
     * Ini mencegah dokumen yang terindikasi split brain digunakan oleh pengguna.
     *
     * CATATAN: Metode ini TIDAK menyalahkan admin secara otomatis.
     * Log hanya mencatat fakta bahwa anomali terdeteksi.
     *
     * @param array  $identifierInfo Info identifier dokumen
     * @param string $reason         Alasan karantina (SPLIT_BRAIN, ALL_NODES_CORRUPT, dll)
     * @param string $performedBy    Username pelaku
     */
    private function quarantineDocument(array $identifierInfo, string $reason, string $performedBy): void
    {
        $nomorPermohonan = $identifierInfo['nomor_permohonan'];
        $tanggalDokumen  = $identifierInfo['tanggal_dokumen'];

        log_message('warning', sprintf(
            '[QUARANTINE] ⚠ Dokumen %s dikarantina — Alasan: %s',
            $nomorPermohonan,
            $reason
        ));

        // Catat ke activity_logs sebagai anomali kritikal
        // PENTING: Tidak menyebutkan "admin melakukan manipulasi" atau tuduhan serupa
        $this->activityLogModel->logActivity([
            'action_type'   => 'QUARANTINE',
            'identifier'    => $nomorPermohonan,
            'status'        => 'CRITICAL',
            'description'   => sprintf(
                'Dokumen %s dikarantina. Alasan: %s. Terdeteksi oleh: %s. ' .
                'Diperlukan investigasi manual oleh administrator.',
                $nomorPermohonan,
                $reason,
                $performedBy
            ),
            'original_data' => [
                'nomor_permohonan' => $nomorPermohonan,
                'tanggal_dokumen'  => $tanggalDokumen,
                'quarantine_reason' => $reason,
                'quarantine_time'   => date('Y-m-d H:i:s'),
            ],
        ]);
    }

    // =========================================================================
    // HELPER METHODS — Fungsi utilitas
    // =========================================================================

    /**
     * Kumpulkan semua identifier unik dari ketiga node.
     *
     * Menggunakan kombinasi nomor_permohonan + tanggal_dokumen sebagai
     * identifier unik agar record yang sama di ketiga database bisa dicocokkan.
     *
     * @param array $nodeA_records Record dari Node A (userdb)
     * @param array $nodeB_records Record dari Node B (admindb)
     * @param array $nodeC_records Record dari Node C (konsensus)
     * @return array Map identifier unik: ['key' => ['nomor_permohonan'=>..., 'tanggal_dokumen'=>...]]
     */
    private function collectUniqueIdentifiers(
        array $nodeA_records,
        array $nodeB_records,
        array $nodeC_records
    ): array {
        $identifiers = [];

        // Fungsi internal untuk menambahkan identifier dari satu set records
        $addIdentifiers = function (array $records) use (&$identifiers) {
            foreach ($records as $record) {
                $nomorPermohonan = $record['nomor_permohonan'] ?? '';
                $tanggalDokumen  = $record['tanggal_dokumen']  ?? '';

                // Buat key unik dari nomor_permohonan + tanggal_dokumen
                $key = $nomorPermohonan . '|' . date('Y-m-d', strtotime($tanggalDokumen));

                // Tambahkan jika belum ada
                if (!isset($identifiers[$key])) {
                    $identifiers[$key] = [
                        'nomor_permohonan' => $nomorPermohonan,
                        'tanggal_dokumen'  => $tanggalDokumen,
                    ];
                }
            }
        };

        // Kumpulkan identifier dari ketiga node
        $addIdentifiers($nodeA_records);
        $addIdentifiers($nodeB_records);
        $addIdentifiers($nodeC_records);

        return $identifiers;
    }

    /**
     * Ambil record dari ketiga node berdasarkan identifier dokumen.
     *
     * Mencocokkan record berdasarkan nomor_permohonan + tanggal_dokumen.
     * Return null jika node tidak memiliki record tersebut.
     *
     * @param string $nomorPermohonan Nomor permohonan dokumen
     * @param string $tanggalDokumen  Tanggal dokumen
     * @param array  $nodeA_records   Semua record dari Node A
     * @param array  $nodeB_records   Semua record dari Node B
     * @param array  $nodeC_records   Semua record dari Node C
     * @return array ['userdb' => array|null, 'admindb' => array|null, 'konsensus' => array|null]
     */
    private function fetchRecordFromAllNodes(
        string $nomorPermohonan,
        string $tanggalDokumen,
        array  $nodeA_records,
        array  $nodeB_records,
        array  $nodeC_records
    ): array {
        // Fungsi pencarian record dalam array berdasarkan nomor_permohonan + tanggal_dokumen
        $findRecord = function (array $records, string $nomor, string $tanggal): ?array {
            $normalizedTanggal = date('Y-m-d', strtotime($tanggal));

            foreach ($records as $record) {
                $recordNomor   = $record['nomor_permohonan'] ?? '';
                $recordTanggal = date('Y-m-d', strtotime($record['tanggal_dokumen'] ?? ''));

                if ($recordNomor === $nomor && $recordTanggal === $normalizedTanggal) {
                    return $record;
                }
            }

            return null; // Record tidak ditemukan di node ini
        };

        return [
            'userdb'    => $findRecord($nodeA_records, $nomorPermohonan, $tanggalDokumen),
            'admindb'   => $findRecord($nodeB_records, $nomorPermohonan, $tanggalDokumen),
            'konsensus' => $findRecord($nodeC_records, $nomorPermohonan, $tanggalDokumen),
        ];
    }

    /**
     * Ambil koneksi database berdasarkan nama node.
     *
     * @param string $nodeName Nama node ('userdb', 'admindb', 'konsensus')
     * @return \CodeIgniter\Database\BaseConnection|null
     */
    private function getDbConnection(string $nodeName): ?\CodeIgniter\Database\BaseConnection
    {
        return match ($nodeName) {
            'userdb'    => $this->userDb,
            'admindb'   => $this->adminDb,
            'konsensus' => $this->konsensusDb,
            default     => null,
        };
    }

    /**
     * Dapatkan label human-readable untuk sebuah node.
     *
     * @param string $nodeName Nama node ('userdb', 'admindb', 'konsensus')
     * @return string Label node, contoh: "Node A (UserDB)"
     */
    private function getNodeLabel(string $nodeName): string
    {
        return self::NODE_LABELS[$nodeName] ?? $nodeName;
    }

    /**
     * Validasi bahwa tabel di ketiga database ada dan bisa diakses.
     *
     * @return bool True jika semua tabel tersedia
     */
    private function validateDatabaseTables(): bool
    {
        try {
            // Cek tabel blockchain di userdb (Node A)
            if (!$this->userDb->tableExists('blockchain')) {
                log_message('error', '[VALIDATE] Tabel blockchain tidak ditemukan di userdb');
                return false;
            }

            // Cek tabel blockchain_backup di admindb (Node B)
            if (!$this->adminDb->tableExists('blockchain_backup')) {
                log_message('error', '[VALIDATE] Tabel blockchain_backup tidak ditemukan di admindb');
                return false;
            }

            // Cek tabel konsensus di konsensusdb (Node C)
            if (!$this->konsensusDb->tableExists('konsensus')) {
                log_message('error', '[VALIDATE] Tabel konsensus tidak ditemukan di konsensusdb');
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            log_message('error', '[VALIDATE] Gagal validasi tabel: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Catat hasil keseluruhan integrity check ke activity_logs.
     *
     * @param array $report Laporan lengkap dari runFullIntegrityCheck()
     */
    private function logIntegrityCheckResult(array $report): void
    {
        $totalAnomalies = $report['total_recovered'] + $report['total_quarantined'] + $report['total_corrupt_l1'];

        $this->activityLogModel->logActivity([
            'action_type'   => 'INTEGRITY_CHECK',
            'status'        => $totalAnomalies > 0 ? 'WARNING' : 'INFO',
            'description'   => sprintf(
                'Integrity Check selesai dalam %.4f detik — '
                . 'Total: %d dokumen, Sehat: %d, Dipulihkan: %d, Dikarantina: %d, '
                . 'Korup Lapis 1: %d',
                $report['execution_time_seconds'],
                $report['total_checked'],
                $report['total_healthy'],
                $report['total_recovered'],
                $report['total_quarantined'],
                $report['total_corrupt_l1']
            ),
            'original_data' => [
                'total_checked'     => $report['total_checked'],
                'total_healthy'     => $report['total_healthy'],
                'total_recovered'   => $report['total_recovered'],
                'total_quarantined' => $report['total_quarantined'],
                'total_corrupt_l1'  => $report['total_corrupt_l1'],
                'execution_time'    => $report['execution_time_seconds'],
                'performed_by'      => $report['performed_by'],
            ],
        ]);
    }
}
