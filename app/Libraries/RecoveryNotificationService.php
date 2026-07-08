<?php

namespace App\Libraries;

use App\Models\ActivityLogModel;
use Psr\Log\LoggerInterface;

/**
 * Recovery Notification Service
 * 
 * Service untuk orchestrate sending recovery alerts
 * Handles Telegram notifications, logging, dan audit trail
 */
class RecoveryNotificationService
{
    protected TelegramService $telegram;
    protected ActivityLogModel $activityLog;
    protected LoggerInterface $logger;

    // Notification types
    const TYPE_CORRUPTION_DETECTED = 'CORRUPTION_DETECTED';
    const TYPE_RECOVERY_STARTED = 'RECOVERY_STARTED';
    const TYPE_RECOVERY_SUCCESS = 'RECOVERY_SUCCESS';
    const TYPE_RECOVERY_FAILED = 'RECOVERY_FAILED';
    const TYPE_MANUAL_REVIEW = 'MANUAL_REVIEW_REQUIRED';
    const TYPE_SYSTEM_STATUS = 'SYSTEM_STATUS';

    public function __construct()
    {
        $this->telegram = new TelegramService();
        $this->activityLog = model(ActivityLogModel::class);
        $this->logger = service('logger');
    }

    /**
     * Notify corruption detected
     * 
     * @param array $corruptionData [
     *     'conflict_type' => '2-1'|'1-1-1',
     *     'affected_blocks' => array,
     *     'countdown_duration' => int
     * ]
     * @return array ['telegram_sent' => bool, 'activity_logged' => bool]
     */
    public function notifyCorruptionDetected(array $corruptionData): array
    {
        $blockCount = count($corruptionData['affected_blocks'] ?? []);
        $samples = array_slice($corruptionData['affected_blocks'] ?? [], 0, 3);

        $alertData = [
            'conflict_type' => $corruptionData['conflict_type'] ?? '2-1',
            'affected_blocks_count' => $blockCount,
            'block_samples' => $samples,
            'countdown_duration' => $corruptionData['countdown_duration'] ?? 30
        ];

        // Format message
        $telegramMessage = $this->formatCorruptionAlert($alertData);

        // Send via Telegram dengan retry
        $telegramResult = $this->telegram->sendMessageWithRetry($telegramMessage);

        // Log to activity
        $this->activityLog->logActivity([
            'action_type' => 'ALERT_CORRUPTION_DETECTED',
            'status' => $telegramResult['success'] ? 'SUCCESS' : 'FAILED',
            'description' => "Corruption detected: {$blockCount} blocks affected (" . 
                           ($corruptionData['conflict_type'] ?? '2-1') . " voting)",
            'original_data' => [
                'telegram_sent' => $telegramResult['success'],
                'telegram_message_id' => $telegramResult['messageId'],
                'affected_blocks' => $blockCount,
                'alert_data' => $alertData
            ]
        ]);

        return [
            'telegram_sent' => $telegramResult['success'],
            'activity_logged' => true,
            'message_id' => $telegramResult['messageId']
        ];
    }

    /**
     * Notify recovery started
     * 
     * @param array $recoveryData
     * @return array
     */
    public function notifyRecoveryStarted(array $recoveryData): array
    {
        $blockCount = count($recoveryData['blocks'] ?? []);

        $message = "▶️ *RECOVERY STARTED*\n\n";
        $message .= "*Status:* IN_PROGRESS\n";
        $message .= "*Blocks:* {$blockCount}\n";
        $message .= "*Strategy:* Intelligent consensus-based recovery\n\n";
        $message .= "_Recovery in progress..._";

        $telegramResult = $this->telegram->sendMessage($message);

        $this->activityLog->logActivity([
            'action_type' => 'RECOVERY_STARTED',
            'status' => $telegramResult['success'] ? 'INFO' : 'WARNING',
            'description' => "Recovery started for {$blockCount} blocks",
            'original_data' => [
                'telegram_sent' => $telegramResult['success'],
                'block_count' => $blockCount
            ]
        ]);

        return [
            'telegram_sent' => $telegramResult['success'],
            'activity_logged' => true,
            'message_id' => $telegramResult['messageId'] ?? null
        ];
    }

    /**
     * Notify recovery completed successfully
     * 
     * @param array $recoveryResult [
     *     'recovered_count' => int,
     *     'failed_count' => int,
     *     'duration' => float
     * ]
     * @return array
     */
    public function notifyRecoverySuccess(array $recoveryResult): array
    {
        $alertData = [
            'total_recovered' => $recoveryResult['recovered_count'] ?? 0,
            'failed_count' => $recoveryResult['failed_count'] ?? 0,
            'success_rate' => $recoveryResult['recovered_count'] ?? 0 / 
                             max(($recoveryResult['recovered_count'] ?? 0) + ($recoveryResult['failed_count'] ?? 0), 1),
            'duration_seconds' => $recoveryResult['duration'] ?? 0
        ];

        $telegramMessage = $this->formatRecoverySuccessAlert($alertData);

        $telegramResult = $this->telegram->sendMessageWithRetry($telegramMessage);

        $this->activityLog->logActivity([
            'action_type' => 'RECOVERY_SUCCESS',
            'status' => 'SUCCESS',
            'description' => "Recovery completed: " . ($recoveryResult['recovered_count'] ?? 0) . 
                           " blocks recovered, " . ($recoveryResult['failed_count'] ?? 0) . " failed",
            'original_data' => [
                'telegram_sent' => $telegramResult['success'],
                'telegram_message_id' => $telegramResult['messageId'],
                'recovery_result' => $recoveryResult
            ]
        ]);

        return [
            'telegram_sent' => $telegramResult['success'],
            'activity_logged' => true,
            'message_id' => $telegramResult['messageId'] ?? null
        ];
    }

    /**
     * Notify recovery failed
     * 
     * @param array $errorData [
     *     'reason' => string,
     *     'failed_blocks' => int,
     *     'error' => string (optional)
     * ]
     * @return array
     */
    public function notifyRecoveryFailed(array $errorData): array
    {
        $alertData = [
            'reason' => $errorData['reason'] ?? 'Unknown error',
            'failed_count' => $errorData['failed_blocks'] ?? 0,
            'error_message' => $errorData['error'] ?? ''
        ];

        $telegramMessage = $this->formatRecoveryFailedAlert($alertData);

        $telegramResult = $this->telegram->sendMessage($telegramMessage);

        $this->activityLog->logActivity([
            'action_type' => 'RECOVERY_FAILED',
            'status' => 'ERROR',
            'description' => "Recovery failed: " . ($errorData['reason'] ?? 'Unknown'),
            'original_data' => [
                'telegram_sent' => $telegramResult['success'],
                'error_data' => $errorData
            ]
        ]);

        return [
            'telegram_sent' => $telegramResult['success'],
            'activity_logged' => true,
            'message_id' => $telegramResult['messageId'] ?? null
        ];
    }

    /**
     * Notify manual review required
     * 
     * Digunakan untuk 1-1-1 conflicts
     * 
     * @param array $reviewData [
     *     'reason' => string,
     *     'affected_blocks' => array
     * ]
     * @return array
     */
    public function notifyManualReviewRequired(array $reviewData): array
    {
        $alertData = [
            'reason' => $reviewData['reason'] ?? 'Unknown',
            'block_samples' => array_slice($reviewData['affected_blocks'] ?? [], 0, 3)
        ];

        $telegramMessage = $this->formatManualReviewAlert($alertData);

        $telegramResult = $this->telegram->sendMessage($telegramMessage);

        $this->activityLog->logActivity([
            'action_type' => 'ALERT_MANUAL_REVIEW',
            'status' => 'WARNING',
            'description' => "Manual review required: " . ($reviewData['reason'] ?? 'Unknown'),
            'original_data' => [
                'telegram_sent' => $telegramResult['success'],
                'affected_blocks' => count($reviewData['affected_blocks'] ?? [])
            ]
        ]);

        return [
            'telegram_sent' => $telegramResult['success'],
            'activity_logged' => true,
            'message_id' => $telegramResult['messageId'] ?? null
        ];
    }

    /**
     * Get notification service status
     * 
     * @return array
     */
    public function getStatus(): array
    {
        return [
            'telegram_available' => $this->telegram->isAvailable(),
            'telegram_config' => $this->telegram->getConfig()
        ];
    }

    /**
     * Format corruption alert message
     */
    protected function formatCorruptionAlert(array $data): string
    {
        $message = "⚠️ *CORRUPTION DETECTED*\n\n";
        $message .= "*Conflict Type:* {$data['conflict_type']}\n";
        $message .= "*Affected Blocks:* {$data['affected_blocks_count']}\n";
        $message .= "*Countdown:* {$data['countdown_duration']}s\n\n";
        
        if (!empty($data['block_samples'])) {
            $message .= "*Detail Manipulasi (Maks 3 sampel):*\n";
            $message .= "---------------------------------------\n";
            
            foreach (array_slice($data['block_samples'], 0, 3) as $sample) {
                $identifier = $sample['identifier'] ?? $sample['record_key'] ?? 'Unknown';
                $corruptedDbs = implode(', ', $sample['corrupt_dbs'] ?? ['Unknown']);
                
                $message .= "📄 *Data:* `{$identifier}`\n";
                $message .= "🚨 *Dimanipulasi di:* {$corruptedDbs}\n";
                
                if (isset($sample['data']) && is_array($sample['data'])) {
                    $differences = $this->findCorruptedColumns($sample['data']);
                    
                    if (!empty($differences)) {
                        $message .= "*Kolom yang berubah:*\n";
                        foreach ($differences as $col => $diff) {
                            $message .= "  🔹 `{$col}`:\n";
                            $message .= "       `[UserDB]`    " . $this->truncateStr($diff['userdb'], 60) . "\n";
                            $message .= "       `[AdminDB]`   " . $this->truncateStr($diff['admindb'], 60) . "\n";
                            $message .= "       `[Konsensus]` " . $this->truncateStr($diff['konsensus'], 60) . "\n";
                        }
                    } else {
                        $message .= "_Tidak dapat menemukan perbedaan kolom spesifik._\n";
                    }
                }
                
                $message .= "---------------------------------------\n";
            }
        }
        
        return $message;
    }

    private function findCorruptedColumns(array $data): array
    {
        $dbs = ['userdb', 'admindb', 'konsensus'];
        $allKeys = [];
        
        foreach ($dbs as $db) {
            if (isset($data[$db]) && is_array($data[$db])) {
                $allKeys = array_merge($allKeys, array_keys($data[$db]));
            }
        }
        $allKeys = array_unique($allKeys);
        
        $ignoreKeys = ['id', 'timestamp', 'backup_timestamp', 'created_at', 'updated_at'];
        $differences = [];
        
        foreach ($allKeys as $key) {
            if (in_array($key, $ignoreKeys)) {
                continue;
            }
            
            $val1 = isset($data['userdb'][$key]) ? (string)$data['userdb'][$key] : 'NULL';
            $val2 = isset($data['admindb'][$key]) ? (string)$data['admindb'][$key] : 'NULL';
            $val3 = isset($data['konsensus'][$key]) ? (string)$data['konsensus'][$key] : 'NULL';
            
            if ($val1 !== $val2 || $val2 !== $val3 || $val1 !== $val3) {
                // Jangan kirim seluruh payload json jika panjang, cukup ambil isinya atau potong
                $differences[$key] = [
                    'userdb' => $val1,
                    'admindb' => $val2,
                    'konsensus' => $val3
                ];
            }
        }
        
        return $differences;
    }

    private function truncateStr(string $str, int $length): string
    {
        if (strlen($str) <= $length) {
            return $str;
        }
        return substr($str, 0, $length) . '...';
    }

    /**
     * Format recovery success alert message
     */
    protected function formatRecoverySuccessAlert(array $data): string
    {
        $message = "✅ *RECOVERY SUCCESS*\n\n";
        $message .= "*Recovered:* {$data['total_recovered']} blocks\n";
        $message .= "*Failed:* {$data['failed_count']}\n";
        $message .= "*Success Rate:* " . number_format($data['success_rate'] * 100, 1) . "%\n";
        $message .= "*Duration:* {$data['duration_seconds']}s\n\n";
        $message .= "_Recovery completed successfully_";
        
        return $message;
    }

    /**
     * Format recovery failed alert message
     */
    protected function formatRecoveryFailedAlert(array $data): string
    {
        $message = "❌ *RECOVERY FAILED*\n\n";
        $message .= "*Reason:* {$data['reason']}\n";
        $message .= "*Failed Blocks:* {$data['failed_count']}\n";
        
        if (!empty($data['error_message'])) {
            $message .= "*Error:* {$data['error_message']}\n";
        }
        
        return $message;
    }

    /**
     * Format manual review alert message
     */
    protected function formatManualReviewAlert(array $data): string
    {
        $message = "🔍 *MANUAL REVIEW REQUIRED*\n\n";
        $message .= "*Reason:* {$data['reason']}\n";
        
        if (!empty($data['block_samples'])) {
            $message .= "*Sample Blocks:*\n";
            foreach (array_slice($data['block_samples'], 0, 3) as $sample) {
                $message .= "- " . ($sample['identifier'] ?? 'N/A') . "\n";
            }
        }
        
        $message .= "\n_Please review manually_";
        
        return $message;
    }
}
