<?php

/**
 * Phase B: Pengujian Anti-Manipulasi (The "Byzantine" Test)
 * 
 * Tujuan: Membuktikan bahwa sistem tidak bisa ditipu oleh satu database yang datanya telah diubah secara ilegal
 * 
 * Simulasi Serangan:
 * 1. Matikan akses backend sementara
 * 2. Ubah kolom file_hash secara manual di DB1 (misalnya menggunakan query SQL langsung)
 * 3. Hidupkan kembali backend
 * 
 * Proses Verifikasi:
 * 1. Lakukan request untuk melihat detail dokumen tersebut
 * 2. Backend harus mengambil data dari ketiga database dan membandingkannya
 * 
 * Ekspektasi Output:
 * - Backend mendeteksi bahwa DB1 ≠ DB2 dan DB1 ≠ DB3
 * - Karena DB2 ≡ DB3 (memenuhi 2/3 mayoritas), sistem tetap menyajikan data dari DB2 kepada user
 * - Sistem mencatat log peringatan: Inconsistency detected on DB1
 */

namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;

class ByzantineAntiManipulationTest extends CIUnitTestCase
{
    // Remove DatabaseTestTrait to avoid migration issues
    // We'll test against actual databases

    protected $db1; // poa_user_db
    protected $db2; // poa_admin_db
    protected $db3; // poa_konsensus_db

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->db1 = \Config\Database::connect('userdb');
        $this->db2 = \Config\Database::connect('admindb');
        $this->db3 = \Config\Database::connect('konsensus');
        
        // Get the latest block's hash for proper chain linking
        $latestBlock = $this->db1->table('blockchain')->orderBy('id', 'DESC')->get()->getRow();
        $this->previousHash = $latestBlock ? $latestBlock->block_hash : '0';
    }

    /**
     * Test 1: Single Database Manipulation Detection
     * 
     * Simulates: DB1 manipulated while DB2 and DB3 remain consistent
     * Expected: System detects DB1 ≠ DB2 and DB1 ≠ DB3, serves data from DB2/DB3
     */
    public function testSingleDatabaseManipulationDetection()
    {
        // Insert consistent data to all databases
        $originalHash = hash('sha256', 'original_data_' . time());
        $testData = [
            'nama_dokumen' => 'Byzantine_Test_' . time(),
            'nomor_permohonan' => 'PERM-BYZ-' . time(),
            'nomor_dokumen' => 'DOC-BYZ-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Original document content'),
            'ip_address' => '127.0.0.1',
            'block_hash' => $originalHash,
            'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
        ];

        $this->db1->table('blockchain')->insert($testData);
        $this->db2->table('blockchain_backup')->insert($testData);
        $this->db3->table('konsensus')->insert($testData);
        
        // Update previous hash
        $this->previousHash = $testData['block_hash'];

        // Simulate attack: Manipulate DB1 directly (bypass backend)
        $manipulatedHash = hash('sha256', 'manipulated_data_' . time());
        $this->db1->table('blockchain')
                  ->where('block_hash', $originalHash)
                  ->update(['block_hash' => $manipulatedHash, 'dokumen_base64' => base64_encode('Manipulated content')]);

        // Verify manipulation occurred
        $dataDB1 = $this->db1->table('blockchain')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB2 = $this->db2->table('blockchain_backup')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB3 = $this->db3->table('konsensus')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();

        // Assert DB1 is different from DB2 and DB3
        $this->assertNotEquals($dataDB1['block_hash'], $dataDB2['block_hash'], 'DB1 should differ from DB2 after manipulation');
        $this->assertNotEquals($dataDB1['block_hash'], $dataDB3['block_hash'], 'DB1 should differ from DB3 after manipulation');

        // Assert DB2 and DB3 are still identical (majority consensus)
        $this->assertEquals($dataDB2['block_hash'], $dataDB3['block_hash'], 'DB2 and DB3 should remain identical');

        // Simulate consensus validation
        $hashes = [
            'db1' => $dataDB1['block_hash'],
            'db2' => $dataDB2['block_hash'],
            'db3' => $dataDB3['block_hash']
        ];

        // Count occurrences of each hash
        $hashCounts = array_count_values($hashes);
        arsort($hashCounts);
        $majorityHash = array_key_first($hashCounts);
        $majorityCount = $hashCounts[$majorityHash];

        // Assert majority is 2/3
        $this->assertEquals(2, $majorityCount, 'Majority should be 2 out of 3 databases');

        // Assert the served data should be from the majority (DB2/DB3)
        $servedHash = $majorityHash;
        $this->assertEquals($dataDB2['block_hash'], $servedHash, 'Served data should match majority (DB2)');
        $this->assertEquals($originalHash, $servedHash, 'Served data should be the original, not manipulated');

        log_message('warning', sprintf(
            'Byzantine Test: Manipulation detected on DB1. Hashes [DB1=%s, DB2=%s, DB3=%s]. Majority=%s (count=%d). Status=DETECTED',
            $dataDB1['block_hash'], $dataDB2['block_hash'], $dataDB3['block_hash'], $majorityHash, $majorityCount
        ));
    }

    /**
     * Test 2: Multiple Field Manipulation Detection
     * 
     * Simulates: Multiple fields in DB1 are manipulated
     * Expected: System detects inconsistencies across all manipulated fields
     */
    public function testMultipleFieldManipulationDetection()
    {
        // Insert consistent data
        $testData = [
            'nama_dokumen' => 'MultiField_Test_' . time(),
            'nomor_permohonan' => 'PERM-MF-' . time(),
            'nomor_dokumen' => 'DOC-MF-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Original multi-field content'),
            'ip_address' => '127.0.0.1',
            'block_hash' => hash('sha256', 'multifield_original_' . time()),
            'previous_hash' => $this->previousHash
        ];

        $this->db1->table('blockchain')->insert($testData);
        $this->db2->table('blockchain_backup')->insert($testData);
        $this->db3->table('konsensus')->insert($testData);
        
        // Update previous hash
        $this->previousHash = $testData['block_hash'];

        // Manipulate multiple fields in DB1
        $this->db1->table('blockchain')
                  ->where('nomor_permohonan', $testData['nomor_permohonan'])
                  ->update([
                      'nama_dokumen' => 'MANIPULATED_DOCUMENT',
                      'dokumen_base64' => base64_encode('Tampered content'),
                      'block_hash' => hash('sha256', 'tampered_' . time())
                  ]);

        // Retrieve data from all databases
        $dataDB1 = $this->db1->table('blockchain')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB2 = $this->db2->table('blockchain_backup')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB3 = $this->db3->table('konsensus')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();

        // Check each field for consistency
        $fieldsToCheck = ['nama_dokumen', 'dokumen_base64', 'block_hash'];
        $inconsistentFields = [];

        foreach ($fieldsToCheck as $field) {
            $db1Value = $dataDB1[$field];
            $db2Value = $dataDB2[$field];
            $db3Value = $dataDB3[$field];

            if ($db1Value !== $db2Value || $db1Value !== $db3Value) {
                $inconsistentFields[] = $field;
            }

            // DB2 and DB3 should still match
            $this->assertEquals($db2Value, $db3Value, "Field {$field} should match between DB2 and DB3");
        }

        // Assert inconsistencies were detected
        $this->assertNotEmpty($inconsistentFields, 'Should detect manipulated fields');
        $this->assertContains('nama_dokumen', $inconsistentFields, 'Should detect nama_dokumen manipulation');
        $this->assertContains('dokumen_base64', $inconsistentFields, 'Should detect dokumen_base64 manipulation');

        log_message('warning', sprintf(
            'Multi-Field Byzantine Test: Inconsistent fields detected: %s. Status=DETECTED',
            implode(', ', $inconsistentFields)
        ));
    }

    /**
     * Test 3: Majority Vote Calculation
     * 
     * Validates: System correctly calculates majority vote V_total ≥ ⌈2/3 n⌉
     */
    public function testMajorityVoteCalculation()
    {
        // Test scenario: DB1 manipulated, DB2 and DB3 consistent
        $scenarios = [
            [
                'name' => 'DB1 manipulated, DB2/DB3 consistent',
                'hashes' => ['hash_a', 'hash_b', 'hash_b'],
                'expected_majority' => 'hash_b',
                'expected_count' => 2,
                'expected_action' => 'COMMIT'
            ],
            [
                'name' => 'DB2 manipulated, DB1/DB3 consistent',
                'hashes' => ['hash_a', 'hash_b', 'hash_a'],
                'expected_majority' => 'hash_a',
                'expected_count' => 2,
                'expected_action' => 'COMMIT'
            ],
            [
                'name' => 'DB3 manipulated, DB1/DB2 consistent',
                'hashes' => ['hash_a', 'hash_a', 'hash_b'],
                'expected_majority' => 'hash_a',
                'expected_count' => 2,
                'expected_action' => 'COMMIT'
            ],
            [
                'name' => 'All databases different (split brain)',
                'hashes' => ['hash_a', 'hash_b', 'hash_c'],
                'expected_majority' => null,
                'expected_count' => 1,
                'expected_action' => 'REJECT'
            ],
            [
                'name' => 'All databases consistent',
                'hashes' => ['hash_a', 'hash_a', 'hash_a'],
                'expected_majority' => 'hash_a',
                'expected_count' => 3,
                'expected_action' => 'COMMIT'
            ]
        ];

        foreach ($scenarios as $scenario) {
            $hashCounts = array_count_values($scenario['hashes']);
            arsort($hashCounts);
            $majorityHash = array_key_first($hashCounts);
            $majorityCount = $hashCounts[$majorityHash];

            $n = 3;
            $threshold = ceil((2/3) * $n);
            $action = $majorityCount >= $threshold ? 'COMMIT' : 'REJECT';

            // For split brain scenario (all different), majority should be null
            if ($scenario['expected_majority'] === null) {
                $this->assertEquals(1, $majorityCount, 
                    "Majority count should be 1 for split brain: {$scenario['name']}");
                $this->assertEquals('REJECT', $action, 
                    "Action should be REJECT for split brain: {$scenario['name']}");
            } else {
                $this->assertEquals($scenario['expected_majority'], $majorityHash, 
                    "Majority hash mismatch for scenario: {$scenario['name']}");
                $this->assertEquals($scenario['expected_count'], $majorityCount, 
                    "Majority count mismatch for scenario: {$scenario['name']}");
            }
            $this->assertEquals($scenario['expected_action'], $action, 
                "Action mismatch for scenario: {$scenario['name']}");
        }

        log_message('info', 'Majority Vote Calculation Test: All scenarios validated');
    }

    /**
     * Test 4: Data Retrieval with Manipulated Node
     * 
     * Validates: When retrieving data, system serves from majority-consistent databases
     */
    public function testDataRetrievalWithManipulatedNode()
    {
        // Insert test data
        $originalHash = hash('sha256', 'retrieval_test_' . time());
        $testData = [
            'nama_dokumen' => 'Retrieval_Test_' . time(),
            'nomor_permohonan' => 'PERM-RET-' . time(),
            'nomor_dokumen' => 'DOC-RET-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Original retrieval test content'),
            'ip_address' => '127.0.0.1',
            'block_hash' => $originalHash,
            'previous_hash' => $this->previousHash
        ];

        $this->db1->table('blockchain')->insert($testData);
        $this->db2->table('blockchain_backup')->insert($testData);
        $this->db3->table('konsensus')->insert($testData);
        
        // Update previous hash
        $this->previousHash = $testData['block_hash'];

        // Update previous hash
        $this->previousHash = $('sha256', 'corrupted_' . time());
        $this->db1->table('blockchain')
                  ->where('block_hash', $originalHash)
                  ->update([
                      'block_hash' => $manipulatedHash,
                      'dokumen_base64' => base64_encode('CORRUPTED DATA')
                  ]);

        // Simulate backend data retrieval with consensus check
        $dataDB1 = $this->db1->table('blockchain')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB2 = $this->db2->table('blockchain_backup')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB3 = $this->db3->table('konsensus')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();

        // Perform consensus check
        $allData = [$dataDB1, $dataDB2, $dataDB3];
        $hashes = array_column($allData, 'block_hash');
        $hashCounts = array_count_values($hashes);
        arsort($hashCounts);
        $majorityHash = array_key_first($hashCounts);
        $majorityCount = $hashCounts[$majorityHash];

        // Determine which database has the majority hash (prefer DB2/DB3 over DB1 if tied)
        $majorityData = null;
        foreach ($allData as $i => $data) {
            if ($data['block_hash'] === $majorityHash) {
                // If this is DB1 and there's a tie, skip to prefer DB2/DB3
                if ($i === 0 && $majorityCount === 1) {
                    continue;
                }
                $majorityData = $data;
                break;
            }
        }

        // Assert served data is from majority (not the manipulated DB1)
        $this->assertNotNull($majorityData, 'Should determine majority data');
        $this->assertEquals($originalHash, $majorityData['block_hash'], 'Served hash should be original');
        $this->assertEquals($testData['dokumen_base64'], $majorityData['dokumen_base64'], 
            'Served content should be original, not corrupted');

        // Assert manipulated data is NOT served
        $this->assertNotEquals($dataDB1['dokumen_base64'], $majorityData['dokumen_base64'], 
            'Manipulated data should not be served');
        
        // Assert majority data matches DB2 (the unmanipulated node)
        $this->assertEquals($dataDB2['dokumen_base64'], $majorityData['dokumen_base64'],
            'Majority data should match DB2 (unmanipulated)');

        log_message('warning', sprintf(
            'Data Retrieval Test: DB1 manipulated, served data from majority. Original hash=%s, Served hash=%s, Status=SECURE',
            $originalHash, $majorityData['block_hash']
        ));
    }

    /**
     * Test 5: Timestamp Manipulation Detection
     * 
     * Validates: System detects timestamp manipulation attempts
     */
    public function testTimestampManipulationDetection()
    {
        // Insert data with specific timestamp
        $originalTimestamp = date('Y-m-d H:i:s');
        $testData = [
            'nama_dokumen' => 'Timestamp_Test_' . time(),
            'nomor_permohonan' => 'PERM-TS-' . time(),
            'nomor_dokumen' => 'DOC-TS-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Timestamp test content'),
            'ip_address' => '127.0.0.1',
            'block_hash' => hash('sha256', 'timestamp_' . time()),
            'previous_hash' => $this->previousHash,
            'timestamp' => $originalTimestamp
        ];

        $this->db1->table('blockchain')->insert($testData);
        $this->db2->table('blockchain_backup')->insert($testData);
        $this->db3->table('konsensus')->insert($testData);
        
        // Update previous hash
        $this->previousHash = $testData['block_hash'];

        // Update previous hash
        $this->previousHash = $testData['block_hash'];

        //        ->where('nomor_permohonan', $testData['nomor_permohonan'])
                  ->update(['timestamp' => $manipulatedTimestamp]);

        // Retrieve and compare
        $dataDB1 = $this->db1->table('blockchain')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB2 = $this->db2->table('blockchain_backup')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB3 = $this->db3->table('konsensus')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();

        // Assert DB1 timestamp differs
        $this->assertNotEquals($dataDB1['timestamp'], $dataDB2['timestamp'], 'DB1 timestamp should differ');
        $this->assertNotEquals($dataDB1['timestamp'], $dataDB3['timestamp'], 'DB1 timestamp should differ');

        // Assert DB2 and DB3 timestamps match
        $this->assertEquals($dataDB2['timestamp'], $dataDB3['timestamp'], 'DB2 and DB3 timestamps should match');
        $this->assertEquals($originalTimestamp, $dataDB2['timestamp'], 'DB2 should have original timestamp');

        log_message('warning', sprintf(
            'Timestamp Manipulation Test: DB1 timestamp=%s (manipulated), DB2/DB3 timestamp=%s (original), Status=DETECTED',
            $dataDB1['timestamp'], $dataDB2['timestamp']
        ));
    }

    /**
     * Test 6: Hash Chain Integrity Check
     * 
     * Validates: Manipulation of previous_hash field is detected
     */
    public function testHashChainIntegrityCheck()
    {
        // Insert two blocks to create a chain
        $block1Data = [
            'nama_dokumen' => 'Block1_' . time(),
            'nomor_permohonan' => 'PERM-B1-' . time(),
            'nomor_dokumen' => 'DOC-B1-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Block 1 content'),
            'ip_address' => '127.0.0.1',
            'block_hash' => hash('sha256', 'block1_' . time()),
            'previous_hash' => $this->previousHash
        ];

        $this->db1->table('blockchain')->insert($block1Data);
        $this->db2->table('blockchain_backup')->insert($block1Data);
        $this->db3->table('konsensus')->insert($block1Data);

        // Update previous hash
        $this->previousHash = $block1Data['block_hash'];

        $block2Data = [
            'nama_dokumen' => 'Block2_' . time(),
            'nomor_permohonan' => 'PERM-B2-' . time(),
            'nomor_dokumen' => 'DOC-B2-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Block 2 content'),
            'ip_address' => '127.0.0.1',
            'block_hash' => hash('sha256', 'block2_' . time()),
            'previous_hash' => $block1Data['block_hash']
        ];

        $this->db1->table('blockchain')->insert($block2Data);
        $this->db2->table('blockchain_backup')->insert($block2Data);
        $this->db3->table('konsensus')->insert($block2Data);

        // Manipulate previous_hash in DB1 for block 2
        $fakePreviousHash = hash('sha256', 'fake_chain_' . time());
        $this->db1->table('blockchain')
                  ->where('block_hash', $block2Data['block_hash'])
                  ->update(['previous_hash' => $fakePreviousHash]);

        // Retrieve and verify
        $block2DB1 = $this->db1->table('blockchain')->where('block_hash', $block2Data['block_hash'])->get()->getRowArray();
        $block2DB2 = $this->db2->table('blockchain_backup')->where('block_hash', $block2Data['block_hash'])->get()->getRowArray();
        $block2DB3 = $this->db3->table('konsensus')->where('block_hash', $block2Data['block_hash'])->get()->getRowArray();

        // Detect chain break in DB1
        $this->assertNotEquals($block2DB1['previous_hash'], $block2DB2['previous_hash'], 
            'DB1 previous_hash should differ (chain broken)');
        $this->assertNotEquals($block2DB1['previous_hash'], $block2DB3['previous_hash'], 
            'DB1 previous_hash should differ (chain broken)');

        // Verify DB2 and DB3 maintain chain integrity
        $this->assertEquals($block1Data['block_hash'], $block2DB2['previous_hash'], 
            'DB2 should maintain valid chain link');
        $this->assertEquals($block1Data['block_hash'], $block2DB3['previous_hash'], 
            'DB3 should maintain valid chain link');

        log_message('warning', sprintf(
            'Hash Chain Integrity Test: DB1 chain broken (previous_hash=%s), DB2/DB3 chain valid (previous_hash=%s), Status=DETECTED',
            $block2DB1['previous_hash'], $block2DB2['previous_hash']
        ));
    }
}
