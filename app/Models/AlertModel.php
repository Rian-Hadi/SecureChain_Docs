<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Alert Model
 * 
 * Model untuk menyimpan alert history
 * Digunakan untuk audit trail dan dashboard reporting
 */
class AlertModel extends Model
{
    protected $table = 'alerts';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'alert_type',
        'severity',
        'title',
        'message',
        'affected_blocks_count',
        'affected_block_samples',
        'telegram_message_id',
        'telegram_sent',
        'status',
        'remarks',
        'created_at'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = null;

    protected $validationRules = [
        'alert_type' => 'required|string|in_list[CORRUPTION_DETECTED,RECOVERY_STARTED,RECOVERY_SUCCESS,RECOVERY_FAILED,MANUAL_REVIEW,SYSTEM_STATUS]',
        'severity' => 'required|string|in_list[INFO,WARNING,CRITICAL,SUCCESS]',
        'title' => 'required|string|max_length[255]',
        'message' => 'required|string',
        'affected_blocks_count' => 'permit_empty|integer',
        'telegram_sent' => 'permit_empty|in_list[0,1]',
        'status' => 'required|string|in_list[PENDING,SENT,FAILED,ACKNOWLEDGED]'
    ];

    /**
     * Log alert ke database
     * 
     * @param string $type Alert type (CORRUPTION_DETECTED, etc)
     * @param string $severity Severity level
     * @param string $title
     * @param string $message
     * @param array $data Additional data
     * @return int|bool Alert ID atau false
     */
    public function logAlert(
        string $type,
        string $severity,
        string $title,
        string $message,
        array $data = []
    ) {
        $insertData = [
            'alert_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'affected_blocks_count' => $data['affected_blocks_count'] ?? null,
            'affected_block_samples' => !empty($data['affected_blocks']) ? 
                json_encode(array_slice($data['affected_blocks'], 0, 5)) : null,
            'telegram_message_id' => $data['telegram_message_id'] ?? null,
            'telegram_sent' => $data['telegram_sent'] ?? false,
            'status' => $data['status'] ?? 'PENDING'
        ];

        return $this->insert($insertData);
    }

    /**
     * Get recent alerts
     * 
     * @param int $limit
     * @return array
     */
    public function getRecent(int $limit = 50): array
    {
        return $this->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->find();
    }

    /**
     * Get alerts by type
     * 
     * @param string $type
     * @param int $limit
     * @return array
     */
    public function getByType(string $type, int $limit = 50): array
    {
        return $this->where('alert_type', $type)
                   ->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->find();
    }

    /**
     * Get alerts by severity
     * 
     * @param string $severity
     * @param int $limit
     * @return array
     */
    public function getBySeverity(string $severity, int $limit = 50): array
    {
        return $this->where('severity', $severity)
                   ->orderBy('created_at', 'DESC')
                   ->limit($limit)
                   ->find();
    }

    /**
     * Get alerts within time range
     * 
     * @param string $startDate (Y-m-d H:i:s)
     * @param string $endDate (Y-m-d H:i:s)
     * @return array
     */
    public function getByDateRange(string $startDate, string $endDate): array
    {
        return $this->where('created_at >=', $startDate)
                   ->where('created_at <=', $endDate)
                   ->orderBy('created_at', 'DESC')
                   ->find();
    }

    /**
     * Get summary statistics
     * 
     * @param string $period 'today'|'week'|'month'
     * @return array
     */
    public function getSummary(string $period = 'today'): array
    {
        $db = \Config\Database::connect();

        $dateFilter = match ($period) {
            'today' => "DATE(created_at) = CURDATE()",
            'week' => "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => "1=1"
        };

        $result = $db->query(
            "SELECT 
                alert_type,
                severity,
                COUNT(*) as count,
                SUM(CASE WHEN telegram_sent = 1 THEN 1 ELSE 0 END) as telegram_sent
            FROM alerts
            WHERE {$dateFilter}
            GROUP BY alert_type, severity
            ORDER BY count DESC"
        )->getResultArray();

        return $result;
    }

    /**
     * Cleanup old alerts (retention policy)
     * 
     * @param int $retentionDays Keep alerts from last N days
     * @return int Number of deleted records
     */
    public function cleanup(int $retentionDays = 90): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        return $this->where('created_at <', $cutoffDate)->delete();
    }
}
