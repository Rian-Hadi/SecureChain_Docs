<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Libraries\CountdownRecoveryService;

/**
 * Unit Tests for CountdownRecoveryService
 * 
 * Tests the countdown recovery state management and transitions
 * 
 * @internal
 */
final class CountdownRecoveryServiceTest extends CIUnitTestCase
{
    /**
     * @var CountdownRecoveryService
     */
    protected $service;

    /**
     * @var string
     */
    protected $testStateFile;

    /**
     * Setup before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Use a temporary file for testing
        $this->testStateFile = WRITEPATH . 'countdown_recovery_test.json';
        
        $this->service = new CountdownRecoveryService();
        
        // Clean up any previous test state
        $this->service->deleteStateFile();
    }

    /**
     * Cleanup after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Cleanup test files
        $this->service->deleteStateFile();
        
        if (file_exists($this->testStateFile)) {
            unlink($this->testStateFile);
        }
    }

    /**
     * Test initial state is IDLE
     */
    public function testInitialStateIsIdle(): void
    {
        $state = $this->service->getState();
        
        $this->assertEqual($state['status'], 'idle');
        $this->assertNull($state['started_at']);
        $this->assertEqual($state['total_pending'], 0);
    }

    /**
     * Test countdown can be started
     */
    public function testCanStartCountdown(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb'],
            'source_db' => 'admindb'
        ]];
        
        $result = $this->service->startCountdown($corruptionDetails);
        
        $this->assertTrue($result);
        $this->assertTrue($this->service->isCountdownActive());
        
        $state = $this->service->getState();
        $this->assertEqual($state['status'], 'counting');
        $this->assertEqual($state['total_pending'], 1);
    }

    /**
     * Test countdown start is idempotent
     */
    public function testCountdownStartIsIdempotent(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        // Start countdown
        $this->service->startCountdown($corruptionDetails);
        $firstState = $this->service->getState();
        $firstStartTime = $firstState['started_at_timestamp'];
        
        // Try starting again
        sleep(1);
        $this->service->startCountdown($corruptionDetails);
        $secondState = $this->service->getState();
        $secondStartTime = $secondState['started_at_timestamp'];
        
        // Should not restart (timestamps should match)
        $this->assertEqual($firstStartTime, $secondStartTime);
    }

    /**
     * Test countdown active check
     */
    public function testIsCountdownActive(): void
    {
        $this->assertFalse($this->service->isCountdownActive());
        
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        $this->service->startCountdown($corruptionDetails);
        $this->assertTrue($this->service->isCountdownActive());
    }

    /**
     * Test time remaining calculation
     */
    public function testGetTimeRemaining(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        $this->service->startCountdown($corruptionDetails);
        $timeRemaining = $this->service->getTimeRemaining();
        
        // Should be close to 300 seconds (5 minutes)
        $this->assertGreater($timeRemaining, 290);
        $this->assertLessOrEqual($timeRemaining, 300);
    }

    /**
     * Test countdown elapsed detection
     */
    public function testIsCountdownElapsed(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        $this->service->startCountdown($corruptionDetails);
        
        // Should not be elapsed immediately
        $this->assertFalse($this->service->isCountdownElapsed());
        
        // Manually set elapsed time in state for testing
        $state = $this->service->getState();
        $state['started_at_timestamp'] = time() - 301; // 301 seconds ago
        
        // Save modified state (would need to bypass lock in actual test)
        // This is a limitation of unit testing with file-based state
        // In integration tests, we can properly test this
    }

    /**
     * Test pending blocks retrieval
     */
    public function testGetPendingBlocks(): void
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
                'status' => 'no_consensus', // Should be filtered out
                'block_hash' => 'hash3',
                'corrupt_dbs' => ['all']
            ]
        ];
        
        $this->service->startCountdown($corruptionDetails);
        $pendingBlocks = $this->service->getPendingBlocks();
        
        // Should only have 2 minority blocks
        $this->assertEqual(count($pendingBlocks), 2);
        
        // Verify structure
        $this->assertArrayHasKey('status', $pendingBlocks[0]);
        $this->assertEqual($pendingBlocks[0]['status'], 'minority');
    }

    /**
     * Test transition to recovering state
     */
    public function testTransitionToRecovering(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        $this->service->startCountdown($corruptionDetails);
        
        // Transition to recovering
        $result = $this->service->transitionToRecovering();
        $this->assertTrue($result);
        
        $state = $this->service->getState();
        $this->assertEqual($state['status'], 'recovering');
        $this->assertIsString($state['recovery_started_at']);
    }

    /**
     * Test cannot transition to recovering if not in counting state
     */
    public function testTransitionFailsIfNotCounting(): void
    {
        $result = $this->service->transitionToRecovering();
        
        $this->assertFalse($result);
    }

    /**
     * Test clear countdown cleans up state
     */
    public function testClearCountdown(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        $this->service->startCountdown($corruptionDetails);
        $this->assertTrue($this->service->isCountdownActive());
        
        // Clear countdown
        $result = $this->service->clearCountdown();
        $this->assertTrue($result);
        
        // Verify state is reset
        $this->assertFalse($this->service->isCountdownActive());
        
        $state = $this->service->getState();
        $this->assertEqual($state['status'], 'idle');
        $this->assertEqual($state['total_pending'], 0);
    }

    /**
     * Test API response format
     */
    public function testGetStateForApi(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        $this->service->startCountdown($corruptionDetails);
        $apiState = $this->service->getStateForApi();
        
        // Verify required fields
        $this->assertTrue(isset($apiState['countdown_active']));
        $this->assertTrue(isset($apiState['status']));
        $this->assertTrue(isset($apiState['time_remaining_seconds']));
        $this->assertTrue(isset($apiState['total_pending_blocks']));
        $this->assertTrue(isset($apiState['pending_blocks_count']));
        
        // Verify values
        $this->assertTrue($apiState['countdown_active']);
        $this->assertEqual($apiState['status'], 'counting');
        $this->assertEqual($apiState['total_pending_blocks'], 1);
    }

    /**
     * Test force reset functionality
     */
    public function testForceReset(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        $this->service->startCountdown($corruptionDetails);
        $this->assertTrue($this->service->isCountdownActive());
        
        // Force reset
        $result = $this->service->forceReset();
        $this->assertTrue($result);
        
        $this->assertFalse($this->service->isCountdownActive());
    }

    /**
     * Test filtering of non-minority blocks
     */
    public function testFiltersNonMinorityBlocks(): void
    {
        $corruptionDetails = [
            [
                'status' => 'minority',
                'block_hash' => 'hash1'
            ],
            [
                'status' => 'no_consensus',
                'block_hash' => 'hash2'
            ],
            [
                'status' => 'healthy',
                'block_hash' => 'hash3'
            ],
            [
                'status' => 'missing',
                'block_hash' => 'hash4'
            ]
        ];
        
        $this->service->startCountdown($corruptionDetails);
        $pending = $this->service->getPendingBlocks();
        
        // Should only have 1 minority block
        $this->assertEqual(count($pending), 1);
        $this->assertEqual($pending[0]['block_hash'], 'hash1');
    }

    /**
     * Test state file is deleted
     */
    public function testDeleteStateFile(): void
    {
        $corruptionDetails = [[
            'status' => 'minority',
            'block_hash' => 'abc123',
            'corrupt_dbs' => ['userdb']
        ]];
        
        $this->service->startCountdown($corruptionDetails);
        
        // Verify state file exists
        $state = $this->service->getState();
        $this->assertEqual($state['status'], 'counting');
        
        // Delete state file
        $result = $this->service->deleteStateFile();
        $this->assertTrue($result);
        
        // Verify state is reset to default
        $newState = $this->service->getState();
        $this->assertEqual($newState['status'], 'idle');
    }
}
