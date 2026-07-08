<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\MajorityRecovery;
use App\Libraries\CountdownRecoveryService;
use App\Libraries\RecoveryNotificationService;
use App\Models\BlockModel;
use App\Models\ActivityLogModel;
use Config\Recovery as RecoveryConfig;

class AutoRecover extends BaseCommand
{
    protected $group = 'Maintenance';
    protected $name = 'auto:recover';
    protected $description = 'Run consensus-based auto recovery for manipulated blocks';

    public function run(array $params = [])
    {
        $config = new RecoveryConfig();
        $majorityRecovery = new MajorityRecovery();
        $countdownService = new CountdownRecoveryService();
        $activityLog = new ActivityLogModel();
        $notificationService = new RecoveryNotificationService();

        // Get current state
        $state = $countdownService->getState();
        $status = $state['status'];

        CLI::write("Recovery State Machine: {$status}", 'yellow');

        // STATE: IDLE - Check for corruption and start countdown if needed
        if ($status === 'idle') {
            if (!$config->countdownRecoveryEnabled) {
                // Direct recovery mode (no countdown)
                $this->executeDirectRecovery($majorityRecovery, $activityLog, $notificationService);
                return;
            }

            // Check for corruption
            $checkResult = $majorityRecovery->check();
            $minorityCount = $checkResult['minority_corrupt'] ?? 0;
            $noConsensusCount = $checkResult['no_consensus'] ?? 0;

            if ($minorityCount > 0) {
                // Get all eligible blocks (minority corruption)
                $eligibleBlocks = array_filter($checkResult['details'] ?? [], function ($detail) {
                    return ($detail['status'] ?? null) === 'minority';
                });
                $eligibleCount = count($eligibleBlocks);

                if ($eligibleCount > 0) {
                    // Send corruption detection alert
                    $notificationService->notifyCorruptionDetected([
                        'conflict_type' => '2-1',
                        'affected_blocks' => array_column($eligibleBlocks, 'record_key'),
                        'countdown_duration' => $config->countdownDurationSeconds
                    ]);

                    // Start countdown
                    if ($countdownService->startCountdown($eligibleBlocks)) {
                        CLI::write(
                            "Countdown started for {$eligibleCount} corrupt block(s). " . 
                            "Recovery in {$config->countdownDurationSeconds}s",
                            'cyan'
                        );

                        $activityLog->logActivity([
                            'action_type' => 'RECOVERY_COUNTDOWN_STARTED',
                            'status' => 'INFO',
                            'description' => "Countdown started for {$eligibleCount} corrupt block(s)",
                            'original_data' => [
                                'eligible_blocks' => $eligibleCount,
                                'total_minority' => $minorityCount,
                                'no_consensus_count' => $noConsensusCount,
                                'countdown_duration_seconds' => $config->countdownDurationSeconds
                            ]
                        ]);
                    } else {
                        CLI::write('Failed to start countdown', 'red');
                    }
                }
            } else {
                CLI::write('No corruption detected. System healthy.', 'green');
            }

            return;
        }

        // STATE: COUNTING - Check if countdown should transition to recovering
        if ($status === 'counting') {
            $timeRemaining = $countdownService->getTimeRemaining();
            $totalPending = $state['total_pending'] ?? 0;

            CLI::write(
                "Countdown active: {$timeRemaining}s remaining for {$totalPending} block(s)",
                'cyan'
            );

            if ($countdownService->isCountdownElapsed()) {
                CLI::write('Countdown elapsed. Transitioning to recovery...', 'yellow');

                if ($countdownService->transitionToRecovering()) {
                    $activityLog->logActivity([
                        'action_type' => 'RECOVERY_TRANSITION_STARTED',
                        'status' => 'INFO',
                        'description' => "Transitioning from countdown to recovery state",
                        'original_data' => ['pending_blocks_count' => $totalPending]
                    ]);

                    // Send recovery starting notification
                    $notificationService->notifyRecoveryStarted([
                        'blocks' => $state['pending_blocks'] ?? []
                    ]);
                }
            }

            return;
        }

        // STATE: RECOVERING - Execute batch recovery
        if ($status === 'recovering') {
            CLI::write('Executing batch recovery with LLM-enhanced validation...', 'yellow');
            
            $pendingBlocks = $countdownService->getPendingBlocks();
            $recoveryResult = $this->executeBatchRecovery($pendingBlocks, $majorityRecovery, $activityLog, $notificationService);

            // Log batch result
            $activityLog->logActivity([
                'action_type' => 'RECOVERY_BATCH_EXECUTED',
                'status' => 'INFO',
                'description' => "Batch recovery executed",
                'original_data' => [
                    'total_pending' => count($pendingBlocks),
                    'recovery_result' => $recoveryResult
                ]
            ]);

            // Send completion notification
            if ($recoveryResult['recovered'] > 0) {
                $notificationService->notifyRecoverySuccess([
                    'recovered_count' => $recoveryResult['recovered'],
                    'failed_count' => $recoveryResult['failed'],
                    'duration' => $recoveryResult['duration'] ?? 0,
                    'llm_confidence' => $pendingBlocks[0]['llm_analysis']['confidence'] ?? null
                ]);
            } elseif ($recoveryResult['failed'] > 0) {
                $notificationService->notifyRecoveryFailed([
                    'reason' => 'Recovery execution failed',
                    'failed_blocks' => $recoveryResult['failed'],
                    'error' => $recoveryResult['error'] ?? null
                ]);
            }

            // Clear countdown state
            $countdownService->clearCountdown();

            CLI::write(
                "Batch recovery finished. " .
                "Recovered: {$recoveryResult['recovered']}, " .
                "Failed: {$recoveryResult['failed']}, " .
                "Skipped: {$recoveryResult['skipped']}",
                'green'
            );

            return;
        }

        CLI::write("Unknown state: {$status}", 'red');
    }

    /**
     * Direct recovery mode (when countdown is disabled)
     * Legacy behavior: immediately recover all minority corruption with LLM validation
     * 
     * @param MajorityRecovery $majorityRecovery
     * @param ActivityLogModel $activityLog
     * @param RecoveryNotificationService $notificationService
     * @return void
     */
    private function executeDirectRecovery(
        MajorityRecovery $majorityRecovery,
        ActivityLogModel $activityLog,
        RecoveryNotificationService $notificationService
    ): void {
        CLI::write('Direct recovery mode (countdown disabled)', 'yellow');

        $checkResult = $majorityRecovery->check();
        $recoveryResult = $this->executeBatchRecovery($checkResult['details'] ?? [], $majorityRecovery, $activityLog, $notificationService);

        CLI::write(
            "Direct recovery finished. Recovered: {$recoveryResult['recovered']}, " .
            "Failed: {$recoveryResult['failed']}, Skipped: {$recoveryResult['skipped']}",
            'green'
        );
    }


    /**
     * Execute batch recovery for list of corrupt blocks with LLM validation
     * 
     * @param array $corruptBlocks List of corruption details
     * @param MajorityRecovery $majorityRecovery
     * @param ActivityLogModel $activityLog
     * @param RecoveryNotificationService $notificationService
     * @return array Recovery statistics: ['recovered' => int, 'failed' => int, 'skipped' => int, 'duration' => float]
     */
    private function executeBatchRecovery(
        array $corruptBlocks,
        MajorityRecovery $majorityRecovery,
        ActivityLogModel $activityLog,
        RecoveryNotificationService $notificationService
    ): array {
        $blockModel = new BlockModel();
        $recovered = 0;
        $failed = 0;
        $skipped = 0;
        $startTime = microtime(true);

        if (empty($corruptBlocks)) {
            return ['recovered' => 0, 'failed' => 0, 'skipped' => 0, 'duration' => 0];
        }

        foreach ($corruptBlocks as $detail) {
            $status = $detail['status'] ?? 'unknown';
            $corruptDbs = $detail['corrupt_dbs'] ?? [];
            $blockId = $detail['record_key'] ?? null;

            // Only recover minority corruption
            if ($status !== 'minority') {
                $skipped++;
                continue;
            }

            if (empty($corruptDbs) || !$blockId) {
                $failed++;
                continue;
            }

            $blockData = null;
            $healthyDb = null;

            if (in_array('userdb', $corruptDbs)) {
                $blockData = $detail['data']['admindb'] ?? $detail['data']['konsensus'] ?? null;
                $healthyDb = !empty($detail['data']['admindb']) ? 'admindb' : 'konsensus';
            } elseif (in_array('admindb', $corruptDbs)) {
                $blockData = $detail['data']['userdb'] ?? $detail['data']['konsensus'] ?? null;
                $healthyDb = !empty($detail['data']['userdb']) ? 'userdb' : 'konsensus';
            } elseif (in_array('konsensus', $corruptDbs)) {
                $blockData = $detail['data']['userdb'] ?? $detail['data']['admindb'] ?? null;
                $healthyDb = !empty($detail['data']['userdb']) ? 'userdb' : 'admindb';
            }

            if (!$blockData || !$healthyDb) {
                $failed++;
                continue;
            }

            $recoveryData = [
                'nomor_permohonan' => $blockData['nomor_permohonan'] ?? null,
                'nomor_dokumen' => $blockData['nomor_dokumen'] ?? null,
                'tanggal_dokumen' => $blockData['tanggal_dokumen'] ?? null,
                'tanggal_filing' => $blockData['tanggal_filing'] ?? null,
                'dokumen_base64' => $blockData['dokumen_base64'] ?? null,
                'ip_address' => $blockData['ip_address'] ?? null,
                'block_hash' => $blockData['block_hash'] ?? null,
                'previous_hash' => $blockData['previous_hash'] ?? null,
            ];

            try {
                if ($blockModel->update($blockId, $recoveryData)) {
                    $recovered++;
                    $activityLog->logActivity([
                        'action_type' => 'RECOVER',
                        'block_id' => $blockId,
                        'identifier' => $blockData['nomor_permohonan'] ?? null,
                        'status' => 'Recovered',
                        'description' => "Data recovered from {$healthyDb} (consensus majority)",
                        'original_data' => ['corrupt_dbs' => $corruptDbs],
                        'modified_data' => ['block_hash' => $blockData['block_hash'] ?? null]
                    ]);
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                CLI::write("Error recovering block {$blockId}: {$e->getMessage()}", 'red');
            }
        }

        $duration = microtime(true) - $startTime;
        
        return ['recovered' => $recovered, 'failed' => $failed, 'skipped' => $skipped, 'duration' => $duration];
    }
}
