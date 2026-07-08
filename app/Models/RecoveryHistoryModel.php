<?php

namespace App\Models;

use CodeIgniter\Model;

class RecoveryHistoryModel extends Model
{
    protected $DBGroup          = 'admindb';
    protected $table            = 'recovery_history';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';

    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    protected $allowedFields = [
        'recovery_type',
        'source_db',
        'target_db',
        'table_name',
        'record_key',
        'before_checksum',
        'after_checksum',
        'before_data',
        'after_data',
        'consensus_result',
        'status',
        'error_message',
        'performed_by',
        'ip_address'
    ];

    public function logRecovery(array $data): bool
    {
        $logData = [
            'recovery_type'     => $data['recovery_type'] ?? 'consensus_auto',
            'source_db'         => $data['source_db'] ?? null,
            'target_db'         => $data['target_db'] ?? null,
            'table_name'        => $data['table_name'] ?? 'blockchain',
            'record_key'        => $data['record_key'] ?? null,
            'before_checksum'   => $data['before_checksum'] ?? null,
            'after_checksum'    => $data['after_checksum'] ?? null,
            'before_data'       => isset($data['before_data']) ? json_encode($data['before_data']) : null,
            'after_data'        => isset($data['after_data']) ? json_encode($data['after_data']) : null,
            'consensus_result'  => isset($data['consensus_result']) ? json_encode($data['consensus_result']) : null,
            'status'            => $data['status'] ?? 'success',
            'error_message'     => $data['error_message'] ?? null,
            'performed_by'      => $data['performed_by'] ?? 'system',
            'ip_address'        => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        $result = $this->insert($logData);

        if ($result) {
            $insertedId = $this->getInsertID();
            log_message('info', "[RECOVERY_HISTORY] ✓ Logged recovery #{$insertedId} - Type: {$logData['recovery_type']}, Status: {$logData['status']}, Source: {$logData['source_db']} → Target: {$logData['target_db']}");
        } else {
            log_message('error', "[RECOVERY_HISTORY] ✗ Failed to log recovery - Target: {$logData['target_db']}");
        }

        return $result;
    }

    public function getHistory(int $limit = 50, array $filters = []): array
    {
        $builder = $this;

        if (isset($filters['recovery_type'])) {
            $builder = $builder->where('recovery_type', $filters['recovery_type']);
        }

        if (isset($filters['status'])) {
            $builder = $builder->where('status', $filters['status']);
        }

        if (isset($filters['record_key'])) {
            $builder = $builder->where('record_key', $filters['record_key']);
        }

        if (isset($filters['date_from'])) {
            $builder = $builder->where('created_at >=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $builder = $builder->where('created_at <=', $filters['date_to']);
        }

        return $builder->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function getRecoveryById(int $id): ?array
    {
        $record = $this->find($id);

        if ($record) {
            if ($record['before_data']) {
                $record['before_data'] = json_decode($record['before_data'], true);
            }
            if ($record['after_data']) {
                $record['after_data'] = json_decode($record['after_data'], true);
            }
            if ($record['consensus_result']) {
                $record['consensus_result'] = json_decode($record['consensus_result'], true);
            }
        }

        return $record;
    }

    public function getStatistics(): array
    {
        $db = $this->db;
        $table = $this->table;

        $totalRecoveries = $db->table($table)->countAllResults();

        $autoRecoveries = $db->table($table)
            ->where('recovery_type', 'consensus_auto')
            ->countAllResults();

        $manualRecoveries = $db->table($table)
            ->where('recovery_type', 'consensus_manual')
            ->countAllResults();

        $rollbacks = $db->table($table)
            ->where('recovery_type', 'rollback')
            ->countAllResults();

        $successCount = $db->table($table)
            ->where('status', 'success')
            ->countAllResults();

        $failedCount = $db->table($table)
            ->where('status', 'failed')
            ->countAllResults();

        $lastRecovery = $db->table($table)
            ->orderBy('created_at', 'DESC')
            ->get(1)
            ->getRowArray();

        $recoveriesToday = $db->table($table)
            ->where('DATE(created_at)', date('Y-m-d'))
            ->countAllResults();

        $recoveriesThisWeek = $db->table($table)
            ->where('created_at >=', date('Y-m-d', strtotime('-7 days')))
            ->countAllResults();

        return [
            'total_recoveries'      => $totalRecoveries,
            'auto_recoveries'       => $autoRecoveries,
            'manual_recoveries'     => $manualRecoveries,
            'rollbacks'             => $rollbacks,
            'success_count'         => $successCount,
            'failed_count'          => $failedCount,
            'last_recovery'         => $lastRecovery,
            'recoveries_today'      => $recoveriesToday,
            'recoveries_this_week'  => $recoveriesThisWeek,
        ];
    }

    public function cleanOldRecords(int $keepCount = 1000): bool
    {
        $keepIds = $this->select('id')
            ->orderBy('created_at', 'DESC')
            ->limit($keepCount)
            ->findColumn('id');

        if (empty($keepIds)) {
            return true;
        }

        return $this->whereNotIn('id', $keepIds)->delete();
    }

    public function getByRecordKey(string $recordKey, int $limit = 10): array
    {
        return $this->where('record_key', $recordKey)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function markAsRolledBack(int $id): bool
    {
        return $this->update($id, ['status' => 'rolled_back']);
    }
}
