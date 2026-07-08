<?php

/**
 * Phase C: Pengujian Auto-Recovery (Synchronization)
 */

namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;

class AutoRecoverySynchronizationTest extends CIUnitTestCase
{
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

    protected function calculateChecksum($db, $table)
    {
        $rows = $db->table($table)->orderBy('id', 'ASC')->get()->getResultArray();
        $fieldsToCompare = ['block_hash', 'nomor_permohonan', 'dokumen_base64'];
        $extracted = array_map(function($row) use ($fieldsToCompare) {
            return array_intersect_key($row, array_flip($fieldsToCompare));
        }, $rows);
        return md5(json_encode($extracted));
    }

    protected function synchronizeData($sourceDb, $targetDb, $sourceTable, $targetTable, $fromId)
    {
        $missingData = $sourceDb->table($sourceTable)
                                ->where('id >', $fromId)
                                ->orderBy('id', 'ASC')
                                ->get()
                                ->getResultArray();

        $syncedCount = 0;
        foreach ($missingData as $row) {
            $blockHash = $row['block_hash'];
            // Check if record already exists in target
            $existing = $targetDb->table($targetTable)
                                ->where('block_hash', $blockHash)
                                ->countAllResults();
            
            if ($existing == 0) {
                unset($row['id']);
                $targetDb->table($targetTable)->insert($row);
                $syncedCount++;
            }
        }

        return $syncedCount;
    }

    public function testLaggingNodeDetectionAndRecovery()
    {
        $initialCountDB1 = $this->db1->table('blockchain')->countAllResults();
        $initialCountDB3 = $this->db3->table('konsensus')->countAllResults();

        $numNewRecords = 10;
        for ($i = 1; $i <= $numNewRecords; $i++) {
            $testData = [
                'nama_dokumen' => 'Recovery_Test_' . $i . '_' . time(),
                'nomor_permohonan' => 'PERM-REC-' . $i . '-' . time(),
                'nomor_dokumen' => 'DOC-REC-' . $i,
                'tanggal_dokumen' => date('Y-m-d'),
                'tanggal_filing' =>$this->previousHash
                'dokumen_base64' => base64_encode('Recovery test content ' . $i),
                'ip_address' => '127.0.0.1',
                'block_hash' => hash('sha256', 'recovery_' . $i . '_' . time()),
                'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
            ];

            $this->db1->table('blockchain')->insert($testData);
            $this->db2->table('blockchain_backup')->insert($testData);
        }

        $syncedCount = $this->synchronizeData($this->db1, $this->db3, 'blockchain', 'konsensus', 0);

        $this->assertEquals($numNewRecords, $syncedCount, 'Should synchronize 10 records');

        $finalCountDB1 = $this->db1->table('blockchain')->countAllResults();
        $finalCountDB3 = $this->db3->table('konsensus')->countAllResults();
        $this->assertEquals($finalCountDB1, $finalCountDB3, 'DB3 should now have same count as DB1');

        log_message('info', sprintf('Lagging Node Recovery: Synced=%d, DB1=%d, DB3=%d, Status=RECOVERED', $syncedCount, $finalCountDB1, $finalCountDB3));
    }

    public function testHealthCheckDetectionMechanism()
    {
        $testData = [
            'nama_dokumen' => 'HealthCheck_Test_' . time(),
            'nomor_permohonan' => 'PERM-HC-' . time(),
            'nomor_dokumen' => 'DOC-HC-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' =>$this->previousHash
            'dokumen_base64' => base64_encode('Health check test content'),
            'ip_address' => '127.0.0.1',
            'block_hash' => hash('sha256', 'healthcheck_' . time()),
            'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
        ];

        $this->db1->table('blockchain')->insert($testData);
        $this->db2->table('blockchain_backup')->insert($testData);
        $this->db3->table('konsensus')->insert($testData);

        $additionalData = [
            'nama_dokumen' => 'Additional_Test_' . time(),
            'nomor_permohonan' => 'PERM-ADD-' . time(),
            'nomor_dokumen' => 'DOC-ADD-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' =>$this->previousHash
            'dokumen_base64' => base64_encode('Additional content'),
            'ip_address' => '127.0.0.1',
            'block_hash' => hash('sha256', 'additional_' . time()),
            'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
        ];

        $this->db1->table('blockchain')->insert($additionalData);
        $this->db3->table('konsensus')->insert($additionalData);

        $currentCountDB1 = $this->db1->table('blockchain')->countAllResults();
        $currentCountDB2 = $this->db2->table('blockchain_backup')->countAllResults();
        $currentCountDB3 = $this->db3->table('konsensus')->countAllResults();

        $counts = [$currentCountDB1, $currentCountDB2, $currentCountDB3];
        $countCounts = array_count_values($counts);
        arsort($countCounts);
        $majorityCount = array_key_first($countCounts);

        $laggingNodes = [];
        if ($currentCountDB1 < $majorityCount) $laggingNodes[] = 'DB1';
        if ($currentCountDB2 < $majorityCount) $laggingNodes[] = 'DB2';
        if ($currentCountDB3 < $majorityCount) $laggingNodes[] = 'DB3';

        $this->assertContains('DB2', $laggingNodes, 'DB2 should be detected as lagging');

        log_message('info', sprintf('Health Check: Counts [DB1=%d, DB2=%d, DB3=%d], Lagging=[%s]', $currentCountDB1, $currentCountDB2, $currentCountDB3, implode(', ', $laggingNodes)));
    }

    public function testIncrementalSynchronization()
    {
        for ($i = 1; $i <= 5; $i++) {
            $testData = [
                'nama_dokumen' => 'Initial_' . $i . '_' . time(),
                'nomor_permohonan' => 'PERM-INIT-' . $i,
                'nomor_dokumen' => 'DOC-INIT-' . $i,
                'tanggal_dokumen' => date('Y-m-d'),
                'tanggal_filing' =>$this->previousHash
                'dokumen_base64' => base64_encode('Initial content ' . $i),
                'ip_address' => '127.0.0.1',
                'block_hash' => hash('sha256', 'initial_' . $i . '_' . time()),
                'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
            ];

            $this->db1->table('blockchain')->insert($testData);
            $this->db2->table('blockchain_backup')->insert($testData);
            $this->db3->table('konsensus')->insert($testData);
        }

        for ($i = 6; $i <= 10; $i++) {
            $testData = [
                'nama_dokumen' => 'Incremental_' . $i . '_' . time(),
                'nomor_permohonan' => 'PERM-INC-' . $i,
                'nomor_dokumen' => 'DOC-INC-' . $i,
                'tanggal_dokumen' => date('Y-m-d'),
                'tanggal_filing' =>$this->previousHash
                'dokumen_base64' => base64_encode('Incremental content ' . $i),
                'ip_address' => '127.0.0.1',
                'block_hash' => hash('sha256', 'incremental_' . $i . '_' . time()),
                'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
            ];

            $this->db1->table('blockchain')->insert($testData);
            $this->db2->table('blockchain_backup')->insert($testData);
        }

        $syncedCount = $this->synchronizeData($this->db1, $this->db3, 'blockchain', 'konsensus', 0);

        $this->assertEquals(5, $syncedCount, 'Should sync only 5 incremental records');

        $finalCountDB1 = $this->db1->table('blockchain')->countAllResults();
        $finalCountDB3 = $this->db3->table('konsensus')->countAllResults();
        $this->assertEquals($finalCountDB1, $finalCountDB3, 'Final counts should match');

        log_message('info', sprintf('Incremental Sync: Synced=%d, DB1=%d, DB3=%d', $syncedCount, $finalCountDB1, $finalCountDB3));
    }

    public function testDataIntegrityAfterRecovery()
    {
        $testRecords = [];
        for ($i = 1; $i <= 5; $i++) {
            $testData = [
                'nama_dokumen' => 'Integrity_Rec_' . $i . '_' . time(),
                'nomor_permohonan' => 'PERM-IR-' . $i,
                'nomor_dokumen' => 'DOC-IR-' . $i,
                'tanggal_dokumen' => date('Y-m-d'),
                'tanggal_filing' =>$this->previousHash
                'dokumen_base64' => base64_encode('Integrity recovery content ' . $i),
                'ip_address' => '127.0.0.1',
                'block_hash' => hash('sha256', 'integrity_rec_' . $i . '_' . time()),
                'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
            ];

            $this->db1->table('blockchain')->insert($testData);
            $this->db2->table('blockchain_backup')->insert($testData);
            
            $testRecords[] = [
                'nomor_permohonan' => $testData['nomor_permohonan'],
                'block_hash' => $testData['block_hash'],
                'dokumen_base64' => $testData['dokumen_base64']
            ];
        }

        $syncedCount = $this->synchronizeData($this->db1, $this->db3, 'blockchain', 'konsensus', 0);

        foreach ($testRecords as $record) {
            $recoveredData = $this->db3->table('konsensus')->where('nomor_permohonan', $record['nomor_permohonan'])->get()->getRowArray();
            $this->assertNotNull($recoveredData, 'Record should exist in DB3');
            $this->assertEquals($record['block_hash'], $recoveredData['block_hash'], 'Block hash mismatch');
            $this->assertEquals($record['dokumen_base64'], $recoveredData['dokumen_base64'], 'Document content mismatch');
        }

        log_message('info', sprintf('Data Integrity: Synced=%d, Verified=%d', $syncedCount, count($testRecords)));
    }

    public function testMultipleLaggingNodesRecovery()
    {
        $numRecords = 5;
        for ($i = 1; $i <= $numRecords; $i++) {
            $testData = [
                'nama_dokumen' => 'MultiLag_' . $i . '_' . time(),
                'nomor_permohonan' => 'PERM-ML-' . $i,
                'nomor_dokumen' => 'DOC-ML-' . $i,
                'tanggal_dokumen' => date('Y-m-d'),
                'tanggal_filing' =>$this->previousHash
                'dokumen_base64' => base64_encode('Multi-lag content ' . $i),
                'ip_address' => '127.0.0.1',
                'block_hash' => hash('sha256', 'multilag_' . $i . '_' . time()),
                'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
            ];

            $this->db1->table('blockchain')->insert($testData);
        }

        $syncedCountDB2 = $this->synchronizeData($this->db1, $this->db2, 'blockchain', 'blockchain_backup', 0);
        $syncedCountDB3 = $this->synchronizeData($this->db1, $this->db3, 'blockchain', 'konsensus', 0);

        $this->assertEquals($numRecords, $syncedCountDB2, 'DB2 should recover all records');
        $this->assertEquals($numRecords, $syncedCountDB3, 'DB3 should recover all records');

        $finalCountDB1 = $this->db1->table('blockchain')->countAllResults();
        $finalCountDB2 = $this->db2->table('blockchain_backup')->countAllResults();
        $finalCountDB3 = $this->db3->table('konsensus')->countAllResults();

        $this->assertEquals($finalCountDB1, $finalCountDB2, 'DB1 and DB2 counts should match');
        $this->assertEquals($finalCountDB2, $finalCountDB3, 'DB2 and DB3 counts should match');

        log_message('info', sprintf('Multiple Lagging: DB2=%d, DB3=%d, Counts [DB1=%d, DB2=%d, DB3=%d]', $syncedCountDB2, $syncedCountDB3, $finalCountDB1, $finalCountDB2, $finalCountDB3));
    }

    public function testRecoveryPerformance()
    {
        $numRecords = 50;
        $startTime = microtime(true);

        for ($i = 1; $i <= $numRecords; $i++) {
            $testData = [
                'nama_dokumen' => 'Perf_Test_' . $i . '_' . time(),
                'nomor_permohonan' => 'PERM-PERF-' . $i,
                'nomor_dokumen' => 'DOC-PERF-' . $i,
                'tanggal_dokumen' => date('Y-m-d'),
                'tanggal_filing' =>$this->previousHash
                'dokumen_base64' => base64_encode(str_repeat('Performance test data ', 10)),
                'ip_address' => '127.0.0.1',
                'block_hash' => hash('sha256', 'perf_' . $i . '_' . time()),
                'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
            ];

            $this->db1->table('blockchain')->insert($testData);
            $this->db2->table('blockchain_backup')->insert($testData);
        }

        $insertTime = (microtime(true) - $startTime) * 1000;

        $recoveryStart = microtime(true);
        $syncedCount = $this->synchronizeData($this->db1, $this->db3, 'blockchain', 'konsensus', 0);
        $recoveryTime = (microtime(true) - $recoveryStart) * 1000;

        $this->assertEquals($numRecords, $syncedCount, 'Should sync all records');
        $this->assertLessThan(10000, $recoveryTime, 'Recovery should complete in less than 10 seconds');

        log_message('info', sprintf('Performance: Records=%d, Insert=%.2fms, Recovery=%.2fms', $numRecords, $insertTime, $recoveryTime));
    }

    public function testChecksumVerificationPostRecovery()
    {
        for ($i = 1; $i <= 15; $i++) {
            $testData = [
                'nama_dokumen' => 'Checksum_Test_' . $i . '_' . time(),
                'nomor_permohonan' => 'PERM-CS-' . $i,
                'nomor_dokumen' => 'DOC-CS-' . $i,
                'tanggal_dokumen' => date('Y-m-d'),
                'tanggal_filing' =>$this->previousHash
                'dokumen_base64' => base64_encode('Checksum test content ' . $i),
                'ip_address' => '127.0.0.1',
                'block_hash' => hash('sha256', 'checksum_' . $i . '_' . time()),
                'previous_hash' => '0000000000000000000000000000000000000000000000000000000000000000'
            ];

            $this->db1->table('blockchain')->insert($testData);
            $this->db2->table('blockchain_backup')->insert($testData);
        }

        $this->synchronizeData($this->db1, $this->db3, 'blockchain', 'konsensus', 0);

        $checksumDB1 = $this->calculateChecksum($this->db1, 'blockchain');
        $checksumDB2 = $this->calculateChecksum($this->db2, 'blockchain_backup');
        $checksumDB3 = $this->calculateChecksum($this->db3, 'konsensus');

        $this->assertEquals($checksumDB1, $checksumDB2, 'DB1 and DB2 checksums should match');
        $this->assertEquals($checksumDB2, $checksumDB3, 'DB2 and DB3 checksums should match');

        log_message('info', sprintf('Checksum Verification: DB1=%s, DB2=%s, DB3=%s', $checksumDB1, $checksumDB2, $checksumDB3));
    }
}
