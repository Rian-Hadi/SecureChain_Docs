<?php

namespace App\Controllers;

use App\Models\BlockModel;
use App\Models\BackupModel;
use App\Models\ActivityLogModel;
use App\Models\UploadHistoryModel;
use App\Libraries\BlockHash;

class Document extends BaseController
{
    // =========================================================================
    // SECURITY: File Whitelist — single source of truth untuk semua lapisan
    // =========================================================================

    /** Ekstensi yang diizinkan (lowercase) */
    private const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'jpg', 'png'];

    /** MIME type yang diizinkan, dipetakan dari ekstensi */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg',
        'image/png',
    ];

    /**
     * Magic bytes (hex prefix) yang valid.
     * Setiap entry: [ hex_prefix, label ]
     * DOCX adalah ZIP-based Office Open XML — kita lakukan pengecekan lanjutan
     * di dalam ZIP untuk memastikan ia benar-benar DOCX bukan ZIP biasa.
     */
    private const MAGIC_SIGNATURES = [
        ['prefix' => '25504446', 'label' => 'PDF'],   // %PDF
        ['prefix' => '504b0304', 'label' => 'DOCX'],  // PK.. (ZIP/Office Open XML)
        ['prefix' => '89504e47', 'label' => 'PNG'],   // .PNG
        ['prefix' => 'ffd8ff',   'label' => 'JPEG'],  // JPEG semua varian
    ];

    /** Ukuran maksimum file dalam KB (5 MB) */
    private const MAX_SIZE_KB = 5120;

    protected $blockModel;
    protected $backupModel;
    protected $activityLogModel;
    protected $uploadHistoryModel;
    protected $cache;
    protected $session;
    protected $db;
    protected $adminDb;
    protected $konsensusDb;
    protected $config;

    public function __construct()
    {
        $this->blockModel         = model(BlockModel::class);
        $this->backupModel        = model(BackupModel::class);
        $this->activityLogModel   = model(ActivityLogModel::class);
        $this->uploadHistoryModel = model(UploadHistoryModel::class);
        $this->cache            = \Config\Services::cache();
        $this->session          = \Config\Services::session();
        $this->db               = \Config\Database::connect('userdb');
        $this->adminDb          = \Config\Database::connect('admindb');
        $this->konsensusDb      = \Config\Database::connect('konsensus');
        $this->config           = new \Config\Recovery();
    }

    // =========================================================================
    // PUBLIC METHODS
    // =========================================================================

    public function index()
    {
        // Halaman dashboard utama (welcome page)
        return view('user/dashboard/index');
    }

    public function upload()
    {
        // Halaman form upload dokumen
        $userDivisi = session()->get('divisi') ?? 'Paten';
        return view('user/upload/index', ['userDivisi' => $userDivisi]);
    }

    public function dokumen()
    {
        // Throttle deteksi: jalankan maks sekali per 5 menit menggunakan cache flag.
        $detectCacheKey = 'detect_recover_last_run';
        if (!$this->cache->get($detectCacheKey)) {
            $this->detectAndRecover();
            $this->cache->save($detectCacheKey, true, 300); // 5 menit
        }

        $keyword = $this->request->getGet('keyword');
        $userDivisi = session()->get('divisi') ?? 'Paten';
        $db      = $this->blockModel;

        if ($keyword) {
            $documents = $db->search($keyword, $userDivisi);
        } else {
            $documents = $db->getByDivision($userDivisi);
        }

        $data = [
            'documents'  => $documents,
            'pager'      => $db->pager,
            'validation' => \Config\Services::validation(),
            'userDivisi' => $userDivisi,
        ];

        return view('user/dokumen/index', $data);
    }

    public function riwayat()
    {
        $userId = session()->get('user_id');
        if (!$userId) {
            return redirect()->to('/auth/login');
        }

        $keyword = $this->request->getGet('keyword');
        $tanggal = $this->request->getGet('tanggal') ?: date('Y-m-d'); // Default to today

        if ($keyword) {
            $histories = $this->uploadHistoryModel->searchByUser($userId, $keyword, $tanggal);
        } else {
            $histories = $this->uploadHistoryModel->getByUser($userId, $tanggal);
        }

        $data = [
            'histories'    => $histories,
            'pager'        => $this->uploadHistoryModel->pager,
            'totalUpload'  => $this->uploadHistoryModel->countByUser($userId, $tanggal),
            'totalSuccess' => $this->uploadHistoryModel->countSuccessByUser($userId, $tanggal),
            'totalFailed'  => $this->uploadHistoryModel->countFailedByUser($userId, $tanggal),
            'selectedDate' => $tanggal,
        ];

        return view('user/riwayat/index', $data);
    }

    public function create()
    {
        $today = date('Y-m-d');
        $userDivisi = session()->get('divisi') ?? 'Paten';
        $allowedKategori = ['Paten', 'Merek', 'Hak Cipta', 'Desain Industri'];

        $allowedExtStr = implode(',', self::ALLOWED_EXTENSIONS);
        $allowedExtLabel = strtoupper(implode(', ', self::ALLOWED_EXTENSIONS));

        $rules = [
            'nama_dokumen'     => 'required|string|max_length[255]',
            'nomor_permohonan' => 'required|string|max_length[100]',
            'nomor_dokumen'    => 'required|string|max_length[100]',
            'tanggal_dokumen'  => [
                'rules'  => "required|valid_date|date_not_after[{$today}]",
                'errors' => [
                    'date_not_after' => 'Tanggal dokumen tidak boleh melebihi hari ini.',
                ],
            ],
            'tanggal_filing'   => [
                'rules'  => "required|valid_date|date_not_after[{$today}]",
                'errors' => [
                    'date_not_after' => 'Tanggal filing tidak boleh melebihi hari ini.',
                ],
            ],
            'kategori_dokumen' => 'required|in_list[' . implode(',', $allowedKategori) . ']',
            'dokumen'          => [
                'rules'  => "uploaded[dokumen]|max_size[dokumen," . self::MAX_SIZE_KB . "]|ext_in[dokumen,{$allowedExtStr}]",
                'errors' => [
                    'uploaded'  => 'File dokumen wajib diunggah.',
                    'max_size'  => 'Ukuran file tidak boleh melebihi ' . (self::MAX_SIZE_KB / 1024) . ' MB.',
                    'ext_in'    => "Tipe file tidak diizinkan. Hanya {$allowedExtLabel} yang diterima.",
                ],
            ],
        ];

        if (!$this->validate($rules)) {
            return redirect()->to('/upload')
                ->withInput();
        }

        // Check division restriction
        $kategoriDokumen = $this->request->getPost('kategori_dokumen');
        if ($userDivisi !== 'Admin' && $kategoriDokumen !== $userDivisi) {
            return redirect()->to('/upload')
                ->with('error', 'Anda hanya dapat mengunggah dokumen kategori ' . $userDivisi);
        }

        try {
            $file = $this->request->getFile('dokumen');

            // FIX: Validate file before processing
            if (!$file || !$file->isValid()) {
                return redirect()->to('/upload')
                    ->withInput()
                    ->with('error', 'File tidak valid atau gagal terupload. Pastikan ukuran file tidak melebihi 5MB.');
            }

            $this->validateFileMimeType($file);
            $this->validateFileMagicBytes($file);
            $this->sanitizeFilename($file->getClientName());

            $postData = [
                'nama_dokumen'     => htmlspecialchars($this->request->getPost('nama_dokumen'), ENT_QUOTES, 'UTF-8'),
                'nomor_permohonan' => htmlspecialchars($this->request->getPost('nomor_permohonan'), ENT_QUOTES, 'UTF-8'),
                'nomor_dokumen'    => htmlspecialchars($this->request->getPost('nomor_dokumen'), ENT_QUOTES, 'UTF-8'),
                'tanggal_dokumen'  => $this->request->getPost('tanggal_dokumen'),
                'tanggal_filing'   => $this->request->getPost('tanggal_filing'),
                'kategori_dokumen' => $kategoriDokumen,
            ];

            $this->createNewBlock($postData, $file);

            // Simpan riwayat upload (sukses)
            $this->saveUploadHistory($postData, $file, 'success');

            return redirect()->to('/dokumen')->with('success', 'Dokumen berhasil diamankan dalam blockchain!');
        } catch (\Exception $e) {
            log_message('error', '[BLOCKCHAIN_ERROR] Gagal membuat blok: ' . $e->getMessage());
            log_message('error', '[BLOCKCHAIN_ERROR] Stack trace: ' . $e->getTraceAsString());
            
            // FIX: Show detailed error to user (but sanitized)
            $errorMsg = 'Gagal menyimpan dokumen: ' . $e->getMessage();
            if (strpos($e->getMessage(), 'SQLSTATE') !== false) {
                $errorMsg = 'Terjadi kesalahan database. Mohon hubungi administrator.';
            }
            
            // Simpan riwayat upload (gagal)
            $this->saveUploadHistory(
                $postData ?? [],
                $file ?? null,
                'failed',
                $errorMsg
            );

            return redirect()->to('/upload')
                ->withInput()
                ->with('error', $errorMsg);
        }
    }

    public function download(string $block_hash)
    {
        // Validasi format hash (SHA-256 = 64 hex chars) untuk mencegah injeksi.
        if (!preg_match('/^[a-f0-9]{64}$/i', $block_hash)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Hash tidak valid.');
        }

        $block = $this->blockModel->getBlockByHash($block_hash);

        if (!$block) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Dokumen tidak ditemukan untuk hash tersebut.');
        }

        $fileData = base64_decode($block['dokumen_base64'], true);
        if ($fileData === false) {
            throw new \RuntimeException('Data dokumen korup, tidak dapat didekode.');
        }

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $fileData);
        finfo_close($finfo);

        $allowedMimes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ];

        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new \RuntimeException('Tipe file tidak diizinkan untuk diunduh.');
        }

        $extension = $this->getExtensionFromMime($mimeType);
        $fileName  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $block['nomor_permohonan'])
            . '_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $block['nomor_dokumen'])
            . '.' . $extension;

        return $this->response
            ->setBody($fileData)
            ->setContentType($mimeType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->send();
    }

    // =========================================================================
    // SECURITY: File Validation
    // =========================================================================

    private function validateFileMimeType(\CodeIgniter\HTTP\Files\UploadedFile $file): void
    {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file->getTempName());
        finfo_close($finfo);

        if (!in_array($mimeType, self::ALLOWED_MIMES, true)) {
            log_message('warning', "[FILE_SECURITY] MIME type ditolak: {$mimeType} — IP: " . $this->request->getIPAddress());
            throw new \Exception(
                'Tipe file tidak diizinkan. Hanya ' . strtoupper(implode(', ', self::ALLOWED_EXTENSIONS)) . ' yang diterima.'
            );
        }
    }

    private function validateFileMagicBytes(\CodeIgniter\HTTP\Files\UploadedFile $file): void
    {
        $handle = fopen($file->getTempName(), 'rb');
        if (!$handle) {
            throw new \Exception('Tidak dapat membaca file untuk validasi keamanan.');
        }

        $bytes = fread($handle, 12);
        fclose($handle);

        $hex = bin2hex($bytes);

        // Cek apakah hex cocok dengan salah satu magic signature yang diizinkan
        $matchedLabel = null;
        foreach (self::MAGIC_SIGNATURES as $sig) {
            if (strpos($hex, $sig['prefix']) === 0) {
                $matchedLabel = $sig['label'];
                break;
            }
        }

        if ($matchedLabel === null) {
            log_message('warning', "[FILE_SECURITY] Magic bytes tidak valid. Hex: {$hex} — IP: " . $this->request->getIPAddress());
            throw new \Exception('File rusak atau bukan tipe file yang diizinkan. Silakan unggah ulang.');
        }

        // Validasi tambahan untuk DOCX: pastikan ZIP berisi struktur Office Open XML
        // (mencegah file ZIP biasa yang lolos karena magic bytes sama)
        if ($matchedLabel === 'DOCX') {
            $this->validateDocxStructure($file);
        }

        log_message('info', "[FILE_SECURITY] File valid ({$matchedLabel}) — IP: " . $this->request->getIPAddress());
    }

    /**
     * Validasi bahwa file DOCX benar-benar Office Open XML, bukan ZIP biasa.
     * DOCX harus mengandung entry [Content_Types].xml di dalam arsip ZIP-nya.
     */
    private function validateDocxStructure(\CodeIgniter\HTTP\Files\UploadedFile $file): void
    {
        // Baca sebagian isi file untuk mencari string "[Content_Types].xml"
        // yang wajib ada di setiap file Office Open XML (DOCX/XLSX/PPTX)
        $handle = fopen($file->getTempName(), 'rb');
        if (!$handle) {
            throw new \Exception('Tidak dapat memvalidasi struktur file DOCX.');
        }

        // Baca 2 KB pertama — cukup untuk menemukan Central Directory header ZIP
        $chunk = fread($handle, 2048);
        fclose($handle);

        if (strpos($chunk, '[Content_Types].xml') === false) {
            log_message('warning', "[FILE_SECURITY] File ZIP bukan DOCX (tidak ada [Content_Types].xml) — IP: " . $this->request->getIPAddress());
            throw new \Exception('File yang diunggah bukan dokumen DOCX yang valid.');
        }
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = str_replace(['../', '..\\', './', '.\\', "\0"], '', $filename);
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '', $filename);
        $filename = substr($filename, 0, 255);

        return $filename ?: 'document_' . time();
    }

    // =========================================================================
    // BLOCKCHAIN: Core Logic
    // =========================================================================

    private function calculateBlockHash(array $data): string
    {
        return BlockHash::calculate($data);
    }

    private function extractSyncData(array $block): array
    {
        return BlockHash::extractSyncData($block);
    }

    private function createNewBlock(array $postData, \CodeIgniter\HTTP\Files\UploadedFile $file): void
    {
        try {
            $dokumenBase64 = base64_encode(file_get_contents($file->getTempName()));

            $cacheKey   = 'latest_block';
            $latestBlock = $this->cache->get($cacheKey);
            if (!$latestBlock) {
                $latestBlock = $this->blockModel->getLatestBlock();
                $this->cache->save($cacheKey, $latestBlock, 60);
            }

            $previousHash = $latestBlock ? $latestBlock['block_hash'] : '0';

            $newData = array_merge($postData, ['dokumen_base64' => $dokumenBase64]);

            // FIX: gunakan calculateBlockHash agar konsisten dengan detectAndRecover
            $newBlockHash = $this->calculateBlockHash($newData);

            // FIX: Ensure timestamp is explicitly set with full datetime format (including hours and minutes)
            // This prevents the timestamp from defaulting to 00:00:00
            $saveData = array_merge($newData, [
                'ip_address'    => $this->request->getIPAddress(),
                'block_hash'    => $newBlockHash,
                'previous_hash' => $previousHash,
                'timestamp'     => date('Y-m-d H:i:s'),  // Explicitly set timestamp with full datetime
            ]);

            // FIX: Add defensive check - remove kategori_dokumen if table doesn't have the column yet
            $testSave = $saveData;
            
            // Try to insert - if it fails due to unknown column, try without kategori_dokumen
            try {
                $this->blockModel->save($testSave);
                if ($this->config->verboseLogging) {
                    log_message('info', '[BLOCKCHAIN] Block saved successfully with kategori_dokumen');
                }
            } catch (\Exception $dbError) {
                if (strpos($dbError->getMessage(), 'Unknown column') !== false && strpos($dbError->getMessage(), 'kategori_dokumen') !== false) {
                    log_message('warning', '[BLOCKCHAIN] kategori_dokumen column does not exist yet, trying without it...');
                    unset($testSave['kategori_dokumen']);
                    $this->blockModel->save($testSave);
                    
                    // Still sync to other DBs with kategori_dokumen for future compatibility
                } else {
                    throw $dbError;
                }
            }

            // Sinkron ke konsensus — TANPA kolom 'id'
            $syncData = $this->extractSyncData($saveData);
            try {
                $this->konsensusDb->table('konsensus')->insert($syncData);
                if ($this->config->verboseLogging) {
                    log_message('info', '[BLOCKCHAIN] Block synced to konsensus successfully');
                }
            } catch (\Exception $syncError) {
                // If sync fails due to kategori_dokumen, try without it
                if (strpos($syncError->getMessage(), 'Unknown column') !== false && strpos($syncError->getMessage(), 'kategori_dokumen') !== false) {
                    log_message('warning', '[BLOCKCHAIN] kategori_dokumen not in konsensus table yet, trying without it...');
                    unset($syncData['kategori_dokumen']);
                    $this->konsensusDb->table('konsensus')->insert($syncData);
                } else {
                    throw $syncError;
                }
            }

            \CodeIgniter\Events\Events::trigger('afterInsertBlock', $saveData);

            $this->cache->delete($cacheKey);
            $this->cache->clean();
            
            if ($this->config->verboseLogging) {
                log_message('info', '[BLOCKCHAIN] New block created successfully: ' . $saveData['block_hash']);
            }
        } catch (\Exception $e) {
            log_message('error', '[BLOCKCHAIN_CRITICAL] createNewBlock failed: ' . $e->getMessage());
            log_message('error', '[BLOCKCHAIN_CRITICAL] Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Simpan riwayat upload ke tabel upload_history
     */
    private function saveUploadHistory(array $postData, $file, string $status, string $keterangan = null): void
    {
        try {
            $userId   = session()->get('user_id');
            $username = session()->get('username');

            if (!$userId) {
                return; // skip jika tidak ada session user
            }

            // Ambil block_hash terakhir (jika sukses, block baru saja dibuat)
            $blockHash = null;
            if ($status === 'success') {
                $latestBlock = $this->blockModel->getLatestBlock();
                $blockHash = $latestBlock['block_hash'] ?? null;
            }

            $fileType = null;
            $fileSize = null;
            if ($file instanceof \CodeIgniter\HTTP\Files\UploadedFile && $file->isValid()) {
                $ext = $file->getClientExtension();
                $fileType = strtoupper($ext);
                $fileSize = $file->getSize();
            }

            $historyData = [
                'user_id'          => $userId,
                'username'         => $username ?? 'unknown',
                'nama_dokumen'     => $postData['nama_dokumen'] ?? '-',
                'nomor_permohonan' => $postData['nomor_permohonan'] ?? '-',
                'nomor_dokumen'    => $postData['nomor_dokumen'] ?? '-',
                'kategori_dokumen' => $postData['kategori_dokumen'] ?? 'Paten',
                'tanggal_dokumen'  => $postData['tanggal_dokumen'] ?? date('Y-m-d'),
                'tanggal_filing'   => $postData['tanggal_filing'] ?? date('Y-m-d'),
                'block_hash'       => $blockHash,
                'file_type'        => $fileType,
                'file_size'        => $fileSize,
                'ip_address'       => $this->request->getIPAddress(),
                'status'           => $status,
                'keterangan'       => $keterangan,
                'uploaded_at'      => date('Y-m-d H:i:s'),
            ];

            $this->uploadHistoryModel->saveHistory($historyData);
        } catch (\Exception $e) {
            // Jangan gagalkan upload utama hanya karena logging history gagal
            log_message('error', '[UPLOAD_HISTORY] Gagal menyimpan riwayat: ' . $e->getMessage());
        }
    }

    private function getExtensionFromMime(string $mimeType): string
    {
        return [
            'application/pdf'  => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
        ][$mimeType] ?? 'bin';
    }

    // =========================================================================
    // INTEGRITY: Detect & Recover
    // =========================================================================

    private function detectAndRecover(): void
    {
        try {
            if (!$this->adminDb->tableExists('blockchain_backup')) {
                log_message('warning', '[SYNC] Tabel admindb tidak ada, skip');
                return;
            }
            if (!$this->konsensusDb->tableExists('konsensus')) {
                log_message('warning', '[SYNC] Tabel konsensus tidak ada, skip');
                return;
            }
        } catch (\Throwable $e) {
            log_message('error', '[SYNC] Gagal cek tabel: ' . $e->getMessage());
            return;
        }

        $allBlocks      = $this->blockModel->getAllBlocks();
        $manipulatedCount = 0;
        $recoveredCount   = 0;

        foreach ($allBlocks as $block) {
            // FIX: gunakan calculateBlockHash — sekarang konsisten dengan createNewBlock
            $recalculatedHash = $this->calculateBlockHash($block);

            if ($recalculatedHash === $block['block_hash']) {
                continue; // blok valid, tidak perlu recovery
            }

            $manipulatedCount++;
            log_message('error', "[MANIPULASI] Blok ID {$block['id']} — Stored: {$block['block_hash']}, Calc: {$recalculatedHash}");

            // Cari sumber recovery
            $recoverySource = null;
            $recoveryData   = null;

            $consensusRecord = $this->konsensusDb->table('konsensus')
                ->where('block_hash', $block['block_hash'])
                ->get()->getRowArray();

            if ($consensusRecord) {
                $recoverySource = 'konsensus';
                $recoveryData   = $consensusRecord;
            } else {
                $adminBackup = $this->backupModel->getBackupByHash($block['block_hash']);
                if ($adminBackup) {
                    $recoverySource = 'admin';
                    $recoveryData   = $adminBackup;
                } else {
                    $adminBackup = $this->backupModel->getBackupByIdentifier(
                        $block['nomor_permohonan'],
                        $block['tanggal_dokumen']
                    );
                    if ($adminBackup) {
                        $recoverySource = 'admin_fallback';
                        $recoveryData   = $adminBackup;
                    }
                }
            }

            if (!$recoveryData) {
                log_message('warning', "[RECOVERY] Tidak ada data valid untuk Blok ID {$block['id']}");
                continue;
            }

            $correctHash = $this->calculateBlockHash($recoveryData);

            $updateData = [
                'nama_dokumen'     => $recoveryData['nama_dokumen'],
                'nomor_permohonan' => $recoveryData['nomor_permohonan'],
                'nomor_dokumen'    => $recoveryData['nomor_dokumen'],
                'tanggal_dokumen'  => $recoveryData['tanggal_dokumen'],
                'tanggal_filing'   => $recoveryData['tanggal_filing'],
                'kategori_dokumen' => $recoveryData['kategori_dokumen'] ?? $block['kategori_dokumen'] ?? 'Paten',
                'dokumen_base64'   => $recoveryData['dokumen_base64'],
                'ip_address'       => $recoveryData['ip_address'] ?? $block['ip_address'],
                'block_hash'       => $correctHash,
                'previous_hash'    => $recoveryData['previous_hash'] ?? $block['previous_hash'],
            ];

            if ($this->blockModel->update($block['id'], $updateData)) {
                $recoveredCount++;
                log_message('info', "[RECOVERY] Blok ID {$block['id']} dipulihkan dari {$recoverySource}");

                $this->activityLogModel->logActivity([
                    'action_type'   => 'RECOVER',
                    'block_id'      => $block['id'],
                    'identifier'    => $recoveryData['nomor_permohonan'],
                    'status'        => 'Recovered',
                    'description'   => "Data dipulihkan dari {$recoverySource}",
                    'original_data' => ['hash' => $block['block_hash']],
                    'modified_data' => ['hash' => $correctHash],
                ]);
            }
        }

        $this->syncValidBlocksToBackups($allBlocks);

        if ($manipulatedCount > 0) {
            $message = "<strong>🔍 Deteksi Manipulasi</strong><br>";
            $message .= $recoveredCount > 0
                ? "<span class='text-green-700'>✓ {$recoveredCount} blok berhasil dipulihkan</span>"
                : "<span class='text-red-700'>❌ Tidak ada backup valid untuk pemulihan</span>";
            $this->session->setFlashdata('warning', $message);
        }
    }

    private function syncValidBlocksToBackups(array $allBlocks): void
    {
        foreach ($allBlocks as $block) {
            // Hanya sinkronkan blok yang hashnya valid
            if ($this->calculateBlockHash($block) !== $block['block_hash']) {
                continue;
            }

            // FIX: extractSyncData membuang kolom 'id' sebelum insert ke konsensus
            $syncData = $this->extractSyncData($block);

            $existsInKonsensus = $this->konsensusDb->table('konsensus')
                ->where('block_hash', $block['block_hash'])
                ->countAllResults() > 0;

            if (!$existsInKonsensus) {
                $this->konsensusDb->table('konsensus')->insert($syncData);
                log_message('info', "[SYNC] Tambah ke konsensus: {$block['block_hash']}");
            }

            $adminBackup = $this->backupModel->getBackupByIdentifier(
                $block['nomor_permohonan'],
                $block['tanggal_dokumen']
            );

            if (!$adminBackup) {
                $this->backupModel->createBackup($block, 'auto_sync');
                log_message('info', "[SYNC] Tambah ke admin backup: {$block['nomor_permohonan']}");
                continue;
            }

            $syncPayload = BlockHash::extractSyncData($block);
            $needsUpdate = false;
            foreach (BlockHash::CONSENSUS_COMPARE_FIELDS as $field) {
                if (($adminBackup[$field] ?? null) !== ($block[$field] ?? null)) {
                    $needsUpdate = true;
                    break;
                }
            }

            if ($needsUpdate) {
                $this->backupModel->update($adminBackup['id'], $syncPayload);
                log_message('info', "[SYNC] Perbarui admin backup: {$block['nomor_permohonan']}");
            }
        }
    }
}
