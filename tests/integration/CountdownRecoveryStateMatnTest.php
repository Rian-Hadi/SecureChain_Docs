<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\CountdownRecoveryService;
use App\Libraries\MajorityRecovery;

/**
 * Integration Tests for Countdown Recovery State Machine
 * 
 * Tests the complete state machine workflow:
 * IDLE → COUNTING → RECOVERING → IDLE
 * 
 * @internal
 */
final class CountdownRecoveryStateMatinTest extends CIUnitTestCase
{
    /**
     * @var CountdownRecoveryService
     */
    protected $countdownService;

    /**
     * Setup before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->countdownService = new CountdownRecoveryService();
        $this->countdownService->deleteStateFile();
    }

    /**
     * Cleanup after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        $this->countdownService->deleteStateFile();
    }

    /**
     * Test complete state machine workflow: IDLE → COUNTING → RECOVERING → IDLE
     */
    public function testCompleteStateMachineWorkflow(): void
    {
        // Step 1: Start in IDLE state
        $state = $this->countdownService->getState();
        $this->assertEquals($state['status'], 'idle');

        // Step 2: Detect corruption and transition to COUNTING
        $corruptionDetails = [
            [
                'status' => 'minority',
                'block_hash' => 'hash1',
                'corrupt_dbs' => ['userdb'],
                'data' => [
                    'admindb' => ['block_hash' => 'hash1', 'nomor_permohonan' => '001']
                ]
            ]
        ];

        $this->service->startCountdown($corruptionDetails);
        
        $state = $this->countdownService->getState();
        $this->assertEquals($state['status'], 'counting');
        $this->assertEquals($state['total_pending'], 1);

        // Step 3: Check countdown not elapsed yet
        $this->assertFalse($this->countdownService->isCountdownElapsed());
        $this->assertTrue($this->countdownService->isCountdownActive());

        // Step 4: Transition to RECOVERING (after countdown would elapse)
        $this->countdownService->transitionToRecovering();
        
        $state = $this->countdownService->getState();
        $this->assertEquals($state['status'], 'recovering');

        // Step 5: After recovery execution, transition back to IDLE
        $this->countdownService->clearCountdown();
        
        $state = $this->countdownService->getState();
        $this->assertEquals($state['status'], 'idle');
        $this->assertFalse($this->countdownService->isCountdownActive());
    }

    /**
     * Test state machine handles multiple pending blocks
     */
    public function testStateMachineWithMultipleBlocks(): void
    {
        $corruptionDetails = [
            [
                'status' => 'minority',
                'block_hash' => 'hash1',
                'corrupt_dbs' => ['userdb']
            ],
            [
                'status' => 'minority',
                'block_hash' => 'hash2',
                'corrupt_dbs' => ['admindb']
            ],
            [
                'status' => 'minority',
                'block_hash' => 'hash3',
                'corrupt_dbs' => ['konsensus']
            ]
        ];

        $this->countdownService->startCountdown($corruptionDetails);
        
        $state = $this->countdownService->getState();
        $this->assertEquals($state['total_pending'], 3);
        
        // All should be available for recovery
        $pending = $this->countdownService->getPendingBlocks();
        $this->assertEquals(count($pending), 3);
    }

    /**
     * Test idempotent countdown start behavior
     */
    public function testIdempotentCountdownStart(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'hash1',
            'corrupt_dbs' => ['userdb']
        ]];

        // Start countdown
        $this->countdownService->startCountdown($corruptionDetails);
        $state1 = $this->countdownService->getState();
        $timestamp1 = $state1['started_at_timestamp'];

        // Wait a bit
        sleep(1);

        // Try starting countdown again with new corruption about a different block
        $newCorruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'hash2',
            'corrupt_dbs' => ['admindb']
        ]];

        $this->countdownService->startCountdown($newCorruptionDetails);
        $state2 = $this->countdownService->getState();
        $timestamp2 = $state2['started_at_timestamp'];

        // Timestamps should match (countdown not restarted)
        $this->assertEquals($timestamp1, $timestamp2);

        // Pending blocks should still be from first start
        $pending = $this->countdownService->getPendingBlocks();
        $this->assertEquals(count($pending), 1);
        $this->assertEquals($pending[0]['block_hash'], 'hash1');
    }

    /**
     * Test recovery can't happen if countdown not elapsed
     */
    public function testRecoveryRequiresCountdownElapsed(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'hash1',
            'corrupt_dbs' => ['userdb']
        ]];

        $this->countdownService->startCountdown($corruptionDetails);
        
        // Time remaining should be ~300 seconds
        $timeRemaining = $this->countdownService->getTimeRemaining();
        $this->assertGreater($timeRemaining, 250);

        // Countdown should NOT be elapsed
        $this->assertFalse($this->countdownService->isCountdownElapsed());
    }

    /**
     * Test API response throughout state transitions
     */
    public function testApiResponseWithStateTransitions(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'hash1',
            'corrupt_dbs' => ['userdb']
        ]];

        // In IDLE state
        $apiResponse = $this->countdownService->getStateForApi();
        $this->assertFalse($apiResponse['countdown_active']);
        $this->assertEquals($apiResponse['status'], 'idle');

        // Start countdown
        $this->countdownService->startCountdown($corruptionDetails);
        $apiResponse = $this->countdownService->getStateForApi();
        $this->assertTrue($apiResponse['countdown_active']);
        $this->assertEquals($apiResponse['status'], 'counting');
        $this->assertEquals($apiResponse['total_pending_blocks'], 1);

        // Transition to recovering
        $this->countdownService->transitionToRecovering();
        $apiResponse = $this->countdownService->getStateForApi();
        $this->assertTrue($apiResponse['countdown_active']);
        $this->assertEquals($apiResponse['status'], 'recovering');

        // Back to idle
        $this->countdownService->clearCountdown();
        $apiResponse = $this->countdownService->getStateForApi();
        $this->assertFalse($apiResponse['countdown_active']);
        $this->assertEquals($apiResponse['status'], 'idle');
    }
}
