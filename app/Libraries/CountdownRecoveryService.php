<?php

namespace App\Libraries;

use Config\Recovery as RecoveryConfig;

/**
 * Countdown Recovery Service
 * 
 * Manages countdown state for automatic block recovery
 * Implements system-wide countdown mechanism before batch recovery execution
 * 
 * State Machine:
 * - idle      : No corruption detected
 * - counting  : Corruption detected, waiting for countdown to complete
 * - recovering: Countdown elapsed, executing batch recovery
 * 
 * State persisted in JSON file (writable/countdown_recovery.json)
 * Resets on system restart (no database persistence as per requirements)
 */
class CountdownRecoveryService
{
    protected $config;
    protected $stateFile;
    protected $lockFile;

    const STATE_IDLE = 'idle';
    const STATE_COUNTING = 'counting';
    const STATE_RECOVERING = 'recovering';

    public function __construct()
    {
        $this->config = new RecoveryConfig();
        $this->stateFile = WRITEPATH . 'countdown_recovery.json';
        $this->lockFile = WRITEPATH . 'countdown_recovery.lock';
    }

    /**
     * Start countdown timer for pending recovery
     * 
     * Called when minority corruption is detected
     * Saves corruption details to state file
     * Subsequent calls are idempotent if countdown already active
     * 
     * @param array $corruptionDetails Array of corruption details from MajorityRecovery::check()
     * @return bool Success
     */
    public function startCountdown(array $corruptionDetails): bool
    {
        $this->acquireLock();
        try {
            $currentState = $this->getState();
            
            // If countdown already active, don't restart (idempotent)
            if ($currentState['status'] === self::STATE_COUNTING) {
                return true;
            }

            $state = [
                'status' => self::STATE_COUNTING,
                'started_at' => date('Y-m-d H:i:s'),
                'started_at_timestamp' => time(),
                'countdown_duration_seconds' => $this->config->countdownDurationSeconds,
                'pending_blocks' => $this->filterMinorityBlocks($corruptionDetails),
                'total_pending' => count($this->filterMinorityBlocks($corruptionDetails))
            ];

            return $this->saveState($state);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Check if countdown is currently active
     * 
     * @return bool True if countdown is running or recovery is in progress
     */
    public function isCountdownActive(): bool
    {
        $state = $this->getState();
        return in_array($state['status'], [self::STATE_COUNTING, self::STATE_RECOVERING]);
    }

    /**
     * Get time remaining in countdown (seconds)
     * 
     * @return int Seconds remaining. Returns 0 if countdown elapsed or not active
     */
    public function getTimeRemaining(): int
    {
        $state = $this->getState();
        
        if ($state['status'] !== self::STATE_COUNTING) {
            return 0;
        }

        $elapsed = time() - $state['started_at_timestamp'];
        $remaining = $state['countdown_duration_seconds'] - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Check if countdown duration has elapsed
     * 
     * @return bool True if countdown should transition to recovering state
     */
    public function isCountdownElapsed(): bool
    {
        $state = $this->getState();
        
        if ($state['status'] !== self::STATE_COUNTING) {
            return false;
        }

        $elapsed = time() - $state['started_at_timestamp'];
        return $elapsed >= $state['countdown_duration_seconds'];
    }

    /**
     * Get pending blocks waiting for recovery
     * 
     * @return array List of block details pending recovery
     */
    public function getPendingBlocks(): array
    {
        $state = $this->getState();
        return $state['pending_blocks'] ?? [];
    }

    /**
     * Get current countdown state
     * 
     * @return array Complete state object
     */
    public function getState(): array
    {
        if (!file_exists($this->stateFile)) {
            return [
                'status' => self::STATE_IDLE,
                'started_at' => null,
                'started_at_timestamp' => null,
                'countdown_duration_seconds' => $this->config->countdownDurationSeconds,
                'pending_blocks' => [],
                'total_pending' => 0
            ];
        }

        $content = file_get_contents($this->stateFile);
        $state = json_decode($content, true);
        
        // Validate state structure
        if (!is_array($state) || !isset($state['status'])) {
            return [
                'status' => self::STATE_IDLE,
                'started_at' => null,
                'started_at_timestamp' => null,
                'countdown_duration_seconds' => $this->config->countdownDurationSeconds,
                'pending_blocks' => [],
                'total_pending' => 0
            ];
        }

        return $state;
    }

    /**
     * Transition state to recovering
     * Called when countdown has elapsed and recovery should start
     * 
     * @return bool Success
     */
    public function transitionToRecovering(): bool
    {
        $this->acquireLock();
        try {
            $state = $this->getState();
            
            if ($state['status'] !== self::STATE_COUNTING) {
                return false;
            }

            $state['status'] = self::STATE_RECOVERING;
            $state['recovery_started_at'] = date('Y-m-d H:i:s');
            $state['recovery_started_at_timestamp'] = time();

            return $this->saveState($state);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Clear countdown state after recovery completes
     * 
     * @return bool Success
     */
    public function clearCountdown(): bool
    {
        $this->acquireLock();
        try {
            $state = [
                'status' => self::STATE_IDLE,
                'started_at' => null,
                'started_at_timestamp' => null,
                'countdown_duration_seconds' => $this->config->countdownDurationSeconds,
                'pending_blocks' => [],
                'total_pending' => 0
            ];

            return $this->saveState($state);
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Force reset countdown (for testing/admin operations)
     * 
     * @return bool Success
     */
    public function forceReset(): bool
    {
        return $this->clearCountdown();
    }

    /**
     * Get state summary for API response
     * 
     * @return array Simplified state for API consumption
     */
    public function getStateForApi(): array
    {
        $state = $this->getState();
        $timeRemaining = $this->getTimeRemaining();

        return [
            'countdown_active' => $this->isCountdownActive(),
            'status' => $state['status'],
            'time_remaining_seconds' => $timeRemaining,
            'total_pending_blocks' => $state['total_pending'] ?? 0,
            'pending_blocks_count' => count($state['pending_blocks'] ?? []),
            'started_at' => $state['started_at'],
            'countdown_duration_seconds' => $state['countdown_duration_seconds'],
            'recovery_started_at' => $state['recovery_started_at'] ?? null
        ];
    }

    /**
     * Filter only minority corruption from check results
     * 
     * @param array $corruptionDetails Full details from MajorityRecovery::check()
     * @return array Only minority blocks
     */
    protected function filterMinorityBlocks(array $corruptionDetails): array
    {
        return array_filter($corruptionDetails, function ($item) {
            return isset($item['status']) && $item['status'] === 'minority';
        });
    }

    /**
     * Save state to JSON file with atomic write
     * 
     * @param array $state State object to save
     * @return bool Success
     */
    protected function saveState(array $state): bool
    {
        try {
            // Create directory if not exists
            if (!is_dir(WRITEPATH)) {
                mkdir(WRITEPATH, 0775, true);
            }

            // Write atomically to temp file then rename
            $tempFile = $this->stateFile . '.tmp';
            $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
                return false;
            }

            // Atomic rename
            return rename($tempFile, $this->stateFile);
        } catch (\Throwable $e) {
            log_message('error', "CountdownRecoveryService: Failed to save state - {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Acquire file lock to prevent race conditions
     * Simple lock mechanism using lockfile
     * 
     * @param int $maxWait Max seconds to wait for lock
     * @return bool Success
     */
    protected function acquireLock(int $maxWait = 5): bool
    {
        $startTime = time();
        
        while (file_exists($this->lockFile)) {
            if (time() - $startTime > $maxWait) {
                log_message('warning', 'CountdownRecoveryService: Lock timeout');
                return true; // Continue anyway after timeout
            }
            usleep(100000); // 100ms
        }

        // Create lock file
        touch($this->lockFile);
        return true;
    }

    /**
     * Release file lock
     * 
     * @return void
     */
    protected function releaseLock(): void
    {
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
    }

    /**
     * Delete state file (useful for testing/debugging)
     * 
     * @return bool Success
     */
    public function deleteStateFile(): bool
    {
        if (file_exists($this->stateFile)) {
            return @unlink($this->stateFile);
        }
        return true;
    }
}
