<?php

namespace App\Models;

use CodeIgniter\Model;

class BackupModel extends Model
{
    protected $DBGroup          = 'admindb';  // Use admin database
    protected $table            = 'blockchain_backup';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'backup_timestamp';
    protected $updatedField  = '';

    protected $allowedFields    = [
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'kategori_dokumen',
        'dokumen_base64',
        'ip_address',
        'block_hash',
        'previous_hash',
        'timestamp',
        'backup_type'
    ];

    public function createBackup(array $blockData, string $backupType = 'auto'): bool
    {
        $backupData = array_merge($blockData, [
            'backup_type' => $backupType
        ]);
        // Prefer using block_hash as unique key when available
        $blockHash = $backupData['block_hash'] ?? null;
        $nomor = $backupData['nomor_permohonan'] ?? null;
        $tanggal = $backupData['tanggal_dokumen'] ?? null;

        $dbGroups = ['admindb', 'userdb', 'konsensus'];
        foreach ($dbGroups as $group) {
            try {
                $otherDb = \Config\Database::connect($group);
            } catch (\Exception $e) {
                log_message('warning', "[BACKUP-CHECK] Cannot connect to DB group {$group}: " . $e->getMessage());
                continue;
            }

            if (! $otherDb->tableExists('blockchain_backup')) {
                continue;
            }

            $builder = $otherDb->table('blockchain_backup');

            if ($blockHash) {
                $found = $builder->where('block_hash', $blockHash)->get()->getRowArray();
                if ($found) {
                    log_message('info', "[BACKUP-CHECK] Backup already exists in {$group} for hash: {$blockHash}");
                    return false;
                }
            }

            if ($nomor && $tanggal) {
                $dateOnly = date('Y-m-d', strtotime($tanggal));
                $found = $builder->where('nomor_permohonan', $nomor)
                    ->where("DATE(tanggal_dokumen)", $dateOnly)
                    ->get()
                    ->getRowArray();
                if ($found) {
                    log_message('info', "[BACKUP-CHECK] Backup already exists in {$group} for nomor: {$nomor} date: {$dateOnly}");
                    return false;
                }
            }
        }

        try {
            $insertId = $this->insert($backupData);
            if ($insertId === false) {
                log_message('error', '[BACKUP] Insert failed: ' . json_encode($this->errors()));
                return false;
            }
            log_message('info', "[BACKUP] Created backup (type: {$backupType}) for nomor: {$nomor} hash: {$blockHash}");
            return true;
        } catch (\Exception $e) {
            log_message('error', '[BACKUP] Exception during insert: ' . $e->getMessage());
            return false;
        }
    }

    public function getBackupByIdentifier(string $nomorPermohonan, string $tanggalDokumen): ?array
    {
        $tanggalDokumen = date('Y-m-d', strtotime($tanggalDokumen));

        log_message('debug', "[BACKUP-SEARCH] Mencari backup untuk Nomor: {$nomorPermohonan}, Tanggal: {$tanggalDokumen}");

        $result = $this->where('nomor_permohonan', $nomorPermohonan)
            ->where("DATE(tanggal_dokumen)", $tanggalDokumen)
            ->orderBy('backup_timestamp', 'DESC')
            ->first();

        if ($result) {
            log_message('debug', "[BACKUP-SEARCH] Backup ditemukan! ID: {$result['id']}");
        } else {
            log_message('warning', "[BACKUP-SEARCH] Backup TIDAK ditemukan untuk Nomor: {$nomorPermohonan}, Tanggal: {$tanggalDokumen}");
            $allBackups = $this->where('nomor_permohonan', $nomorPermohonan)->findAll();
            if (!empty($allBackups)) {
                log_message('warning', "[BACKUP-SEARCH] Ditemukan " . count($allBackups) . " backup dengan nomor permohonan yang sama tapi tanggal berbeda:");
                foreach ($allBackups as $b) {
                    log_message('warning', "[BACKUP-SEARCH] - ID: {$b['id']}, Tanggal: {$b['tanggal_dokumen']}");
                }
            }
        }

        return $result;
    }

    public function getAllBackups(): array
    {
        return $this->orderBy('backup_timestamp', 'DESC')->findAll();
    }

    public function countBackups(): int
    {
        return $this->countAllResults();
    }

    public function getLatestBackups(int $limit = 10): array
    {
        return $this->orderBy('backup_timestamp', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function getBackupByHash(string $blockHash): ?array
    {
        log_message('debug', "[BACKUP-SEARCH] Mencari backup berdasarkan hash: {$blockHash}");

        $result = $this->where('block_hash', $blockHash)
            ->orderBy('backup_timestamp', 'DESC')
            ->first();

        if ($result) {
            log_message('debug', "[BACKUP-SEARCH] Backup ditemukan berdasarkan hash! ID: {$result['id']}, Nomor: {$result['nomor_permohonan']}");
        } else {
            log_message('warning', "[BACKUP-SEARCH] Backup TIDAK ditemukan untuk hash: {$blockHash}");
        }

        return $result;
    }

    public function getBackupByBlockId(int $blockId): ?array
    {
        return $this->where('id', $blockId)
            ->orderBy('backup_timestamp', 'DESC')
            ->first();
    }
}
