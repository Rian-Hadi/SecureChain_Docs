<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\MajorityRecovery;
use App\Libraries\ConsensusMonitoring;
use App\Libraries\CountdownRecoveryService;
use App\Libraries\RecoveryNotificationService;
use App\Models\ActivityLogModel;
use Config\Recovery as RecoveryConfig;

/**
 * ============================================================
 * RECOVERY DAEMON — Background Auto-Recovery Service
 * ============================================================
 * 
 * Daemon yang berjalan terus-menerus di background untuk:
 * - Deteksi manipulasi data secara otomatis (tanpa refresh manual)
 * - Recovery otomatis menggunakan konsensus 2/3 mayoritas
 * - Logging semua event ke activity_log
 * - Notifikasi via Telegram jika ada anomali
 * 
 * Implementasi Layer Detection dari arsi.md:
 * - LAYER 1: Consensus Check (setiap checkInterval detik)
 * - LAYER 2: Auto-Recovery (jika anomali terdeteksi)
 * - LAYER 3: Integrity Verification (pasca recovery)
 * 
 * Cara menjalankan:
 *   php spark recovery:daemon              # Foreground (lihat output)
 *   php spark recovery:daemon &            # Background (Linux)
 *   php spark recovery:daemon --interval=30  # Custom interval 30 detik
 *   php spark recovery:daemon --once        # Jalankan sekali lalu exit
 * 
 * Menghentikan daemon:
 *   kill $(cat writable/recovery_daemon.pid)
 *   # atau: Ctrl+C jika foreground
 */
class RecoveryDaemon extends BaseCommand
{
    protected $group       = 'Maintenance';
    protected $name        = 'recovery:daemon';
    protected $description = 'Run background daemon for auto-detection & recovery of manipulated data';
    protected $usage       = 'recovery:daemon [--interval=N] [--once] [--no-recover] [--verbose]';
    protected $options     = [
        '--interval'   => 'Check interval in seconds (default: from config, typically 30)',
        '--once'       => 'Run check once then exit (no loop)',
        '--no-recover' => 'Detection only, no auto-recovery',
        '--verbose'    => 'Show detailed output for each cycle',
    ];

    private int $checkInterval;
    private int $cycleCount = 0;
    private bool $running = true;
    private string $pidFile;
    private string $logFile;

    public function run(array $params = []): void
    {
        $config = new RecoveryConfig();

        // Parse options
        $this->checkInterval = (int)($params['interval'] ?? CLI::getOption('interval') ?? min($config->checkIntervalSeconds, 30));
        $runOnce    = array_key_exists('once', $params) || CLI::getOption('once') !== null;
        $noRecover  = array_key_exists('no-recover', $params) || CLI::getOption('no-recover') !== null;
        $verbose    = array_key_exists('verbose', $params) || CLI::getOption('verbose') !== null;

        // Setup paths
        $this->pidFile = WRITEPATH . 'recovery_daemon.pid';
        $this->logFile = WRITEPATH . 'logs/recovery_daemon.log';

        // Check if another daemon is already running
        if ($this->isDaemonRunning()) {
            CLI::error('Another recovery daemon is already running! PID: ' . file_get_contents($this->pidFile));
            CLI::write('To stop it: kill $(cat ' . $this->pidFile . ')', 'yellow');
            return;
        }

        // Write PID file
        $this->writePidFile();

        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT,  [$this, 'handleSignal']);
        }

        // Startup banner
        $this->printBanner($config, $runOnce, $noRecover);

        $this->logDaemon('INFO', 'Recovery daemon started. Interval: ' . $this->checkInterval . 's');

        // Main loop
        try {
            do {
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                if (!$this->running) {
                    break;
                }

                $this->cycleCount++;
                $this->executeCycle($config, $noRecover, $verbose);

                if (!$runOnce && $this->running) {
                    if ($verbose) {
                        CLI::write('[' . date('H:i:s') . "] Sleeping {$this->checkInterval}s until next cycle...", 'dark_gray');
                    }
                    // Sleep in small increments to respond to signals faster
                    $this->interruptibleSleep($this->checkInterval);
                }
            } while (!$runOnce && $this->running);
        } catch (\Throwable $e) {
            $this->logDaemon('ERROR', 'Daemon crashed: ' . $e->getMessage());
            CLI::error('Daemon error: ' . $e->getMessage());
        } finally {
            $this->cleanup();
        }

        CLI::write("\n[" . date('H:i:s') . '] Recovery daemon stopped.', 'yellow');
    }

    /**
     * Execute one detection + recovery cycle
     */
    private function executeCycle(RecoveryConfig $config, bool $noRecover, bool $verbose): void
    {
        $startTime = microtime(true);
        $timestamp = date('Y-m-d H:i:s');

        if ($verbose) {
            CLI::write("\n" . str_repeat('─', 60), 'dark_gray');
            CLI::write("[{$timestamp}] Cycle #{$this->cycleCount} — Checking consensus...", 'cyan');
        }

        try {
            $countdownService = new CountdownRecoveryService();
            $state = $countdownService->getState();
            $status = $state['status'] ?? 'idle';

            if (!$config->autoRecoverEnabled) {
                $status = 'idle'; // Reset or ignore if auto-recovery is globally disabled
            }

            // ═══════════════════════════════════════════
            // LAYER 1: Consensus Check (Detection)
            // ═══════════════════════════════════════════
            $majorityRecovery = new MajorityRecovery();
            $checkResult = $majorityRecovery->check();

            $totalChecked    = $checkResult['total_checked'] ?? 0;
            $healthyCount    = $checkResult['healthy'] ?? 0;
            $minorityCount   = $checkResult['minority_corrupt'] ?? 0;
            $noConsensus     = $checkResult['no_consensus'] ?? 0;
            $missingCount    = $checkResult['missing_in_db'] ?? 0;
            $hashRepair      = $checkResult['hash_repair'] ?? 0;
            $anomalyCount    = $minorityCount + $noConsensus + $missingCount + $hashRepair;

            $executionMs = round((microtime(true) - $startTime) * 1000);

            // ═══════════════════════════════════════════
            // Display status
            // ═══════════════════════════════════════════
            if ($anomalyCount === 0) {
                // All healthy — compact output
                CLI::write(
                    "[{$timestamp}] ✅ Cycle #{$this->cycleCount}: {$totalChecked} records OK ({$executionMs}ms)",
                    'green'
                );

                // If system became healthy, clear any active countdown
                if ($status !== 'idle') {
                    $countdownService->clearCountdown();
                    CLI::write("  🧹 System is healthy. Active countdown cleared.", 'green');
                }
                return;
            }

            // Anomalies detected — detailed output
            CLI::write(
                "[{$timestamp}] ⚠️  Cycle #{$this->cycleCount}: {$anomalyCount} anomali terdeteksi dari {$totalChecked} records ({$executionMs}ms)",
                'yellow'
            );
            CLI::write("  ├─ Minority (2/1): {$minorityCount}", $minorityCount > 0 ? 'yellow' : 'green');
            CLI::write("  ├─ No Consensus:   {$noConsensus}", $noConsensus > 0 ? 'red' : 'green');
            CLI::write("  ├─ Missing Data:   {$missingCount}", $missingCount > 0 ? 'yellow' : 'green');
            CLI::write("  └─ Hash Repair:    {$hashRepair}", $hashRepair > 0 ? 'yellow' : 'green');

            $this->logDaemon('WARNING', "Anomalies detected: minority={$minorityCount}, no_consensus={$noConsensus}, missing={$missingCount}, hash_repair={$hashRepair}");

            // ═══════════════════════════════════════════
            // LAYER 2: Auto-Recovery (jika enabled)
            // ═══════════════════════════════════════════
            if (!$noRecover && $config->autoRecoverEnabled) {
                if ($config->countdownRecoveryEnabled) {
                    // COUNTDOWN-BASED RECOVERY MODE
                    if ($status === 'idle') {
                        // Filter items yang bisa di-recover otomatis
                        $recoverableItems = array_filter(
                            $checkResult['details'] ?? [],
                            fn($item) => in_array($item['status'] ?? '', ['minority', 'missing', 'hash_repair'])
                        );

                        if (!empty($recoverableItems)) {
                            // Start countdown
                            if ($countdownService->startCountdown($recoverableItems)) {
                                CLI::write("  🕒 Countdown recovery started for " . count($recoverableItems) . " block(s).", 'cyan');
                                CLI::write("     Recovery will execute in {$config->countdownDurationSeconds}s.", 'cyan');
                                $this->logDaemon('INFO', "Recovery countdown started for " . count($recoverableItems) . " block(s)");

                                // Send notification
                                try {
                                    $notificationService = new RecoveryNotificationService();
                                    $notificationService->notifyCorruptionDetected([
                                        'conflict_type' => '2-1',
                                        'affected_blocks' => array_values($recoverableItems),
                                        'countdown_duration' => $config->countdownDurationSeconds
                                    ]);
                                } catch (\Throwable $ne) {
                                    $this->logDaemon('WARNING', 'Notification failed: ' . $ne->getMessage());
                                }
                            }
                        }
                    } elseif ($status === 'counting') {
                        $timeRemaining = $countdownService->getTimeRemaining();
                        CLI::write("  🕒 Countdown recovery active: {$timeRemaining}s remaining.", 'cyan');

                        if ($countdownService->isCountdownElapsed()) {
                            CLI::write('  🔔 Countdown elapsed. Transitioning to recovery state...', 'yellow');

                            if ($countdownService->transitionToRecovering()) {
                                $this->logDaemon('INFO', 'Transitioned countdown to recovering state.');
                                
                                // Get pending blocks from countdown state to recover
                                $pendingBlocks = $countdownService->getPendingBlocks();
                                $this->executeAutoRecovery($pendingBlocks, $majorityRecovery, $verbose);

                                // Clear countdown
                                $countdownService->clearCountdown();
                            }
                        }
                    } elseif ($status === 'recovering') {
                        CLI::write('  🔧 Processing active recovery state...', 'yellow');
                        $pendingBlocks = $countdownService->getPendingBlocks();
                        $this->executeAutoRecovery($pendingBlocks, $majorityRecovery, $verbose);

                        // Clear countdown
                        $countdownService->clearCountdown();
                    }
                } else {
                    // DIRECT RECOVERY MODE
                    $recoverableItems = array_filter(
                        $checkResult['details'] ?? [],
                        fn($item) => in_array($item['status'] ?? '', ['minority', 'missing', 'hash_repair'])
                    );
                    $this->executeAutoRecovery(array_values($recoverableItems), $majorityRecovery, $verbose);
                }
            } elseif ($noRecover) {
                CLI::write("  ⏸️  Auto-recovery disabled (--no-recover flag)", 'dark_gray');
            } else {
                CLI::write("  ⏸️  Auto-recovery globally disabled in Config/Recovery.php (autoRecoverEnabled = false)", 'yellow');
            }
        } catch (\Throwable $e) {
            $this->logDaemon('ERROR', "Cycle #{$this->cycleCount} error: " . $e->getMessage());
            CLI::error("[{$timestamp}] Cycle #{$this->cycleCount} error: " . $e->getMessage());
        }
    }

    /**
     * Execute auto-recovery for detected anomalies
     */
    private function executeAutoRecovery(array $recoverableItems, MajorityRecovery $majorityRecovery, bool $verbose): void
    {
        if (empty($recoverableItems)) {
            CLI::write("  ℹ️  No auto-recoverable items (no_consensus requires manual review)", 'dark_gray');
            return;
        }

        $count = count($recoverableItems);
        CLI::write("  🔧 Auto-recovering {$count} record(s)...", 'cyan');

        try {
            $recoveryStart = microtime(true);
            $result = $majorityRecovery->recover(
                items: array_values($recoverableItems),
                performedBy: 'daemon_auto'
            );
            $recoveryMs = round((microtime(true) - $recoveryStart) * 1000);

            $success = $result['success'] ?? 0;
            $failed  = $result['failed'] ?? 0;
            $skipped = $result['skipped'] ?? 0;

            if ($failed === 0 && $success > 0) {
                CLI::write("  ✅ Recovery berhasil: {$success} dipulihkan ({$recoveryMs}ms)", 'green');
                $this->logDaemon('SUCCESS', "Auto-recovery: {$success} records recovered in {$recoveryMs}ms");
            } elseif ($success > 0) {
                CLI::write("  ⚠️  Recovery parsial: {$success} berhasil, {$failed} gagal ({$recoveryMs}ms)", 'yellow');
                $this->logDaemon('WARNING', "Partial recovery: {$success} success, {$failed} failed");
            } else {
                CLI::write("  ❌ Recovery gagal: {$failed} error(s) ({$recoveryMs}ms)", 'red');
                $this->logDaemon('ERROR', "Recovery failed: {$failed} errors");
            }

            // Show details in verbose mode
            if ($verbose && !empty($result['details'])) {
                foreach ($result['details'] as $detail) {
                    $icon = ($detail['success'] ?? false) ? '✓' : '✗';
                    $key  = $detail['record_key'] ?? 'unknown';
                    $src  = $detail['source_db'] ?? '?';
                    $tgt  = implode(',', $detail['target_dbs'] ?? []);
                    CLI::write("     {$icon} {$key} [{$src}→{$tgt}]", ($detail['success'] ?? false) ? 'green' : 'red');
                }
            }

            // ═══════════════════════════════════════════
            // LAYER 3: Post-Recovery Verification
            // ═══════════════════════════════════════════
            if ($success > 0) {
                $this->verifyRecovery($majorityRecovery, $verbose);
            }

            // Send notification for significant events
            $this->sendNotificationIfNeeded($recoverableItems, $result);

        } catch (\Throwable $e) {
            CLI::error("  ❌ Recovery error: " . $e->getMessage());
            $this->logDaemon('ERROR', 'Recovery exception: ' . $e->getMessage());
        }
    }

    /**
     * Verify recovery was successful by re-checking consensus
     */
    private function verifyRecovery(MajorityRecovery $majorityRecovery, bool $verbose): void
    {
        if ($verbose) {
            CLI::write("  🔍 Verifying post-recovery integrity...", 'dark_gray');
        }

        try {
            $verifyResult = $majorityRecovery->check();
            $remainingAnomalies = ($verifyResult['minority_corrupt'] ?? 0) 
                + ($verifyResult['no_consensus'] ?? 0) 
                + ($verifyResult['missing_in_db'] ?? 0)
                + ($verifyResult['hash_repair'] ?? 0);

            if ($remainingAnomalies === 0) {
                CLI::write("  ✅ Verification: Semua data konsisten setelah recovery", 'green');
            } else {
                CLI::write("  ⚠️  Verification: Masih ada {$remainingAnomalies} anomali tersisa", 'yellow');
                $this->logDaemon('WARNING', "Post-recovery: {$remainingAnomalies} anomalies remain");
            }
        } catch (\Throwable $e) {
            CLI::write("  ⚠️  Verification skipped: " . $e->getMessage(), 'dark_gray');
        }
    }

    /**
     * Send notification for significant events
     */
    private function sendNotificationIfNeeded(array $recoverableItems, array $recoveryResult): void
    {
        try {
            $notificationService = new RecoveryNotificationService();
            $success = $recoveryResult['success'] ?? 0;
            $failed  = $recoveryResult['failed'] ?? 0;

            if ($success > 0) {
                $notificationService->notifyRecoverySuccess([
                    'recovered_count' => $success,
                    'failed_count'    => $failed,
                    'trigger'         => 'daemon_auto',
                    'cycle'           => $this->cycleCount,
                ]);
            }

            if ($failed > 0) {
                $notificationService->notifyRecoveryFailed([
                    'reason'        => 'Auto-recovery daemon detected unrecoverable items',
                    'failed_blocks' => $failed,
                ]);
            }
        } catch (\Throwable $e) {
            // Notification failure should not crash the daemon
            $this->logDaemon('WARNING', 'Notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Sleep that can be interrupted by signals
     */
    private function interruptibleSleep(int $seconds): void
    {
        $end = time() + $seconds;
        while (time() < $end && $this->running) {
            sleep(1);
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    /**
     * Handle OS signals (SIGTERM, SIGINT)
     */
    public function handleSignal(int $signal): void
    {
        $signalName = match($signal) {
            SIGTERM => 'SIGTERM',
            SIGINT  => 'SIGINT',
            default => "Signal #{$signal}",
        };
        
        CLI::write("\n[" . date('H:i:s') . "] Received {$signalName}. Shutting down gracefully...", 'yellow');
        $this->logDaemon('INFO', "Received {$signalName}. Stopping daemon.");
        $this->running = false;
    }

    /**
     * Check if daemon is already running
     */
    private function isDaemonRunning(): bool
    {
        if (!file_exists($this->pidFile)) {
            return false;
        }

        $pid = (int)trim(file_get_contents($this->pidFile));
        
        if ($pid <= 0) {
            return false;
        }

        // Check if process is actually running (Linux)
        if (file_exists("/proc/{$pid}")) {
            return true;
        }

        // Stale PID file — clean up
        @unlink($this->pidFile);
        return false;
    }

    /**
     * Write PID file
     */
    private function writePidFile(): void
    {
        $dir = dirname($this->pidFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($this->pidFile, getmypid());
    }

    /**
     * Cleanup on exit
     */
    private function cleanup(): void
    {
        if (file_exists($this->pidFile)) {
            @unlink($this->pidFile);
        }
        $this->logDaemon('INFO', "Daemon stopped after {$this->cycleCount} cycles.");
    }

    /**
     * Log daemon events to file
     */
    private function logDaemon(string $level, string $message): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Print startup banner
     */
    private function printBanner(RecoveryConfig $config, bool $runOnce, bool $noRecover): void
    {
        CLI::write('');
        CLI::write('╔══════════════════════════════════════════════════════════╗', 'cyan');
        CLI::write('║       RECOVERY DAEMON — Auto-Detection & Recovery       ║', 'cyan');
        CLI::write('║       Konsensus 2/3 Majority · Background Service       ║', 'cyan');
        CLI::write('╚══════════════════════════════════════════════════════════╝', 'cyan');
        CLI::write('');
        CLI::write('  Mode:         ' . ($runOnce ? 'Single Run' : 'Continuous Daemon'), 'white');
        CLI::write('  Interval:     ' . $this->checkInterval . ' detik', 'white');
        
        $autoRecoverStatus = $noRecover ? 'DISABLED (CLI Option)' : ($config->autoRecoverEnabled ? 'ENABLED' : 'DISABLED (Config)');
        $autoRecoverColor = ($noRecover || !$config->autoRecoverEnabled) ? 'red' : 'green';
        CLI::write('  Auto-Recover: ' . CLI::color($autoRecoverStatus, $autoRecoverColor), 'white');
        
        CLI::write('  PID:          ' . getmypid(), 'white');
        CLI::write('  PID File:     ' . $this->pidFile, 'white');
        CLI::write('  Log File:     ' . $this->logFile, 'white');
        CLI::write('  Started:      ' . date('Y-m-d H:i:s'), 'white');
        CLI::write('');

        if (!$runOnce) {
            CLI::write('  Tekan Ctrl+C untuk menghentikan daemon', 'dark_gray');
            CLI::write('  Atau: kill ' . getmypid(), 'dark_gray');
        }
        CLI::write('');
    }
}
