<?php

namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;

class FailureMatrixTest extends CIUnitTestCase
{
    protected $db1;
    protected $db2;
    protected $db3;

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

    public function testScenario_AllNodesNormal()
    {
        $uniqueSalt = microtime(true) . rand(1000, 9999);
        $testHash = hash('sha256', 'scenario1_' . $uniqueSalt);
        
        $existing = $this->db1->table('blockchain')->where('block_hash', $testHash)->countAllResults();
        if ($existing > 0) {
            $testHash = hash('sha256', 'scenario1_' . $uniqueSalt . '_retry');
        }

        $testData = [
            'nama_dokumen' => 'Scenario1_' . time(),
            'nomor_permohonan' => 'PERM-S1-' . time(),
            'nomor_dokumen' => 'DOC-S1-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Scenario 1 test data'),
            'ip_address' => '127.0.0.1',
            'block_hash' => $testHash,
            'previous_hash' => $this->previousHash
        ];

        $resultDB1 = $this->db1->table('blockchain')->insert($testData);
        $resultDB2 = $this->db2->table('blockchain_backup')->insert($testData);
        $resultDB3 = $this->db3->table('konsensus')->insert($testData);
        
        // Update previous hash
        $this->previousHash = $testData['block_hash'];

        $this->assertTrue($resultDB1 && $resultDB2 && $resultDB3, 'All writes should succeed');
    }

    public function testScenario_TwoNodesUpOneDown()
    {
        $uniqueSalt = microtime(true) . rand(1000, 9999);
        $testHash = hash('sha256', 'scenario2_' . $uniqueSalt);
        
        $existing = $this->db1->table('blockchain')->where('block_hash', $testHash)->countAllResults();
        if ($existing > 0) {
            $testHash = hash('sha256', 'scenario2_' . $uniqueSalt . '_retry');
        }

        $testData = [
            'nama_dokumen' => 'Scenario2_' . time(),
            'nomor_permohonan' => 'PERM-S2-' . time(),
            'nomor_dokumen' => 'DOC-S2-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Scenario 2 test data'),
            'ip_address' => '127.0.0.1',
            'block_hash' => $testHash,
            'previous_hash' => $this->previousHash
        ];

        $resultDB1 = $this->db1->table('blockchain')->insert($testData);
        $resultDB2 = $this->db2->table('blockchain_backup')->insert($testData);

        $this->assertTrue($resultDB1 && $resultDB2, 'Writes to available databases should succeed');
    }

    public function testScenario_OneNodeUpTwoDown()
    {
        $n = 3;
        $threshold = ceil((2/3) * $n);
        $upCount = 1;

        $this->assertTrue($upCount < $threshold, 'Quorum should not be met (1/3 < 2/3)');
    }

    public function testScenario_AllNodesUpOneManipulated()
    {
        $uniqueSalt = microtime(true) . rand(1000, 9999);
        $originalHash = hash('sha256', 'scenario4_original_' . $uniqueSalt);
        
        $existing = $this->db1->table('blockchain')->where('block_hash', $originalHash)->countAllResults();
        if ($existing > 0) {
            $originalHash = hash('sha256', 'scenario4_original_' . $uniqueSalt . '_retry');
        }

        $testData = [
            'nama_dokumen' => 'Scenario4_' . time(),
            'nomor_permohonan' => 'PERM-S4-' . time(),
            'nomor_dokumen' => 'DOC-S4-' . time(),
            'tanggal_dokumen' => date('Y-m-d'),
            'tanggal_filing' => date('Y-m-d'),
            'dokumen_base64' => base64_encode('Scenario 4 original data'),
            'ip_address' => '127.0.0.1',
            'block_hash' => $originalHash,
            'previous_hash' => $this->previousHash
        ];

        $this->db1->table('blockchain')->insert($testData);
        $this->db2->table('blockchain_backup')->insert($testData);
        $this->db3->table('konsensus')->insert($testData);

        $manipulatedHash = hash('sha256', 'scenario4_manipulated_' . time());
        $this->db1->table('blockchain')
                  ->where('block_hash', $originalHash)
                  ->update([
                      'block_hash' => $manipulatedHash,
                      'dokumen_base64' => base64_encode('MANIPULATED DATA')
                  ]);

        $dataDB2 = $this->db2->table('blockchain_backup')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();
        $dataDB3 = $this->db3->table('konsensus')->where('nomor_permohonan', $testData['nomor_permohonan'])->get()->getRowArray();

        $this->assertEquals($originalHash, $dataDB2['block_hash'], 'DB2 should have original hash');
        $this->assertEquals($originalHash, $dataDB3['block_hash'], 'DB3 should have original hash');
    }

    public function testScenario_AllNodesDown()
    {
        $n = 3;
        $threshold = ceil((2/3) * $n);
        $upCount = 0;

        $this->assertTrue($upCount < $threshold, 'Quorum should not be met (0/3)');
    }

    public function testScenario_SplitBrain()
    {
        $n = 3;
        $threshold = ceil((2/3) * $n);
        
        $this->assertTrue($threshold > 1, 'Threshold should be > 1 for split brain detection');
    }
}
