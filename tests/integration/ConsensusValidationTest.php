<?php

/**
 * Phase A: Pengujian Konsensus & Integritas (Write Operation)
 * 
 * Tujuan: Memastikan Orchestrator pada backend mengirimkan data secara sinkron ke semua node
 * Skenario: Pengguna mengunggah dokumen PDF
 * 
 * Proses Backend:
 * 1. Generate SHA-256 Hash dari file
 * 2. Kirim query INSERT secara simultan ke DB1, DB2, dan DB3
 * 3. Lakukan verifikasi pasca-insert dengan melakukan SELECT COUNT pada ketiga database
 * 
 * Validasi: Pastikan ID transaksi dan Hash yang tersimpan di ketiga database 100% identik
 */

namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;

class ConsensusValidationTest extends CIUnitTestCase
{
    // Remove DatabaseTestTrait to avoid migration issues
    // We'll test against actual databases

    // Database connections for the three nodes
    protected $db1; // poa_user_db
    protected $db2; // poa_admin_db
    protected $db3; // poa_konsensus_db

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize database connections
        $this->db1 = \Config\Database::connect('userdb');
        $this->db2 = \Config\Database::connect('admindb');
        $this->db3 = \Config\Database::connect('konsensus');
    }

    /**
     * Test 1: Simultaneous Write to All Three Databases
     * 
     * Validates: V_total = Σ [Data_i ≡ Data_master]
     * Action: Commit jika V_total ≥ ⌈2/3 n⌉
     */
    public function testSimultaneousWriteConsensus()
    {
        // Prepare test data
        $testData = [
            'nama_dokumen' => 'Test_Document_' . time(),
            'nomor_permohonan' => 'PERM-' . time(),
            'nomor_dokumen' => 'DOC-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' =>$this->previousHash
            'dokumen_base64' => base64_encode('Test PDF content for consensus validation'),
            'ip_address' => '127.0.0.1',
            'block_hash' => hash('sha256', time() . rand()),
            'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
        ];

        // Record start time
        $startTime = microtime(true);

        // Simultaneous INSERT to all three databases
        $resultDB1 = $this->db1->table('blockchain')->insert($testData);
        $resultDB2 = $this->db2->table('blockchain_backup')->insert($testData);
        $resultDB3 = $this->db3->table('konsensus')->insert($testData);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Assert all inserts succeeded
        $this->assertTrue($resultDB1, 'Insert to DB1 (user_db) failed');
        $this->assertTrue($resultDB2, 'Insert to DB2 (admin_db) failed');
        $this->assertTrue($resultDB3, 'Insert to DB3 (konsensus_db) failed');

        // Verify data exists in all databases
        $countDB1 = $this->db1->table('blockchain')->where('block_hash', $testData['block_hash'])->countAllResults();
        $countDB2 = $this->db2->table('blockchain_backup')->where('block_hash', $testData['block_hash'])->countAllResults();
        $countDB3 = $this->db3->table('konsensus')->where('block_hash', $testData['block_hash'])->countAllResults();

        // Validation: All three databases should have the record
        $this->assertEquals(1, $countDB1, 'Data not found in DB1 after insert');
        $this->assertEquals(1, $countDB2, 'Data not found in DB2 after insert');
        $this->assertEquals(1, $countDB3, 'Data not found in DB3 after insert');

        // Calculate consensus validation score
        $n = 3; // Total number of databases
        $vTotal = ($countDB1 > 0 ? 1 : 0) + ($countDB2 > 0 ? 1 : 0) + ($countDB3 > 0 ? 1 : 0);
        $threshold = ceil((2/3) * $n);

        // Assert consensus threshold is met
        $this->assertGreaterThanOrEqual($threshold, $vTotal, 
            sprintf('Consensus validation failed: V_total=%d, Threshold=%d', $vTotal, $threshold));

        // Log the test result
        log_message('info', sprintf(
            'Consensus Validation Test: V_total=%d/%d, Threshold=%d, Execution Time=%.2fms, Status=COMMIT',
            $vTotal, $n, $threshold, $executionTime
        ));
    }

    /**
     * Test 2: Data Integrity Verification Across All Nodes
     * 
     * Validates: ID transaksi dan Hash yang tersimpan di ketiga database 100% identik
     */
    public function testDataIntegrityAcrossNodes()
    {
        // Insert test data
        $testData = [
            'nama_dokumen' => 'Integrity_Test_' . time(),
            'nomor_permohonan' => 'PERM-INT-' . time(),
            'nomor_dokumen' => 'DOC-INT-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' =>$this->previousHash
            'dokumen_base64' => base64_encode('Test data for integrity verification'),
            'ip_address' => '127.0.0.1',
            'block_hash' => hash('sha256', 'integrity_test_' . time()),
            'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
        ];

        // Insert to all databases
        $this->db1->table('blockchain')->insert($testData);
        $insertIdDB1 = $this->db1->insertID();

        $this->db2->table('blockchain_backup')->insert($testData);
        
        // Update previousHash for next test
        $this->previousHash = $testData['block_hash'];
        $insertIdDB2 = $this->db2->insertID();

        $this->db3->table('konsensus')->insert($testData);
        $insertIdDB3 = $this->db3->insertID();

        // Retrieve data from all databases
        $dataDB1 = $this->db1->table('blockchain')->where('block_hash', $testData['block_hash'])->get()->getRowArray();
        $dataDB2 = $this->db2->table('blockchain_backup')->where('block_hash', $testData['block_hash'])->get()->getRowArray();
        $dataDB3 = $this->db3->table('konsensus')->where('block_hash', $testData['block_hash'])->get()->getRowArray();

        // Verify block_hash is identical across all databases
        $this->assertEquals($testData['block_hash'], $dataDB1['block_hash'], 'Block hash mismatch in DB1');
        $this->assertEquals($testData['block_hash'], $dataDB2['block_hash'], 'Block hash mismatch in DB2');
        $this->assertEquals($testData['block_hash'], $dataDB3['block_hash'], 'Block hash mismatch in DB3');

        $this->assertEquals($dataDB1['block_hash'], $dataDB2['block_hash'], 'Block hash mismatch between DB1 and DB2');
        $this->assertEquals($dataDB2['block_hash'], $dataDB3['block_hash'], 'Block hash mismatch between DB2 and DB3');
        $this->assertEquals($dataDB1['block_hash'], $dataDB3['block_hash'], 'Block hash mismatch between DB1 and DB3');

        // Verify other critical fields are identical
        $this->assertEquals($dataDB1['nomor_permohonan'], $dataDB2['nomor_permohonan'], 'Nomor permohonan mismatch DB1-DB2');
        $this->assertEquals($dataDB2['nomor_permohonan'], $dataDB3['nomor_permohonan'], 'Nomor permohonan mismatch DB2-DB3');

        $this->assertEquals($dataDB1['dokumen_base64'], $dataDB2['dokumen_base64'], 'Document content mismatch DB1-DB2');
        $this->assertEquals($dataDB2['dokumen_base64'], $dataDB3['dokumen_base64'], 'Document content mismatch DB2-DB3');

        // Calculate checksum for verification - compare only the inserted record fields
        $fieldsToCompare = ['block_hash', 'nomor_permohonan', 'dokumen_base64', 'nama_dokumen'];
        $record1 = array_intersect_key($dataDB1, array_flip($fieldsToCompare));
        $record2 = array_intersect_key($dataDB2, array_flip($fieldsToCompare));
        $record3 = array_intersect_key($dataDB3, array_flip($fieldsToCompare));

        $checksumDB1 = md5(json_encode($record1));
        $checksumDB2 = md5(json_encode($record2));
        $checksumDB3 = md5(json_encode($record3));

        $this->assertEquals($checksumDB1, $checksumDB2, 'Checksum mismatch DB1-DB2');
        $this->assertEquals($checksumDB2, $checksumDB3, 'Checksum mismatch DB2-DB3');

        log_message('info', sprintf(
            'Data Integrity Test: Hash=%s, Checksums [DB1=%s, DB2=%s, DB3=%s], Status=IDENTICAL',
            $testData['block_hash'], $checksumDB1, $checksumDB2, $checksumDB3
        ));
    }

    /**
     * Test 3: SHA-256 Hash Generation and Validation
     * 
     * Validates: Hash generation is consistent and correct
     */
    public function testSHA256HashGeneration()
    {
        // Test hash generation with unique content to avoid collisions
        $uniqueSalt = microtime(true) . rand(1000, 9999);
        $testContent = 'Test document content for hash validation ' . $uniqueSalt;
        $expectedHash = hash('sha256', $testContent);

        // Check if hash already exists in database
        $existingDB1 = $this->db1->table('blockchain')->where('block_hash', $expectedHash)->countAllResults();
        if ($existingDB1 > 0) {
            // Generate a new hash if collision detected
            $testContent = 'Test document content for hash validation ' . $uniqueSalt . ' retry';
            $expectedHash = hash('sha256', $testContent);
        }

        // Insert with generated hash
        $testData = [
            'nama_dokumen' => 'Hash_Test_' . time(),
            'nomor_permohonan' $this->previousHash
            'nomor_dokumen' => 'DOC-HASH-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode($testContent),
            'ip_address' => '127.0.0.1',;
        
        // Update previousHash for next test
        $this->previousHash = $expectedHash
            'block_hash' => $expectedHash,
            'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
        ];

        $thUpdate previousHash for next test
        $this->previousHash = $testData['block_hash'];

        // is->db1->table('blockchain')->insert($testData);
        $this->db2->table('blockchain_backup')->insert($testData);
        $this->db3->table('konsensus')->insert($testData);

        // Verify hash length (SHA-256 produces 64 character hex string)
        $this->assertEquals(64, strlen($expectedHash), 'SHA-256 hash should be 64 characters');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $expectedHash, 'Hash should be valid hex string');

        // Verify hash is stored correctly
        $storedDataDB1 = $this->db1->table('blockchain')->where('block_hash', $expectedHash)->get()->getRowArray();
        $this->assertNotNull($storedDataDB1, 'Data not retrievable by hash');
        $this->assertEquals($expectedHash, $storedDataDB1['block_hash'], 'Hash stored incorrectly');

        log_message('info', sprintf(
            'SHA-256 Hash Test: Content length=%d, Hash=%s, Status=VALID',
            strlen($testContent), $expectedHash
        ));
    }

    /**
     * Test 4: Count Validation Post-Insert
     * 
     * Validates: SELECT COUNT returns consistent results across all databases
     */
    public function testCountValidationPostInsert()
    {
        // Get initial counts
        $initialCountDB1 = $this->db1->table('blockchain')->countAllResults();
        $initialCountDB2 = $this->db2->table('blockchain_backup')->countAllResults();
        $initialCountDB3 = $this->db3->table('konsensus')->countAllResults();

        // Insert multiple records
        $numInserts = 5;
        for ($i = 0; $i < $numInserts; $i++) {
            $testData = [$this->previousHash
                'nama_dokumen' => 'Count_Test_' . $i . '_' . time(),
                'nomor_permohonan' => 'PERM-COUNT-' . $i,
                'nomor_dokumen' => 'DOC-COUNT-' . $i,
                'tanggal_dokumen' => date('Y-m-d'),
                'tanggal_filing' => date('Y-m-d'),;
            
            // Update previousHash for next iteration
            $this->previousHash = $testData['block_hash']
                'dokumen_base64' => base64_encode('Test content ' . $i),
                'ip_address' => '127.0.0.1',
                'block_hash' => hash('sha256', 'count_test_' . $i . time()),
                'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
            ];

            $this->db1->table('blockchain')->insert($testData);
            $this->db2->table('blockchain_backup')->insert($testData);
            $this->db3->table('konsensus')->insert($testData);
        }

        // Get final counts
        $finalCountDB1 = $this->db1->table('blockchain')->countAllResults();
        $finalCountDB2 = $this->db2->table('blockchain_backup')->countAllResults();
        $finalCountDB3 = $this->db3->table('konsensus')->countAllResults();

        // Verify counts increased by the same amount
        $this->assertEquals($initialCountDB1 + $numInserts, $finalCountDB1, 'DB1 count mismatch');
        $this->assertEquals($initialCountDB2 + $numInserts, $finalCountDB2, 'DB2 count mismatch');
        $this->assertEquals($initialCountDB3 + $numInserts, $finalCountDB3, 'DB3 count mismatch');

        // Verify the increase is consistent across databases
        $increaseDB1 = $finalCountDB1 - $initialCountDB1;
        $increaseDB2 = $finalCountDB2 - $initialCountDB2;
        $increaseDB3 = $finalCountDB3 - $initialCountDB3;

        $this->assertEquals($increaseDB1, $increaseDB2, 'Count increase mismatch DB1-DB2');
        $this->assertEquals($increaseDB2, $increaseDB3, 'Count increase mismatch DB2-DB3');

        log_message('info', sprintf(
            'Count Validation Test: Inserted=%d, Increases [DB1=%d, DB2=%d, DB3=%d], Final Counts [DB1=%d, DB2=%d, DB3=%d], Status=SYNCHRONIZED',
            $numInserts, $increaseDB1, $increaseDB2, $increaseDB3, $finalCountDB1, $finalCountDB2, $finalCountDB3
        ));
    }

    /**
     * Test 5: Consensus Threshold Calculation
     * 
     * Validates: Threshold formula ⌈2/3 n⌉ is correctly calculated
     */
    public function testConsensusThresholdCalculation()
    {
        $n = 3; // Number of databases
        $threshold = ceil((2/3) * $n);
        
        $this->assertEquals(2, $threshold, 'Threshold should be 2 for n=3');

        // Test different scenarios
        $scenarios = [
            ['db1' => true, 'db2' => true, 'db3' => true, 'expected' => 'COMMIT', 'v_total' => 3],
            ['db1' => true, 'db2' => true, 'db3' => false, 'expected' => 'COMMIT', 'v_total' => 2],
            ['db1' => true, 'db2' => false, 'db3' => false, 'expected' => 'REJECT', 'v_total' => 1],
            ['db1' => false, 'db2' => false, 'db3' => false, 'expected' => 'REJECT', 'v_total' => 0],
        ];

        foreach ($scenarios as $scenario) {
            $vTotal = ($scenario['db1'] ? 1 : 0) + ($scenario['db2'] ? 1 : 0) + ($s,
            $nc $threshold
        ));
    }
}enario['db3'] ? 1 : 0);
            $action = $vTotal >= $threshold ? 'COMMIT' : 'REJECT';
            
            $this->assertEquals($scenario['expected'], $action, 
                sprintf('Action mismatch for scenario: DB1=%s, DB2=%s, DB3=%s', 
                    $scenario['db1'], $scenario['db2'], $scenario['db3']));
        }

        log_message('info', sprintf(
            'Consensus Threshold Test: n=%d, Threshold=%d, All scenarios validated',
            $n, $threshold
        ));
    }
}
