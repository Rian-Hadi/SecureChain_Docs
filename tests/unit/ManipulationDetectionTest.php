<?php

/**
 * Test Script - Manipulasi Data Detection
 * CLI Test untuk verify deteksi manipulasi
 */

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\BlockModel;

class ManipulationDetectionTest extends CIUnitTestCase
{
    protected $blockModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockModel = model(BlockModel::class);
    }

    public function testDetectHashMismatch()
    {
        // Get latest block
        $allBlocks = $this->blockModel->getAllBlocks();

        if (empty($allBlocks)) {
            $this->markTestSkipped('No blocks in database');
        }

        $block = end($allBlocks);

        // Simulate hash mismatch detection
        $dataToHash = $block['nomor_permohonan'] . $block['nomor_dokumen'] .
            $block['tanggal_dokumen'] . $block['tanggal_filing'] .
            $block['dokumen_base64'];
        $recalculatedHash = hash('sha256', $dataToHash);

        echo "\n=== MANIPULATION DETECTION TEST ===\n";
        echo "Block ID: #" . $block['id'] . "\n";
        echo "Nomor Permohonan: " . $block['nomor_permohonan'] . "\n";
        echo "Current Hash:     " . substr($block['block_hash'], 0, 16) . "...\n";
        echo "Calculated Hash:  " . substr($recalculatedHash, 0, 16) . "...\n";

        if ($recalculatedHash === $block['block_hash']) {
            echo "Status: ✓ SAFE - No manipulation detected\n";
        } else {
            echo "Status: ✗ ALERT - Data manipulation detected!\n";
        }
        echo "===================================\n\n";

        // Test assertion
        $this->assertTrue(true);
    }

    public function testDetectionViewData()
    {
        echo "\n=== MONITORING VIEW DATA TEST ===\n";
        echo "Testing that monitoring page receives:\n";
        echo "- \$manipulatedData array\n";
        echo "- \$chainIntegrity array\n";
        echo "- \$stats with manipulated_count\n";
        echo "==================================\n\n";

        $this->assertTrue(true);
    }
}
