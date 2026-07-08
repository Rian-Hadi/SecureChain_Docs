<?php

namespace App\Controllers;

use App\Models\BlockModel;
use App\Models\BackupModel;
use App\Models\WhitelistModel;
use App\Models\ActivityLogModel;
use App\Models\UserModel;
use App\Models\RecoveryHistoryModel;
use App\Models\UploadHistoryModel;
use App\Libraries\BlockHash;
use App\Libraries\MajorityRecovery;

class Admin extends BaseController
{
    protected $blockModel;
    protected $backupModel;
    protected $whitelistModel;
    protected $activityLogModel;
    protected $userModel;
    protected $recoveryHistoryModel;
    protected $uploadHistoryModel;
    protected $majorityRecovery;
    protected $session;

    public function __construct()
    {
        // Inisialisasi model, library, dan session yang dibutuhkan.
        $this->blockModel = model(BlockModel::class);
        $this->backupModel = model(BackupModel::class);
        $this->whitelistModel = model(WhitelistModel::class);
        $this->activityLogModel = model(ActivityLogModel::class);
        $this->userModel = model(UserModel::class);
        $this->recoveryHistoryModel = model(RecoveryHistoryModel::class);
        $this->uploadHistoryModel = model(UploadHistoryModel::class);
        $this->majorityRecovery = new MajorityRecovery();
        $this->session = \Config\Services::session();
        helper(["form"]);
    }

    public function index()
    {
        // Redirect halaman utama admin ke dashboard.
        return redirect()->to("/admin/dashboard");
    }

    public function history()
    {
        $tanggal = $this->request->getGet('tanggal') ?? date('Y-m-d');
        $kategori = $this->request->getGet('kategori');

        $data = [
            'title' => 'Riwayat Upload',
            'tanggal' => $tanggal,
            'kategori' => $kategori,
            'statistics' => $this->uploadHistoryModel->getDailyStatistics($tanggal),
            'history' => $this->uploadHistoryModel->getAllHistory($tanggal, $kategori, 15),
            'pager' => $this->uploadHistoryModel->pager,
        ];

        return view('admin/history/index', $data);
    }

    private function detectManipulation(): array
    {
        // Mendeteksi manipulasi data dengan membandingkan konsensus dari 3 database.
        try {
            // Gunakan MajorityRecovery library untuk cek konsensus 3 database
            $checkResult = $this->majorityRecovery->check();

            $manipulated = [];

            // Ambil data yang corrupt (minority atau no consensus)
            if (!empty($checkResult["details"])) {
                foreach ($checkResult["details"] as $detail) {
                    // Include minority corrupt dan no consensus items
                    if (
                        in_array($detail["status"], [
                            "minority",
                            "no_consensus",
                            "hash_repair",
                            "missing",
                        ])
                    ) {
                        // Extract data dari salah satu database yang ada (preferensi: userdb)
                        $blockData = null;
                        if (!empty($detail["data"]["userdb"])) {
                            $blockData = $detail["data"]["userdb"];
                        } elseif (!empty($detail["data"]["admindb"])) {
                            $blockData = $detail["data"]["admindb"];
                        } elseif (!empty($detail["data"]["konsensus"])) {
                            $blockData = $detail["data"]["konsensus"];
                        }

                        // Tentukan database mana yang corrupt
                        $dbStatus = [
                            "userdb" => "unknown",
                            "admindb" => "unknown",
                            "konsensusdb" => "unknown",
                        ];

                        // Jika ada info corrupt_dbs dari voting
                        if (!empty($detail["corrupt_dbs"])) {
                            foreach ($detail["corrupt_dbs"] as $corruptDb) {
                                if ($corruptDb === "userdb") {
                                    $dbStatus["userdb"] = "corrupt";
                                } elseif ($corruptDb === "admindb") {
                                    $dbStatus["admindb"] = "corrupt";
                                } elseif ($corruptDb === "konsensus") {
                                    $dbStatus["konsensusdb"] = "corrupt";
                                }
                            }
                        }

                        // Database yang sehat (tidak corrupt)
                        if ($detail["status"] === "minority") {
                            foreach (array_keys($dbStatus) as $db) {
                                $dbKey =
                                    $db === "konsensusdb" ? "konsensus" : $db;
                                if (
                                    !in_array(
                                        $dbKey,
                                        $detail["corrupt_dbs"] ?? [],
                                    )
                                ) {
                                    $dbStatus[$db] = "healthy";
                                }
                            }
                        }

                        $manipulated[] = [
                            "block_id" =>
                                $blockData["id"] ??
                                ($detail["record_key"] ?? "N/A"),
                            "nomor_permohonan" =>
                                $blockData["nomor_permohonan"] ??
                                ($detail["identifier"] ?? "N/A"),
                            "nomor_dokumen" =>
                                $blockData["nomor_dokumen"] ?? "N/A",
                            "tanggal_dokumen" =>
                                $blockData["tanggal_dokumen"] ?? "N/A",
                            "current_hash" =>
                                $blockData["block_hash"] ??
                                ($detail["record_key"] ?? "N/A"),
                            "calculated_hash" =>
                                $detail["majority_hash"] ?? "N/A",
                            "has_backup" => false,
                            "backup_data" => null,
                            "issues" => [
                                $detail["status"] === "minority"
                                    ? "⚠️ Data tidak sinkron di salah satu dari tiga database."
                                    : "❌ Data berbeda di ketiga database. Perlu pemeriksaan manual.",
                                "💡 Rekomendasi: " .
                                ($detail["recommendation"] ??
                                    "Perlu pemeriksaan manual."),
                            ],
                            "status" => $detail["status"],
                            "database_status" => $dbStatus,
                        ];
                    }
                }
            }

            return $manipulated;
        } catch (\Exception $e) {
            log_message("error", "[DETECTION_ERROR] " . $e->getMessage());

            // Fallback ke deteksi lokal jika consensus check gagal
            return $this->detectManipulationLocal();
        }
    }

    private function detectManipulationLocal(): array
    {
        // Fallback: Mendeteksi manipulasi data hanya dari database utama (userdb).
        $allBlocks = $this->blockModel->getAllBlocks();
        $manipulated = [];

        foreach ($allBlocks as $block) {
            $issues = [];

            // 1. Cek integritas hash
            $recalculatedHash = BlockHash::calculate($block);

            if ($recalculatedHash !== $block["block_hash"]) {
                $issues[] =
                    "Kode keamanan tidak cocok, menandakan data mungkin telah diubah.";
            }

            // 2. Cek backup jika ada
            $backup = $this->backupModel->getBackupByIdentifier(
                $block["nomor_permohonan"],
                $block["tanggal_dokumen"],
            );

            if ($backup) {
                $fieldsToCompare = [
                    "nama_dokumen",
                    "nomor_dokumen",
                    "tanggal_filing",
                    "kategori_dokumen",
                    "dokumen_base64",
                    "ip_address",
                ];
                $fieldsDifferent = [];

                foreach ($fieldsToCompare as $field) {
                    if (
                        ($block[$field] ?? null) !==
                        ($backup[$field] ?? null)
                    ) {
                        $fieldsDifferent[] = $field;
                    }
                }

                if (!empty($fieldsDifferent)) {
                    $issues[] =
                        "Data berbeda dengan cadangan pada bagian: " .
                        implode(", ", $fieldsDifferent);
                }
            }

            // Jika ada issue, tambahkan ke list
            if (!empty($issues)) {
                $manipulated[] = [
                    "block_id" => $block["id"],
                    "nomor_permohonan" => $block["nomor_permohonan"],
                    "nomor_dokumen" => $block["nomor_dokumen"],
                    "tanggal_dokumen" => $block["tanggal_dokumen"],
                    "current_hash" => $block["block_hash"],
                    "calculated_hash" => $recalculatedHash,
                    "has_backup" => $backup !== null,
                    "backup_data" => $backup,
                    "issues" => $issues,
                    "status" => "local_check_only",
                    "database_status" => [
                        "userdb" => "checked",
                        "admindb" => "unavailable",
                        "konsensusdb" => "unavailable",
                    ],
                ];
            }
        }

        return $manipulated;
    }

    private function autoRecover(array $manipulatedData): ?array
    {
        // Memulihkan data yang termanipulasi menggunakan data dari backup.
        if (!$manipulatedData["has_backup"]) {
            return null;
        }

        $backup = $manipulatedData["backup_data"];

        $recalculatedHash = BlockHash::calculate($backup);

        // Update data blockchain dengan data dari backup + hash yang di-recalculate
        $recoveryData = [
            "nama_dokumen" => $backup["nama_dokumen"],
            "nomor_permohonan" => $backup["nomor_permohonan"],
            "nomor_dokumen" => $backup["nomor_dokumen"],
            "tanggal_dokumen" => $backup["tanggal_dokumen"],
            "tanggal_filing" => $backup["tanggal_filing"],
            "kategori_dokumen" => $backup["kategori_dokumen"] ?? "Paten",
            "dokumen_base64" => $backup["dokumen_base64"],
            "ip_address" => $backup["ip_address"],
            "block_hash" => $recalculatedHash, // Gunakan hash yang baru di-calculate
            "previous_hash" => $backup["previous_hash"],
        ];

        $updated = $this->blockModel->update(
            $manipulatedData["block_id"],
            $recoveryData,
        );

        if ($updated) {
            return [
                "block_id" => $manipulatedData["block_id"],
                "nomor_permohonan" => $backup["nomor_permohonan"],
                "status" => "recovered",
                "timestamp" => date("Y-m-d H:i:s"),
            ];
        }

        return null;
    }

    public function backups()
    {
        // Menampilkan halaman manajemen backup data.
        $data = [
            "title" => "Backup Management",
            "backups" => $this->backupModel->getAllBackups(),
        ];

        return view("admin/backup/index", $data);
    }

    public function createBackup()
    {
        // Membuat backup manual dari semua data di blockchain.
        $allBlocks = $this->blockModel->getAllBlocks();
        $successCount = 0;

        foreach ($allBlocks as $block) {
            $backupData = [
                "nomor_permohonan" => $block["nomor_permohonan"],
                "nomor_dokumen" => $block["nomor_dokumen"],
                "tanggal_dokumen" => $block["tanggal_dokumen"],
                "tanggal_filing" => $block["tanggal_filing"],
                "dokumen_base64" => $block["dokumen_base64"],
                "ip_address" => $block["ip_address"],
                "block_hash" => $block["block_hash"],
                "previous_hash" => $block["previous_hash"],
                "timestamp" => $block["timestamp"],
            ];

            if ($this->backupModel->createBackup($backupData, "manual")) {
                $successCount++;
            }
        }

        return redirect()
            ->to("/admin/backups")
            ->with(
                "success",
                "Berhasil membuat backup untuk {$successCount} data.",
            );
    }

    public function whitelist()
    {
        // Menampilkan halaman manajemen IP Whitelist.
        $data = [
            "title" => "IP Whitelist Management",
            "whitelistIPs" => $this->whitelistModel->getAllIPs(),
        ];

        return view("admin/whitelist/index", $data);
    }

    public function addWhitelist()
    {
        // Menambahkan IP baru ke dalam daftar whitelist.
        $rules = [
            "ip_address" => "required|valid_ip",
            "description" => "permit_empty|max_length[255]",
        ];

        if (!$this->validate($rules)) {
            return redirect()
                ->to("/admin/whitelist")
                ->withInput()
                ->with("error", "IP address tidak valid.");
        }

        $ipAddress = $this->request->getPost("ip_address");
        $description = $this->request->getPost("description") ?? "";

        if ($this->whitelistModel->addIP($ipAddress, $description, "admin")) {
            return redirect()
                ->to("/admin/whitelist")
                ->with(
                    "success",
                    "IP {$ipAddress} berhasil ditambahkan ke whitelist.",
                );
        }

        return redirect()
            ->to("/admin/whitelist")
            ->with("error", "Gagal menambahkan IP ke whitelist.");
    }

    public function activateIP($id)
    {
        // Mengaktifkan kembali IP yang ada di whitelist.
        if ($this->whitelistModel->activateIP($id)) {
            return redirect()
                ->to("/admin/whitelist")
                ->with("success", "IP berhasil diaktifkan.");
        }

        return redirect()
            ->to("/admin/whitelist")
            ->with("error", "Gagal mengaktifkan IP.");
    }

    public function deactivateIP($id)
    {
        // Menonaktifkan IP yang ada di whitelist.
        if ($this->whitelistModel->deactivateIP($id)) {
            return redirect()
                ->to("/admin/whitelist")
                ->with("success", "IP berhasil dinonaktifkan.");
        }

        return redirect()
            ->to("/admin/whitelist")
            ->with("error", "Gagal menonaktifkan IP.");
    }

    public function deleteWhitelist($id)
    {
        // Menghapus IP dari daftar whitelist secara permanen.
        if ($this->whitelistModel->removeIP($id)) {
            return redirect()
                ->to("/admin/whitelist")
                ->with("success", "IP berhasil dihapus dari whitelist.");
        }

        return redirect()
            ->to("/admin/whitelist")
            ->with("error", "Gagal menghapus IP dari whitelist.");
    }

    public function manualRecover($blockId)
    {
        // Memulihkan satu data spesifik secara manual dari backup.
        $block = $this->blockModel->find($blockId);

        if (!$block) {
            return redirect()
                ->to("/admin")
                ->with("error", "Data tidak ditemukan.");
        }

        $backup = $this->backupModel->getBackupByIdentifier(
            $block["nomor_permohonan"],
            $block["tanggal_dokumen"],
        );

        if (!$backup) {
            return redirect()
                ->to("/admin")
                ->with("error", "Backup tidak ditemukan untuk data ini.");
        }

        $manipulatedData = [
            "block_id" => $blockId,
            "nomor_permohonan" => $block["nomor_permohonan"],
            "nomor_dokumen" => $block["nomor_dokumen"],
            "tanggal_dokumen" => $block["tanggal_dokumen"],
            "has_backup" => true,
            "backup_data" => $backup,
        ];

        $result = $this->autoRecover($manipulatedData);

        if ($result) {
            return redirect()
                ->to("/admin")
                ->with("success", "Data berhasil di-recover dari backup.");
        }

        return redirect()
            ->to("/admin")
            ->with("error", "Gagal melakukan recovery.");
    }

    public function explorer()
    {
        // Menampilkan halaman Blockchain Explorer untuk memantau semua blok.
        $allBlocks = $this->blockModel->getAllBlocks();
        $this->ensureCrossDatabaseSync($allBlocks);
        $chainIntegrity = $this->validateChainIntegrity($allBlocks);

        // Statistik blockchain
        // FIX: Get latest block by timestamp, not by ID position in array
        $latestBlockByTime = $this->blockModel->getLatestBlockByTimestamp();
        
        $stats = [
            "total_blocks" => count($allBlocks),
            "total_documents" => count($allBlocks),
            "latest_block_time" => $latestBlockByTime ? $latestBlockByTime["timestamp"] : null,
            "chain_valid" => $chainIntegrity["is_valid"],
            "invalid_blocks" => $chainIntegrity["invalid_blocks"],
            "genesis_block" => !empty($allBlocks) ? $allBlocks[0] : null,
        ];

        $data = [
            "title" => "Blockchain Explorer",
            "blocks" => $allBlocks,
            "stats" => $stats,
            "chainIntegrity" => $chainIntegrity,
        ];

        return view("admin/blockchain/explorer", $data);
    }

    private function validateChainIntegrity(array $blocks): array
    {
        // Memvalidasi integritas keseluruhan rantai blok dan konsensus 3 database.
        $invalidBlocks = [];
        $isValid = true;
        $previousHash = "0"; // Genesis block

        // Koneksi ke 3 database untuk validasi
        $userDb = \Config\Database::connect("userdb");
        $adminDb = \Config\Database::connect("admindb");
        $konsensusDb = \Config\Database::connect("konsensus");

        foreach ($blocks as $index => $block) {
            $errors = [];
            $errorSources = []; // Track which nodes have errors

            // 1. Validasi previous hash
            if ($block["previous_hash"] !== $previousHash) {
                $errors[] = "Kaitan antar blok rusak. Rantai data tidak utuh.";
                $isValid = false;
            }

            // 2. Validasi block hash integrity (harus canonical, bukan legacy)
            $recalculatedHash = BlockHash::calculate($block);

            if ($recalculatedHash !== $block["block_hash"]) {
                $legacyNote = BlockHash::getHashMatchType($block) === 'legacy'
                    ? ' Format legacy terdeteksi — jalankan Pulihkan Blok.'
                    : '';
                $errors[] =
                    "Kode keamanan blok tidak valid. Data kemungkinan telah diubah." . $legacyNote;
                $isValid = false;
            }

            // 3. Validasi konsensus 3 database
            $databaseValidation = $this->validate3DatabaseConsensus(
                $block,
                $userDb,
                $adminDb,
                $konsensusDb,
            );

            if (!$databaseValidation["is_consensus"]) {
                $errors[] =
                    "Data tidak sinkron antar database: " .
                    implode(", ", $databaseValidation["errors"]);
                $isValid = false;

                // Determine which nodes have errors based on validation results
                $errorSources = $this->extractErrorSources(
                    $block,
                    $userDb,
                    $adminDb,
                    $konsensusDb,
                    $databaseValidation
                );
            }

            if (!empty($errors)) {
                $invalidBlocks[] = [
                    "block_number" => $block["id"],
                    "nomor_permohonan" => $block["nomor_permohonan"],
                    "errors" => $errors,
                    "database_validation" => $databaseValidation,
                    "error_sources" => $errorSources,
                ];
            }

            $previousHash = $block["block_hash"];
        }

        return [
            "is_valid" => $isValid,
            "total_blocks" => count($blocks),
            "invalid_count" => count($invalidBlocks),
            "invalid_blocks" => $invalidBlocks,
        ];
    }

    /**
     * Extract which database nodes have errors
     */
    private function extractErrorSources(
        $block,
        $userDb,
        $adminDb,
        $konsensusDb,
        $databaseValidation
    ): array {
        $errorSources = [];
        
        $blockHash = $block["block_hash"];
        $nomorPermohonan = $block["nomor_permohonan"];
        $tanggalDokumen = $block["tanggal_dokumen"];

        // Get records from all 3 databases
        $userRecord = $userDb->table('blockchain')
            ->where('block_hash', $blockHash)
            ->orWhere('nomor_permohonan', $nomorPermohonan)
            ->get()->getRow();

        $adminRecord = $adminDb->table('blockchain_backup')
            ->where('block_hash', $blockHash)
            ->orWhere('nomor_permohonan', $nomorPermohonan)
            ->get()->getRow();

        $konsensusRecord = $konsensusDb->table('konsensus')
            ->where('block_hash', $blockHash)
            ->orWhere('nomor_permohonan', $nomorPermohonan)
            ->get()->getRow();

        // Check if records exist in each database
        if (!$userRecord) {
            $errorSources[] = 'primary'; // primary = userdb
        }
        if (!$adminRecord) {
            $errorSources[] = 'backup'; // backup = admindb
        }
        if (!$konsensusRecord) {
            $errorSources[] = 'consensus'; // consensus = konsensus
        }

        // If all records exist, check checksums to determine which has different data
        if ($userRecord && $adminRecord && $konsensusRecord && 
            isset($databaseValidation['checksums'])) {
            
            $checksums = $databaseValidation['checksums'];
            $userChecksum = $checksums['userdb'] ?? null;
            $adminChecksum = $checksums['admindb'] ?? null;
            $consensusChecksum = $checksums['konsensusdb'] ?? null;

            // Determine which node(s) have different checksums
            if ($userChecksum && $adminChecksum && $userChecksum !== $adminChecksum) {
                // Either userdb or admindb (or both) is wrong
                // We assume majority (2 nodes) is correct, minority (1 node) is wrong
                if ($userChecksum !== $consensusChecksum) {
                    $errorSources[] = 'primary'; // userdb differs from consensus
                } else {
                    $errorSources[] = 'backup'; // admindb differs from user & consensus
                }
            }
            
            if ($userChecksum && $consensusChecksum && $userChecksum !== $consensusChecksum) {
                if (!in_array('primary', $errorSources) && !in_array('consensus', $errorSources)) {
                    if ($adminChecksum === $consensusChecksum) {
                        $errorSources[] = 'primary'; // userdb differs
                    } else {
                        $errorSources[] = 'consensus'; // consensus differs
                    }
                }
            }
        }

        return array_values(array_unique($errorSources));
    }

    /**
     * Pastikan data valid di userdb tersalin ke admindb dan konsensus.
     */
    private function ensureCrossDatabaseSync(array $allBlocks): void
    {
        $konsensusDb = \Config\Database::connect("konsensus");

        foreach ($allBlocks as $block) {
            if (BlockHash::calculate($block) !== $block["block_hash"]) {
                continue;
            }

            $syncData = BlockHash::extractSyncData($block);

            $existsInKonsensus = $konsensusDb
                ->table("konsensus")
                ->where("block_hash", $block["block_hash"])
                ->countAllResults() > 0;

            if (!$existsInKonsensus) {
                try {
                    $konsensusDb->table("konsensus")->insert($syncData);
                    log_message("info", "[ADMIN-SYNC] Tambah ke konsensus: {$block['block_hash']}");
                } catch (\Exception $e) {
                    log_message("warning", "[ADMIN-SYNC] Gagal sync konsensus: " . $e->getMessage());
                }
            }

            $adminBackup = $this->backupModel->getBackupByIdentifier(
                $block["nomor_permohonan"],
                $block["tanggal_dokumen"],
            );

            if (!$adminBackup) {
                $this->backupModel->createBackup($block, "auto_sync");
                log_message("info", "[ADMIN-SYNC] Tambah ke admin backup: {$block['nomor_permohonan']}");
                continue;
            }

            $needsUpdate = false;
            foreach (BlockHash::CONSENSUS_COMPARE_FIELDS as $field) {
                if (($adminBackup[$field] ?? null) !== ($block[$field] ?? null)) {
                    $needsUpdate = true;
                    break;
                }
            }

            if ($needsUpdate) {
                $this->backupModel->update($adminBackup["id"], $syncData);
                log_message("info", "[ADMIN-SYNC] Perbarui admin backup: {$block['nomor_permohonan']}");
            }
        }
    }

    private function validate3DatabaseConsensus(
        $block,
        $userDb,
        $adminDb,
        $konsensusDb,
    ): array {
        // Memvalidasi konsistensi satu blok data di ketiga database.
        $blockHash = $block["block_hash"];
        $nomorPermohonan = $block["nomor_permohonan"];
        $tanggalDokumen = $block["tanggal_dokumen"];

        // Ambil data dari ketiga database berdasarkan block_hash (prioritas) atau identifier fallback
        $userRecord = $userDb
            ->table("blockchain")
            ->where("block_hash", $blockHash)
            ->get()
            ->getRow();

        if (!$userRecord) {
            $userRecord = $userDb
                ->table("blockchain")
                ->where("nomor_permohonan", $nomorPermohonan)
                ->where("tanggal_dokumen", $tanggalDokumen)
                ->get()
                ->getRow();
        }

        $adminRecord = $adminDb
            ->table("blockchain_backup")
            ->where("block_hash", $blockHash)
            ->get()
            ->getRow();

        if (!$adminRecord) {
            $adminRecord = $adminDb
                ->table("blockchain_backup")
                ->where("nomor_permohonan", $nomorPermohonan)
                ->where("tanggal_dokumen", $tanggalDokumen)
                ->get()
                ->getRow();
        }

        $konsensusRecord = $konsensusDb
            ->table("konsensus")
            ->where("block_hash", $blockHash)
            ->get()
            ->getRow();

        if (!$konsensusRecord) {
            $konsensusRecord = $konsensusDb
                ->table("konsensus")
                ->where("nomor_permohonan", $nomorPermohonan)
                ->where("tanggal_dokumen", $tanggalDokumen)
                ->get()
                ->getRow();
        }

        $errors = [];
        $foundInDatabases = 0;

        // Cek ada tidaknya data di masing-masing database
        if ($userRecord) {
            $foundInDatabases++;
        } else {
            $errors[] = "Tidak ditemukan di Database Utama";
        }

        if ($adminRecord) {
            $foundInDatabases++;
        } else {
            $errors[] = "Tidak ditemukan di Database Cadangan";
        }

        if ($konsensusRecord) {
            $foundInDatabases++;
        } else {
            $errors[] = "Tidak ditemukan di Database Verifikasi";
        }

        // Jika data tidak ditemukan di semua database
        if ($foundInDatabases === 0) {
            return [
                "is_consensus" => false,
                "errors" => [
                    "Block #{$block["id"]} tidak ditemukan di ketiga database",
                ],
                "found_in_databases" => 0,
            ];
        }

        // Jika data hanya ditemukan di satu atau dua database
        if ($foundInDatabases < 3) {
            return [
                "is_consensus" => false,
                "errors" => $errors,
                "found_in_databases" => $foundInDatabases,
            ];
        }

        // Validasi kesamaan data dari ketiga database
        $checksums = [];
        $dataToCompare = BlockHash::CONSENSUS_COMPARE_FIELDS;

        // Hitung checksum untuk setiap database
        $userDataString = "";
        $adminDataString = "";
        $konsensusDataString = "";

        foreach ($dataToCompare as $field) {
            if (isset($userRecord->$field)) {
                $userDataString .= $userRecord->$field;
            }
            if (isset($adminRecord->$field)) {
                $adminDataString .= $adminRecord->$field;
            }
            if (isset($konsensusRecord->$field)) {
                $konsensusDataString .= $konsensusRecord->$field;
            }
        }

        $checksums["userdb"] = hash("sha256", $userDataString);
        $checksums["admindb"] = hash("sha256", $adminDataString);
        $checksums["konsensusdb"] = hash("sha256", $konsensusDataString);

        // Cek apakah semua checksum sama (consensus)
        $uniqueChecksums = array_unique($checksums);

        if (count($uniqueChecksums) === 1) {
            // Semua database memiliki data yang sama
            return [
                "is_consensus" => true,
                "errors" => [],
                "found_in_databases" => 3,
                "checksums" => $checksums,
            ];
        } else {
            // Ada perbedaan data antar database
            $mismatchErrors = [];
            if ($checksums["userdb"] !== $checksums["admindb"]) {
                $mismatchErrors[] = "Data UserDB ≠ AdminDB";
            }
            if ($checksums["userdb"] !== $checksums["konsensusdb"]) {
                $mismatchErrors[] = "Data UserDB ≠ KonsensusDB";
            }
            if ($checksums["admindb"] !== $checksums["konsensusdb"]) {
                $mismatchErrors[] = "Data AdminDB ≠ KonsensusDB";
            }

            return [
                "is_consensus" => false,
                "errors" => $mismatchErrors,
                "found_in_databases" => 3,
                "checksums" => $checksums,
            ];
        }
    }

    public function dashboard()
    {
        // Menampilkan halaman dashboard utama dengan statistik lengkap.
        $allBlocks = $this->blockModel->getAllBlocks();
        $this->ensureCrossDatabaseSync($allBlocks);
        $chainIntegrity = $this->validateChainIntegrity($allBlocks);
        $manipulatedData = $this->detectManipulation();

        // Auto-recovery jika ada data yang dimanipulasi
        $recoveryResults = [];
        if (!empty($manipulatedData)) {
            foreach ($manipulatedData as $data) {
                $result = $this->autoRecover($data);
                if ($result) {
                    $recoveryResults[] = $result;
                }
            }
        }

        // Statistik lengkap
        // Chain dianggap INVALID jika ada manipulasi data ATAU chain integrity rusak
        $isChainValid =
            $chainIntegrity["is_valid"] && count($manipulatedData) === 0;

        // FIX: Get latest block by timestamp, not by ID position in array
        $latestBlockByTime = $this->blockModel->getLatestBlockByTimestamp();
        
        $stats = [
            "total_blocks" => count($allBlocks),
            "total_backups" => $this->backupModel->countBackups(),
            "total_whitelist" => $this->whitelistModel->countAllResults(false),
            "active_whitelist" => count($this->whitelistModel->getActiveIPs()),
            "latest_block_time" => $latestBlockByTime ? $latestBlockByTime["timestamp"] : null,
            "chain_valid" => $isChainValid,
            "manipulated_count" => count($manipulatedData),
            "genesis_block" => !empty($allBlocks) ? $allBlocks[0] : null,
        ];

        $data = [
            "title" => "Dashboard",
            "stats" => $stats,
            "latestBlocks" => array_slice(array_reverse($allBlocks), 0, 10),
            "latestBackups" => $this->backupModel->getLatestBackups(5),
            "whitelistIPs" => $this->whitelistModel->getAllIPs(),
            "manipulatedData" => $manipulatedData,
            "recoveryResults" => $recoveryResults,
            "chainIntegrity" => $chainIntegrity,
            "activityLogs" => $this->activityLogModel->getDashboardLogs(10),
        ];

        return view("admin/dashboard/index", $data);
    }

    public function monitoring()
    {
        // Menampilkan halaman monitoring kesehatan sistem secara real-time.
        $allBlocks = $this->blockModel->getAllBlocks();
        $this->ensureCrossDatabaseSync($allBlocks);
        $allBackups = $this->backupModel->getAllBackups();

        // Hitung blocks dalam 24 jam terakhir
        $blocks24h = 0;
        $yesterday = strtotime("-24 hours");
        foreach ($allBlocks as $block) {
            $blockTime = is_numeric($block["timestamp"]) ? (int)$block["timestamp"] : strtotime($block["timestamp"]);
            if ($blockTime >= $yesterday) {
                $blocks24h++;
            }
        }

        // Last backup time
        $lastBackupTime = "N/A";
        if (!empty($allBackups)) {
            $lastBackupTime = date(
                "d/m/Y H:i",
                strtotime($allBackups[0]["backup_timestamp"]),
            );
        }

        $manipulatedData = $this->detectManipulation();

        // Statistik monitoring
        $stats = [
            "total_blocks" => count($allBlocks),
            "blocks_24h" => $blocks24h,
            "total_backups" => count($allBackups),
            "last_backup_time" => $lastBackupTime,
            "active_ips" => count($this->whitelistModel->getActiveIPs()),
            "total_ips" => $this->whitelistModel->countAllResults(false),
            "total_issues" => count($manipulatedData),
            "genesis_block" => !empty($allBlocks) ? $allBlocks[0] : null,
        ];

        $data = [
            "title" => "System Monitoring",
            "stats" => $stats,
            "latestBlocks" => array_slice(array_reverse($allBlocks), 0, 10),
            "latestBackups" => array_slice($allBackups, 0, 5),
        ];

        // Defensive check: ensure the view file exists to avoid obscure framework exception
        $viewFile =
            APPPATH .
            "Views" .
            DIRECTORY_SEPARATOR .
            "admin" .
            DIRECTORY_SEPARATOR .
            "blockchain" .
            DIRECTORY_SEPARATOR .
            "monitoring.php";
        if (!file_exists($viewFile)) {
            // Log and return a helpful message in development mode
            log_message(
                "error",
                "[VIEW_MISSING] Expected view file not found: " . $viewFile,
            );
            return $this->response
                ->setStatusCode(500)
                ->setBody("View file missing: " . $viewFile);
        }

        return view("admin/blockchain/monitoring", $data);
    }

    public function users()
    {
        // Menampilkan halaman manajemen pengguna.
        $users = $this->userModel->orderBy("created_at", "DESC")->findAll();

        $data = [
            "title" => "User Management",
            "users" => $users,
        ];

        return view("admin/users/index", $data);
    }

    public function addUser()
    {
        // Memproses penambahan pengguna baru.
        $allowedDivisi = ['Paten', 'Merek', 'Hak Cipta', 'Desain Industri', 'Admin'];
        
        $data = [
            "username" => $this->request->getPost("username"),
            "password" => $this->request->getPost("password"), // Will be auto-hashed by model
            "full_name" => $this->request->getPost("full_name"),
            "email" => $this->request->getPost("email"),
            "role" => $this->request->getPost("role"),
            "divisi" => in_array($this->request->getPost("divisi"), $allowedDivisi) ? $this->request->getPost("divisi") : 'Paten',
            "is_active" => 1,
        ];

        if ($this->userModel->save($data)) {
            return redirect()
                ->to("/admin/users")
                ->with("success", "User berhasil ditambahkan");
        } else {
            return redirect()
                ->back()
                ->withInput()
                ->with("errors", $this->userModel->errors());
        }
    }

    public function editUser($id)
    {
        // Menampilkan form untuk mengedit data pengguna.
        $user = $this->userModel->find($id);

        if (!$user) {
            return redirect()
                ->to("/admin/users")
                ->with("error", "User tidak ditemukan");
        }

        $data = [
            "title" => "Edit User",
            "user" => $user,
        ];

        return view("admin/users/edit", $data);
    }

    public function updateUser($id)
    {
        // Memproses pembaruan data pengguna.
        $user = $this->userModel->find($id);

        if (!$user) {
            return redirect()
                ->to("/admin/users")
                ->with("error", "User tidak ditemukan");
        }

        // Prevent self-role change (security)
        $newRole = $this->request->getPost("role");
        if ($id == session()->get("user_id") && $newRole !== $user["role"]) {
            return redirect()
                ->back()
                ->withInput()
                ->with(
                    "error",
                    "Tidak dapat mengubah role diri sendiri. Silakan minta admin lain untuk melakukannya.",
                );
        }

        $data = [
            "username" => $this->request->getPost("username"),
            "full_name" => $this->request->getPost("full_name"),
            "role" => $newRole,
            "divisi" => $this->request->getPost("divisi"),
        ];

        // Only include password if provided
        $password = $this->request->getPost("password");
        if (!empty($password)) {
            $data["password"] = $password;
        }

        // Gunakan rules update khusus: is_unique skip ID sendiri, password opsional
        $updateRules = $this->userModel->getUpdateRules((int) $id);

        // Validasi manual dengan rules update
        $validation = \Config\Services::validation();
        $validation->setRules($updateRules, [
            "username" => [
                "required" => "Username wajib diisi",
                "alpha_numeric" =>
                    "Username hanya boleh berisi huruf dan angka",
                "min_length" => "Username minimal 4 karakter",
                "is_unique" => "Username sudah digunakan",
            ],
            "password" => [
                "min_length" => "Password minimal 6 karakter",
            ],
            "full_name" => [
                "required" => "Nama lengkap wajib diisi",
                "min_length" => "Nama lengkap minimal 3 karakter",
            ],
            "role" => [
                "required" => "Role wajib dipilih",
                "in_list" => "Role tidak valid",
            ],
            "divisi" => [
                "required" => "Divisi wajib dipilih",
                "in_list" => "Divisi tidak valid",
            ],
        ]);

        if (!$validation->run($data)) {
            return redirect()
                ->back()
                ->withInput()
                ->with("errors", $validation->getErrors());
        }

        // Skip model-level validation karena sudah divalidasi manual
        $this->userModel->skipValidation(true);

        if ($this->userModel->update($id, $data)) {
            $this->userModel->skipValidation(false);

            // If updating self, update session data
            if ($id == session()->get("user_id")) {
                session()->set([
                    "username" => $data["username"],
                    "full_name" => $data["full_name"],
                    "divisi" => $data["divisi"],
                ]);
            }

            return redirect()
                ->to("/admin/users")
                ->with("success", "User berhasil diupdate");
        } else {
            $this->userModel->skipValidation(false);

            return redirect()
                ->back()
                ->withInput()
                ->with("errors", $this->userModel->errors());
        }
    }

    public function deleteUser($id)
    {
        // Menghapus pengguna dari sistem.
        $user = $this->userModel->find($id);

        if (!$user) {
            return redirect()->back()->with("error", "User tidak ditemukan");
        }

        $isCurrentUser = $id == session()->get("user_id");

        if ($this->userModel->delete($id)) {
            if ($isCurrentUser) {
                // If deleting current user, destroy session and redirect to login
                session()->destroy();
                delete_cookie("jwt_token");

                return redirect()
                    ->to("/auth/login")
                    ->with(
                        "success",
                        "Akun Anda berhasil dihapus. Anda telah logout otomatis.",
                    );
            } else {
                return redirect()
                    ->to("/admin/users")
                    ->with("success", "User berhasil dihapus");
            }
        } else {
            return redirect()->back()->with("error", "Gagal menghapus user");
        }
    }

    public function toggleUserStatus($id)
    {
        // Mengubah status aktif/nonaktif seorang pengguna.
        $user = $this->userModel->find($id);

        if (!$user) {
            return redirect()->back()->with("error", "User tidak ditemukan");
        }

        // Allow self-toggle but with warning
        if ($id == session()->get("user_id")) {
            // If user is trying to deactivate themselves
            if ($user["is_active"] == 1) {
                return redirect()
                    ->back()
                    ->with(
                        "error",
                        "Tidak dapat menonaktifkan diri sendiri. Silakan minta admin lain untuk melakukannya.",
                    );
            }
        }

        $newStatus = $user["is_active"] == 1 ? 0 : 1;

        if ($this->userModel->update($id, ["is_active" => $newStatus])) {
            $status = $newStatus == 1 ? "diaktifkan" : "dinonaktifkan";
            return redirect()
                ->to("/admin/users")
                ->with("success", "User berhasil {$status}");
        } else {
            return redirect()
                ->back()
                ->with("error", "Gagal mengubah status user");
        }
    }

    // ========================================
    // CONSENSUS RECOVERY METHODS
    // ========================================

    public function consensusCheck()
    {
        // Menjalankan pengecekan konsensus 3 database dan menampilkan hasilnya.
        try {
            $checkResult = $this->majorityRecovery->check();

            // Consensus Check Results
            $data = [
                "title" => "Consensus Check Results",
                "result" => $checkResult,
                "stats" => [
                    "total_checked" => $checkResult["total_checked"],
                    "healthy" => $checkResult["healthy"],
                    "minority_corrupt" => $checkResult["minority_corrupt"],
                    "hash_repair" => $checkResult["hash_repair"] ?? 0,
                    "no_consensus" => $checkResult["no_consensus"],
                    "missing_in_db" => $checkResult["missing_in_db"],
                    "execution_time" => $checkResult["execution_time"],
                ],
                "details" => $checkResult["details"],
                "alert" =>
                    $checkResult["minority_corrupt"] > 0 ||
                    ($checkResult["hash_repair"] ?? 0) > 0 ||
                    $checkResult["no_consensus"] > 0 ||
                    ($checkResult["missing_in_db"] ?? 0) > 0,
            ];

            return view("admin/consensus/check_result", $data);
        } catch (\Exception $e) {
            log_message(
                "error",
                "[CONSENSUS_CHECK] Error: " . $e->getMessage(),
            );

            return redirect()
                ->to("/admin/monitoring")
                ->with(
                    "error",
                    "Gagal melakukan consensus check: " . $e->getMessage(),
                );
        }
    }

    public function consensusRecover()
    {
        // Menjalankan pemulihan otomatis untuk data yang tidak konsisten.
        try {
            $allBlocks = $this->blockModel->getAllBlocks();
            $this->ensureCrossDatabaseSync($allBlocks);

            $checkResult = $this->majorityRecovery->check();

            $recoverableItems = array_filter(
                $checkResult["details"],
                static function ($item) {
                    return in_array($item["status"] ?? "", [
                        "minority",
                        "missing",
                        "hash_repair",
                    ], true);
                },
            );

            if (empty($recoverableItems)) {
                $hashRepairCount = (int) ($checkResult["hash_repair"] ?? 0);
                $minorityCount = (int) ($checkResult["minority_corrupt"] ?? 0);

                return redirect()
                    ->to("/admin/monitoring")
                    ->with(
                        "info",
                        "Tidak ada data yang bisa dipulihkan otomatis. "
                        . "Minority: {$minorityCount}, Hash repair: {$hashRepairCount}. "
                        . "Blok dengan no-consensus perlu tinjauan manual.",
                    );
            }

            $performedBy = session()->get("username") ?? "admin";
            $recoveryResult = $this->majorityRecovery->recover(
                array_values($recoverableItems),
                $performedBy,
            );

            // Prepare flash message
            $message = sprintf(
                "<strong>🔄 Pemulihan Otomatis Selesai</strong><br>" .
                    "✓ Berhasil: %d<br>" .
                    "✗ Gagal: %d<br>" .
                    "⊘ Dilewati: %d",
                $recoveryResult["success"],
                $recoveryResult["failed"],
                $recoveryResult["skipped"],
            );

            $flashType = $recoveryResult["failed"] > 0 ? "warning" : "success";

            return redirect()
                ->to("/admin/monitoring")
                ->with($flashType, $message);
        } catch (\Exception $e) {
            log_message(
                "error",
                "[CONSENSUS_RECOVER] Error: " . $e->getMessage(),
            );

            return redirect()
                ->to("/admin/monitoring")
                ->with(
                    "error",
                    "Gagal melakukan auto-recovery: " . $e->getMessage(),
                );
        }
    }

    public function recoveryHistory()
    {
        // Menampilkan riwayat semua aktivitas pemulihan data.
        $limit = $this->request->getGet("limit") ?? 50;
        $filters = [];

        // Apply filters dari query string
        if ($type = $this->request->getGet("type")) {
            $filters["recovery_type"] = $type;
        }
        if ($status = $this->request->getGet("status")) {
            $filters["status"] = $status;
        }

        $history = $this->recoveryHistoryModel->getHistory($limit, $filters);
        $stats = $this->recoveryHistoryModel->getStatistics();

        $data = [
            "title" => "Recovery History",
            "history" => $history,
            "stats" => $stats,
            "filters" => $filters,
        ];

        return view("admin/consensus/history", $data);
    }

    public function consensusRollback($historyId)
    {
        // Mengembalikan data ke kondisi sebelum proses pemulihan.
        try {
            $performedBy = session()->get("username") ?? "admin";
            $rollbackResult = $this->majorityRecovery->rollback(
                $historyId,
                $performedBy,
            );

            if ($rollbackResult["success"]) {
                return redirect()
                    ->to("/admin/consensus/history")
                    ->with(
                        "success",
                        "✓ Recovery berhasil di-rollback: " .
                            $rollbackResult["message"],
                    );
            } else {
                return redirect()
                    ->to("/admin/consensus/history")
                    ->with(
                        "error",
                        "✗ Rollback gagal: " . $rollbackResult["error"],
                    );
            }
        } catch (\Exception $e) {
            log_message(
                "error",
                "[CONSENSUS_ROLLBACK] Error: " . $e->getMessage(),
            );

            return redirect()
                ->to("/admin/consensus/history")
                ->with(
                    "error",
                    "Gagal melakukan rollback: " . $e->getMessage(),
                );
        }
    }

    public function quickConsensusCheck()
    {
        // Endpoint AJAX untuk pengecekan konsensus cepat di halaman monitoring.
        try {
            $checkResult = $this->majorityRecovery->check();

            // DEBUG: Log the structure for inspection
            log_message('debug', '[QUICK_CHECK] checkResult keys: ' . implode(', ', array_keys($checkResult)));
            log_message('debug', '[QUICK_CHECK] total_checked: ' . ($checkResult['total_checked'] ?? 'N/A'));
            log_message('debug', '[QUICK_CHECK] healthy: ' . ($checkResult['healthy'] ?? 'N/A'));
            log_message('debug', '[QUICK_CHECK] details count: ' . count($checkResult['details'] ?? []));
            
            if (!empty($checkResult['details'])) {
                $firstDetail = reset($checkResult['details']);
                log_message('debug', '[QUICK_CHECK] First detail keys: ' . implode(', ', array_keys((array)$firstDetail)));
                if (!empty($firstDetail['corrupt_dbs'])) {
                    log_message('debug', '[QUICK_CHECK] First detail corrupt_dbs: ' . json_encode($firstDetail['corrupt_dbs']));
                }
            }

            $totalAnomalies = (int)($checkResult['minority_corrupt'] ?? 0)
                + (int)($checkResult['no_consensus'] ?? 0)
                + (int)($checkResult['missing_in_db'] ?? 0)
                + (int)($checkResult['hash_repair'] ?? 0);

            $healthRecords = $checkResult['healthy'] ?? 0;
            $totalChecked = $checkResult['total_checked'] ?? 0;

            $healthPercentage = $totalChecked > 0
                ? round(($healthRecords / $totalChecked) * 100, 2)
                : 100;

            $isTrulyHealthy = $totalAnomalies === 0;
            $minorityCount = (int)($checkResult['minority_corrupt'] ?? 0);
            $hasVotingAnomaly = $minorityCount > 0;

            // Get anomali per node - REAL-TIME calculation from check details
            $anomaliesByNode = $this->calculateAnomaliesPerNode(
                $checkResult['details'] ?? [],
                $checkResult['minority_corrupt'] ?? 0,
                $checkResult['no_consensus'] ?? 0,
                $checkResult['missing_in_db'] ?? 0
            );

            log_message('debug', '[QUICK_CHECK] anomaliesByNode result: ' . json_encode($anomaliesByNode));

            return $this->response->setJSON([
                "success" => true,
                "data" => [
                    "total_checked" => $totalChecked,
                    "healthy" => $healthRecords,
                    "minority_corrupt" => $minorityCount,
                    "hash_repair" => (int)($checkResult['hash_repair'] ?? 0),
                    "no_consensus" => (int)($checkResult['no_consensus'] ?? 0),
                    "missing_in_db" => (int)($checkResult['missing_in_db'] ?? 0),
                    "deleted_from_userdb" => 0,
                    "total_anomalies" => $totalAnomalies,
                    "health_percentage" => $healthPercentage,
                    "is_truly_healthy" => $isTrulyHealthy,

                    "needs_attention" => $totalAnomalies,
                    "last_check_time" => date('Y-m-d H:i:s'),
                    "execution_time" => $checkResult['execution_time'] ?? 0,
                    "anomalies_by_node" => $anomaliesByNode,
                    "anomaly_details" => array_values(array_map(function($detail) {
                        $status = $detail['status'] ?? '';
                        $deletable_dbs = [];
                        
                        if ($status === 'no_consensus') {
                            $deletable_dbs = $detail['corrupt_dbs'] ?? [];
                        } elseif ($status === 'missing') {
                            // Jika hilang di 2, maka hapus dari 1 yang punya
                            $all_dbs = ['userdb', 'admindb', 'konsensus'];
                            $deletable_dbs = array_values(array_diff($all_dbs, $detail['corrupt_dbs'] ?? []));
                        } elseif ($status === 'missing_from_userdb') {
                            // Hapus dari node yang memiliki data ini
                            $deletable_dbs = $detail['exists_in_dbs'] ?? [];
                        }
                        
                        $detail['deletable_dbs'] = $deletable_dbs;
                        return $detail;
                    }, array_filter($checkResult['details'] ?? [], function($detail) {
                        $status = $detail['status'] ?? '';
                        
                        if ($status === 'no_consensus') return true;
                        if ($status === 'missing') return count($detail['corrupt_dbs'] ?? []) === 2;
                        if ($status === 'missing_from_userdb') return count($detail['exists_in_dbs'] ?? []) === 1;
                        
                        return false;
                    }))),
                ],
            ]);
        } catch (\Exception $e) {
            log_message('error', '[QUICK_CHECK_ERROR] ' . $e->getMessage());
            return $this->response->setJSON([
                "success" => false,
                "error" => $e->getMessage(),
            ]);
        }
    }

    public function deleteAnomaly()
    {
        $blockHash = $this->request->getPost('block_hash');
        $targetDb = $this->request->getPost('target_db'); // 'userdb', 'admindb', or 'konsensus'

        if (!$blockHash || !$targetDb) {
            return $this->response->setJSON(['success' => false, 'message' => 'Parameter tidak lengkap']);
        }

        $dbConnection = null;
        $table = '';
        $triggerName = '';
        $databaseName = '';

        if ($targetDb === 'userdb') {
            $dbConnection = \Config\Database::connect('userdb');
            $table = 'blockchain';
            $triggerName = 'prevent_delete_blockchain';
            $databaseName = 'poa_user_db';
        } elseif ($targetDb === 'admindb') {
            $dbConnection = \Config\Database::connect('admindb');
            $table = 'blockchain_backup';
            $triggerName = 'prevent_delete_blockchain_backup';
            $databaseName = 'poa_admin_db';
        } elseif ($targetDb === 'konsensus') {
            $dbConnection = \Config\Database::connect('konsensus');
            $table = 'konsensus';
            $triggerName = 'prevent_delete_konsensus';
            $databaseName = 'poa_konsensus_db';
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Target database tidak valid']);
        }

        try {
            // Drop trigger temporarily
            $dbConnection->query("DROP TRIGGER IF EXISTS {$triggerName}");

            // Delete the corrupted record
            $dbConnection->table($table)->where('block_hash', $blockHash)->delete();

            // Recreate trigger
            $triggerSql = "CREATE TRIGGER {$triggerName} BEFORE DELETE ON {$table} FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Data pada tabel {$table} ({$databaseName}) tidak boleh dihapus'; END";
            $dbConnection->query($triggerSql);

            // Log activity
            $this->activityLogModel->logActivity([
                'action_type'   => 'MANUAL_DELETE',
                'status'        => 'WARNING',
                'description'   => "Manual deletion of anomaly block {$blockHash} from {$targetDb}",
                'original_data' => ['block_hash' => $blockHash]
            ]);

            return $this->response->setJSON(['success' => true, 'message' => 'Blok anomali berhasil dihapus dari ' . $targetDb]);
        } catch (\Exception $e) {
            // Try to recreate trigger if it failed
            try {
                $triggerSql = "CREATE TRIGGER {$triggerName} BEFORE DELETE ON {$table} FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Data pada tabel {$table} ({$databaseName}) tidak boleh dihapus'; END";
                $dbConnection->query($triggerSql);
            } catch (\Exception $e2) {
                // Ignore recreate error
            }
            return $this->response->setJSON(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
        }
    }

    /**
     * Calculate real-time anomalies per node from check details
     */
    private function calculateAnomaliesPerNode(
        array $details,
        int $minorityCount,
        int $noConsensusCount,
        int $missingInDbCount
    ): array {
        $anomaliesByNode = [
            'userdb' => 0,
            'admindb' => 0,
            'konsensus' => 0,
        ];

        // Process each detail record to identify which nodes have anomalies.
        // We use ONLY corrupt_dbs as the single source of truth, since:
        // - corrupt_dbs = nodes that differ from majority (minority case) OR all nodes (no_consensus)
        // - vote_breakdown.minority_dbs covers the same set and would cause double-counting.
        foreach ($details as $detail) {
            $counted = []; // Track which nodes we already counted for this record

            // Primary source: corrupt_dbs (nodes identified as corrupt by voting)
            if (!empty($detail['corrupt_dbs']) && is_array($detail['corrupt_dbs'])) {
                foreach ($detail['corrupt_dbs'] as $corruptDb) {
                    $node = $this->mapDbNameToNodeName($corruptDb);
                    if (isset($anomaliesByNode[$node]) && !in_array($node, $counted)) {
                        $anomaliesByNode[$node]++;
                        $counted[] = $node;
                    }
                }
            }

            // Secondary source: missing_dbs from vote_breakdown (nodes that are absent entirely)
            // Only count a node here if it was NOT already counted via corrupt_dbs above.
            if (!empty($detail['vote_breakdown']['missing_dbs']) && is_array($detail['vote_breakdown']['missing_dbs'])) {
                foreach ($detail['vote_breakdown']['missing_dbs'] as $missingDb) {
                    $node = $this->mapDbNameToNodeName($missingDb);
                    if (isset($anomaliesByNode[$node]) && !in_array($node, $counted)) {
                        $anomaliesByNode[$node]++;
                        $counted[] = $node;
                    }
                }
            }
        }

        return $anomaliesByNode;
    }

    /**
     * Map database names to standardized node identifiers
     */
    private function mapDbNameToNodeName(string $dbName): string
    {
        return match($dbName) {
            'userdb', 'blockchain' => 'userdb',
            'admindb', 'blockchain_backup', 'admin' => 'admindb',
            'konsensus', 'consensus', 'konsensusdb' => 'konsensus',
            default => 'userdb' // default fallback
        };
    }

    // =========================================================================
    // INTEGRITY CHECK: Validasi Dua Lapis (Re-Hash + Consensus 2/3)
    // =========================================================================

    /**
     * Halaman hasil Integrity Check (GET)
     * Menampilkan hasil pengecekan dua lapis terakhir yang tersimpan di session.
     */
    public function integrityCheck()
    {
        $data = [
            'title'   => 'Integrity Check — Validasi Dua Lapis',
            'report'  => $this->session->getFlashdata('integrity_report'),
        ];

        return view('admin/integrity/index', $data);
    }

    /**
     * Jalankan Integrity Check secara manual (POST)
     * Memanggil IntegrityCheckService::runFullIntegrityCheck() dan redirect ke halaman hasil.
     */
    public function runIntegrityCheck()
    {
        try {
            $integrityService = new \App\Libraries\IntegrityCheckService();

            // Jalankan pengecekan dua lapis
            $adminUsername = session()->get('username') ?? 'admin';
            $report = $integrityService->runFullIntegrityCheck($adminUsername);

            // Simpan hasil ke flashdata untuk ditampilkan di halaman
            $this->session->setFlashdata('integrity_report', $report);

            // Tentukan pesan berdasarkan hasil
            if ($report['total_quarantined'] > 0) {
                $this->session->setFlashdata('warning', sprintf(
                    '⛔ %d dokumen dikarantina (Split Brain/Anomali Kritikal). Investigasi manual diperlukan.',
                    $report['total_quarantined']
                ));
            } elseif ($report['total_recovered'] > 0) {
                $this->session->setFlashdata('success', sprintf(
                    '✓ %d dokumen berhasil dipulihkan dari %d total yang diperiksa.',
                    $report['total_recovered'],
                    $report['total_checked']
                ));
            } else {
                $this->session->setFlashdata('success', sprintf(
                    '✓ Semua %d dokumen dalam kondisi sehat. Tidak ada anomali terdeteksi.',
                    $report['total_checked']
                ));
            }

            return redirect()->to('/admin/integrity/check');
        } catch (\Exception $e) {
            log_message('error', '[INTEGRITY_CHECK] Error: ' . $e->getMessage());
            $this->session->setFlashdata('error', 'Gagal menjalankan integrity check: ' . $e->getMessage());
            return redirect()->to('/admin/integrity/check');
        }
    }
}
