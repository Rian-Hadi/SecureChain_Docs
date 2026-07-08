<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\BlockModel;

class FixBlockchainChain extends BaseCommand
{
    protected $group = 'App';
    protected $name = 'blockchain:fix-chain';
    protected $description = 'Fix blockchain chain by updating previous_hash values';
    protected $usage = 'blockchain:fix-chain';

    public function run(array $params)
    {
        $db = \Config\Database::connect('userdb');
        $blockModel = new BlockModel();

        CLI::write('Fetching all blocks...', 'yellow');
        $allBlocks = $blockModel->orderBy('id', 'ASC')->findAll();
        
        if (empty($allBlocks)) {
            CLI::write('No blocks found in database.', 'red');
            return;
        }

        CLI::write('Found ' . count($allBlocks) . ' blocks', 'yellow');
        CLI::write('Fixing blockchain chain...', 'yellow');

        $previousHash = '0';
        $fixedCount = 0;

        foreach ($allBlocks as $block) {
            $currentPreviousHash = $block['previous_hash'];
            
            // Update previous_hash to match the actual previous block's hash
            if ($currentPreviousHash !== $previousHash) {
                $db->table('blockchain')
                   ->where('id', $block['id'])
                   ->update(['previous_hash' => $previousHash]);
                
                $fixedCount++;
                CLI::write("Block ID {$block['id']}: Updated previous_hash from {$currentPreviousHash} to {$previousHash}", 'green');
            }
            
            $previousHash = $block['block_hash'];
        }

        CLI::write("Chain fixed! Updated {$fixedCount} blocks.", 'green');
        
        // Also fix in backup databases
        $this->fixDatabaseChain('admindb', 'blockchain_backup');
        $this->fixDatabaseChain('konsensus', 'konsensus');
        
        CLI::write('Blockchain chain fixed successfully in all databases!', 'green');
    }

    private function fixDatabaseChain($dbGroup, $table)
    {
        $db = \Config\Database::connect($dbGroup);
        $allBlocks = $db->table($table)->orderBy('id', 'ASC')->get()->getResultArray();
        
        if (empty($allBlocks)) {
            return;
        }

        CLI::write("Fixing chain in {$table}...", 'yellow');

        $previousHash = '0';
        $fixedCount = 0;

        foreach ($allBlocks as $block) {
            $currentPreviousHash = $block['previous_hash'];
            
            if ($currentPreviousHash !== $previousHash) {
                $db->table($table)
                   ->where('id', $block['id'])
                   ->update(['previous_hash' => $previousHash]);
                
                $fixedCount++;
            }
            
            $previousHash = $block['block_hash'];
        }

        CLI::write("Fixed {$fixedCount} blocks in {$table}", 'green');
    }
}
