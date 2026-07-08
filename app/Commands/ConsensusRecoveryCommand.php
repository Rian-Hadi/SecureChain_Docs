<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\MajorityRecovery;
use App\Libraries\ConsensusMonitoring;

/**
 * ============================================================
 * CONSENSUS RECOVERY COMMAND LINE INTERFACE
 * ============================================================
 * 
 * CLI tool for managing 2/3 majority consensus & recovery system
 * 
 * Commands:
 * - php spark consensus:check         Check consensus status
 * - php spark consensus:recover       Recover from majority
 * - php spark consensus:recover-purge Recover + Purge minority
 * - php spark consensus:monitor       Real-time monitoring
 * - php spark consensus:health        System health report
 * - php spark consensus:alerts        Show active alerts
 * - php spark consensus:rollback      Rollback recovery operation
 */
class ConsensusRecoveryCommand extends BaseCommand
{
    protected $group = 'Consensus';
    protected $name = 'consensus';
    protected $description = '2/3 Majority Consensus & Recovery System Management';
    protected $usage = 'consensus <action> [options]';
    protected $arguments = [
        'action' => 'Action to perform: check|recover|monitor|health|alerts|rollback'
    ];
    protected $options = [
        '-p' => 'Enable purge_minority flag (only with recover)',
        '-d' => 'Dry-run mode (no actual changes)',
        '-v' => 'Verbose output',
    ];

    public function run(array $params = [])
    {
        $action = array_shift($params) ?? 'help';

        match($action) {
            'check' => $this->checkConsensus($params),
            'recover' => $this->recover($params),
            'recover-purge' => $this->recoverWithPurge($params),
            'monitor' => $this->monitor($params),
            'health' => $this->getHealth($params),
            'alerts' => $this->showAlerts($params),
            'rollback' => $this->rollback($params),
            'help' => $this->showHelp(),
            default => CLI::error("Unknown action: {$action}"),
        };
    }

    /**
     * ACTION 1: Check consensus status
     * 
     * Usage: php spark consensus:check [-v]
     * 
     * Shows:
     * - Overall consensus status
     * - Healthy vs anomalous records
     * - Specific anomalies detected
     */
    protected function checkConsensus(array $params = []): void
    {
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');
        CLI::write('   2/3 MAJORITY CONSENSUS CHECK', 'cyan');
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');

        $verbose = in_array('-v', $params) || in_array('--verbose', $params);

        try {
            $recovery = new MajorityRecovery();
            $startTime = microtime(true);
            $result = $recovery->check();
            $executionTime = microtime(true) - $startTime;

            // Display results
            CLI::write("\n📊 CONSENSUS CHECK RESULTS\n", 'green');
            CLI::write("  Total Records Checked: " . CLI::color($result['total_checked'], 'yellow'));
            CLI::write("  Healthy Records:       " . CLI::color($result['healthy'], 'green'));
            CLI::write("  Minority Corrupt:      " . CLI::color($result['minority_corrupt'], 'yellow'));
            CLI::write("  No Consensus:          " . CLI::color($result['no_consensus'], 'red'));
            CLI::write("  Missing Data:          " . CLI::color($result['missing_in_db'], 'yellow'));
            CLI::write("  Hash Repair Needed:    " . CLI::color($result['hash_repair'], 'yellow'));

            $healthPercent = $result['total_checked'] > 0
                ? round(($result['healthy'] / $result['total_checked']) * 100, 2)
                : 0;
            $healthColor = $healthPercent === 100 ? 'green' : ($healthPercent >= 80 ? 'yellow' : 'red');
            CLI::write("\n  Health Percentage:     " . CLI::color($healthPercent . '%', $healthColor));
            CLI::write("  Execution Time:        " . CLI::color(round($executionTime * 1000, 2) . 'ms', 'cyan'));

            // Show anomalies if verbose
            if ($verbose && !empty($result['details'])) {
                CLI::write("\n📋 DETAILED ANOMALIES\n", 'yellow');
                foreach ($result['details'] as $detail) {
                    CLI::write("  • " . $detail['identifier'] . " (" . $detail['record_key'] . ")");
                    CLI::write("    Status: " . $detail['status']);
                    if (!empty($detail['corrupt_dbs'])) {
                        CLI::write("    Corrupt DBs: " . implode(', ', $detail['corrupt_dbs']));
                    }
                    if (!empty($detail['recommendation'])) {
                        CLI::write("    Recommendation: " . $detail['recommendation']);
                    }
                    CLI::write("");
                }
            }

            // Overall status
            $anomalyCount = $result['minority_corrupt'] + $result['no_consensus']
                + $result['missing_in_db'];

            if ($anomalyCount === 0) {
                CLI::write("\n✅ " . CLI::color("CONSENSUS OK", 'green') . " - All records in consensus\n");
            } elseif ($result['no_consensus'] > 0) {
                CLI::write("\n❌ " . CLI::color("CRITICAL ISSUES DETECTED", 'red') . " - Manual intervention required\n");
            } else {
                CLI::write("\n⚠️  " . CLI::color("ANOMALIES DETECTED", 'yellow') . " - Auto-recovery available\n");
            }
        } catch (\Exception $e) {
            CLI::error("Error during consensus check: " . $e->getMessage());
        }
    }

    /**
     * ACTION 2: Recover from 2/3 majority
     * 
     * Usage: php spark consensus:recover [-d] [-v]
     * 
     * Recovers minority nodes from majority consensus
     * Use -d flag for dry-run (no actual changes)
     */
    protected function recover(array $params = []): void
    {
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');
        CLI::write('   2/3 MAJORITY AUTO-RECOVERY', 'cyan');
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');

        $dryRun = in_array('-d', $params) || in_array('--dry-run', $params);
        $verbose = in_array('-v', $params) || in_array('--verbose', $params);

        if ($dryRun) {
            CLI::write("\n" . CLI::color('⚠️  DRY-RUN MODE - No actual changes will be made', 'yellow') . "\n");
        }

        try {
            // First check consensus
            $recovery = new MajorityRecovery();
            $checkResult = $recovery->check();

            // Filter items to recover
            $toRecover = array_filter(
                $checkResult['details'] ?? [],
                fn($item) => in_array($item['status'] ?? '', ['minority', 'missing', 'hash_repair'])
            );

            if (empty($toRecover)) {
                CLI::write("\n✅ No items require recovery\n");
                return;
            }

            CLI::write("\n🔄 RECOVERY OPERATION\n", 'green');
            CLI::write("  Items to Recover: " . CLI::color(count($toRecover), 'yellow'));
            CLI::write("");

            // Perform recovery
            if ($dryRun) {
                CLI::write(CLI::color("DRY-RUN: Would recover", 'yellow'));
            } else {
                // Confirm before proceeding
                if (!$this->confirm('Proceed with recovery?')) {
                    CLI::write("\nRecovery cancelled.");
                    return;
                }

                $result = $recovery->recover(array_values($toRecover), performedBy: 'cli_user');

                CLI::write("\n📊 RECOVERY RESULTS\n", 'green');
                CLI::write("  Total Attempted: " . $result['total_attempted']);
                CLI::write("  Successful:      " . CLI::color($result['success'], 'green'));
                CLI::write("  Failed:          " . CLI::color($result['failed'], $result['failed'] > 0 ? 'red' : 'green'));
                CLI::write("  Skipped:         " . $result['skipped']);

                if ($result['failed'] === 0) {
                    CLI::write("\n✅ " . CLI::color("RECOVERY COMPLETED SUCCESSFULLY", 'green') . "\n");
                } else {
                    CLI::write("\n⚠️  " . CLI::color("RECOVERY COMPLETED WITH ERRORS", 'yellow') . "\n");
                }

                if ($verbose && !empty($result['details'])) {
                    CLI::write("📋 RECOVERY DETAILS\n", 'yellow');
                    foreach ($result['details'] as $detail) {
                        $status = ($detail['success'] ?? false) ? '✓' : '✗';
                        CLI::write("  {$status} " . ($detail['record_key'] ?? 'unknown'));
                    }
                    CLI::write("");
                }
            }
        } catch (\Exception $e) {
            CLI::error("Recovery error: " . $e->getMessage());
        }
    }

    /**
     * ACTION 3: Recover + Purge minority nodes
     * 
     * Usage: php spark consensus:recover-purge [-d] [-v]
     * 
     * Performs recovery AND purges corrupted minority node data
     * WARNING: Purge is destructive - use with caution
     */
    protected function recoverWithPurge(array $params = []): void
    {
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');
        CLI::write('   2/3 MAJORITY RECOVERY + MINORITY PURGE', 'cyan');
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');

        $dryRun = in_array('-d', $params) || in_array('--dry-run', $params);

        CLI::write("\n" . CLI::color('⚠️  WARNING: PURGE WILL DELETE DATA FROM MINORITY NODES', 'red'));
        CLI::write(CLI::color('This action is DESTRUCTIVE and cannot be easily undone', 'red'));

        if ($dryRun) {
            CLI::write(CLI::color('\nDRY-RUN MODE - No actual changes will be made', 'yellow'));
        }

        // Double confirmation
        if (!$this->confirm('\nDo you understand the consequences?')) {
            CLI::write("\nOperation cancelled.");
            return;
        }

        if (!$dryRun && !$this->confirm('FINAL CONFIRMATION: Proceed with recovery + purge?')) {
            CLI::write("\nOperation cancelled.");
            return;
        }

        try {
            $recovery = new MajorityRecovery();
            $checkResult = $recovery->check();

            $toRecover = array_filter(
                $checkResult['details'] ?? [],
                fn($item) => in_array($item['status'] ?? '', ['minority', 'missing', 'hash_repair'])
            );

            if (empty($toRecover)) {
                CLI::write("\n✅ No items require recovery\n");
                return;
            }

            CLI::write("\n🔄 RECOVERY + PURGE OPERATION\n", 'green');

            if ($dryRun) {
                CLI::write(CLI::color("DRY-RUN: Would recover and purge", 'yellow'));
            } else {
                $result = $recovery->recoverWithPurge(
                    items: array_values($toRecover),
                    performedBy: 'cli_admin',
                    purgeMinority: true
                );

                CLI::write("\n📊 OPERATION RESULTS\n", 'green');
                CLI::write("  Recovery Success:  " . CLI::color($result['success'] ?? 0, 'green'));
                CLI::write("  Records Purged:    " . CLI::color($result['total_purged_records'] ?? 0, 'yellow'));

                if (($result['purge_results']['purge_failed'] ?? 0) > 0) {
                    CLI::write("  Purge Failures:    " . CLI::color($result['purge_results']['purge_failed'], 'red'));
                }

                if (($result['success'] ?? 0) > 0 && ($result['total_purged_records'] ?? 0) > 0) {
                    CLI::write("\n✅ " . CLI::color("RECOVERY + PURGE COMPLETED", 'green') . "\n");
                } else {
                    CLI::write("\n⚠️  " . CLI::color("OPERATION COMPLETED WITH WARNINGS", 'yellow') . "\n");
                }
            }
        } catch (\Exception $e) {
            CLI::error("Operation error: " . $e->getMessage());
        }
    }

    /**
     * ACTION 4: Real-time monitoring dashboard
     * 
     * Usage: php spark consensus:monitor
     */
    protected function monitor(array $params = []): void
    {
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');
        CLI::write('   REAL-TIME MONITORING DASHBOARD', 'cyan');
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');

        try {
            $monitoring = new ConsensusMonitoring();
            $health = $monitoring->getSystemHealth();

            // Overall status
            $statusColor = match($health['overall_status']) {
                'healthy' => 'green',
                'warning' => 'yellow',
                'critical' => 'red',
                default => 'white',
            };
            $statusIcon = match($health['overall_status']) {
                'healthy' => '✅',
                'warning' => '⚠️ ',
                'critical' => '❌',
                default => '❓',
            };

            CLI::write("\n$statusIcon Overall Status: " . CLI::color(strtoupper($health['overall_status']), $statusColor));
            CLI::write("Timestamp: " . $health['timestamp']);

            // Consensus summary
            CLI::write("\n📊 CONSENSUS SUMMARY\n", 'cyan');
            $summary = $health['consensus_summary'];
            CLI::write("  Total Records:      " . $summary['total_records']);
            CLI::write("  Healthy:            " . CLI::color($summary['healthy_records'], 'green'));
            CLI::write("  Anomalies:          " . CLI::color($summary['anomaly_count'], 'yellow'));
            CLI::write("  Health:             " . CLI::color($summary['health_percentage'] . '%', $summary['health_percentage'] >= 90 ? 'green' : 'yellow'));

            // Node status
            CLI::write("\n🖥️  NODE STATUS\n", 'cyan');
            foreach ($health['node_status']['nodes'] as $nodeName => $nodeStatus) {
                $statusChar = $nodeStatus['status'] === 'healthy' ? '✓' : '✗';
                $statusColor = $nodeStatus['status'] === 'healthy' ? 'green' : 'red';
                CLI::write("  $statusChar " . CLI::color($nodeName, $statusColor) . " - " . $nodeStatus['status']);
                if ($nodeStatus['connected']) {
                    CLI::write("    Response: " . $nodeStatus['response_time_ms'] . "ms");
                }
            }

            // Active alerts
            if (!empty($health['alerts'])) {
                CLI::write("\n🚨 ACTIVE ALERTS\n", 'red');
                foreach ($health['alerts'] as $alert) {
                    CLI::write("  • [" . strtoupper($alert['severity']) . "] " . $alert['message']);
                }
            } else {
                CLI::write("\n✅ No active alerts");
            }

            // Recommendations
            if (!empty($health['recommendations'])) {
                CLI::write("\n💡 RECOMMENDATIONS\n", 'yellow');
                foreach ($health['recommendations'] as $rec) {
                    CLI::write("  • [" . strtoupper($rec['priority']) . "] " . $rec['action']);
                }
            }

            CLI::write("\n");
        } catch (\Exception $e) {
            CLI::error("Monitoring error: " . $e->getMessage());
        }
    }

    /**
     * ACTION 5: Get detailed health report
     * 
     * Usage: php spark consensus:health
     */
    protected function getHealth(array $params = []): void
    {
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');
        CLI::write('   SYSTEM HEALTH REPORT', 'cyan');
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');

        try {
            $monitoring = new ConsensusMonitoring();
            $dashboardData = $monitoring->getDashboardData(days: 7);

            CLI::write("\n📈 7-DAY HEALTH TREND\n", 'green');
            $metrics = $dashboardData['historical_metrics'];
            if (!empty($metrics)) {
                CLI::write("  First Record:  " . $metrics[0]['timestamp'] . " - Health: " . round(($metrics[0]['healthy'] / max(1, $metrics[0]['total'])) * 100, 2) . "%");
                $last = end($metrics);
                CLI::write("  Latest Record: " . $last['timestamp'] . " - Health: " . round(($last['healthy'] / max(1, $last['total'])) * 100, 2) . "%");
            }

            CLI::write("\n" . print_r($dashboardData['current_health'], true));
        } catch (\Exception $e) {
            CLI::error("Health report error: " . $e->getMessage());
        }
    }

    /**
     * ACTION 6: Show active alerts
     * 
     * Usage: php spark consensus:alerts
     */
    protected function showAlerts(array $params = []): void
    {
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');
        CLI::write('   ACTIVE ALERTS', 'cyan');
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');

        try {
            $monitoring = new ConsensusMonitoring();
            $alerts = $monitoring->getActiveAlerts();

            if (empty($alerts)) {
                CLI::write("\n✅ No active alerts\n");
                return;
            }

            CLI::write("\n🚨 " . count($alerts) . " ALERT(S) ACTIVE\n", 'red');
            foreach ($alerts as $alert) {
                $severityColor = match($alert['severity']) {
                    'critical' => 'red',
                    'warning' => 'yellow',
                    default => 'cyan',
                };
                CLI::write("\n  [" . strtoupper($alert['type']) . "] " . CLI::color("(" . $alert['severity'] . ")", $severityColor));
                CLI::write("  Message: " . $alert['message']);
                CLI::write("  Created: " . $alert['created_at']);
            }

            CLI::write("\n");
        } catch (\Exception $e) {
            CLI::error("Alert error: " . $e->getMessage());
        }
    }

    /**
     * ACTION 7: Rollback recovery operation
     * 
     * Usage: php spark consensus:rollback <recovery_history_id>
     */
    protected function rollback(array $params = []): void
    {
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');
        CLI::write('   ROLLBACK RECOVERY OPERATION', 'cyan');
        CLI::write('═══════════════════════════════════════════════════════', 'cyan');

        $historyId = array_shift($params);
        if (!$historyId || !is_numeric($historyId)) {
            CLI::error("Recovery history ID required: php spark consensus:rollback <id>");
            return;
        }

        if (!$this->confirm("Rollback recovery #$historyId?")) {
            CLI::write("\nRollback cancelled.");
            return;
        }

        try {
            $recovery = new MajorityRecovery();
            $result = $recovery->rollback((int)$historyId, performedBy: 'cli_admin');

            if ($result['success']) {
                CLI::write("\n✅ " . CLI::color("ROLLBACK SUCCESSFUL", 'green') . "\n");
                CLI::write($result['message']);
            } else {
                CLI::error($result['error']);
            }
        } catch (\Exception $e) {
            CLI::error("Rollback error: " . $e->getMessage());
        }
    }

    /**
     * Show help message
     */
    public function showHelp(): void
    {
        CLI::write("\n" . CLI::color("CONSENSUS RECOVERY CLI", 'cyan') . "\n");
        CLI::write("Manage the 2/3 Majority Consensus & Recovery System\n");

        CLI::write(CLI::color("\nUSAGE:\n", 'green'));
        CLI::write("  php spark consensus:<action> [options]\n");

        CLI::write(CLI::color("\nACTIONS:\n", 'green'));
        CLI::write("  check          Check current consensus status");
        CLI::write("  recover        Recover corrupt data from majority (2/3)");
        CLI::write("  recover-purge  Recover + purge minority nodes (DESTRUCTIVE)");
        CLI::write("  monitor        Real-time monitoring dashboard");
        CLI::write("  health         Detailed health report (7-day trend)");
        CLI::write("  alerts         Show active alerts");
        CLI::write("  rollback ID    Rollback a previous recovery operation\n");

        CLI::write(CLI::color("OPTIONS:\n", 'green'));
        CLI::write("  -d, --dry-run   Perform dry-run (no actual changes)");
        CLI::write("  -v, --verbose   Show detailed output");
        CLI::write("  -h, --help      Show this help message\n");

        CLI::write(CLI::color("EXAMPLES:\n", 'green'));
        CLI::write("  # Check consensus status");
        CLI::write("  php spark consensus:check -v\n");

        CLI::write("  # Perform recovery (with confirmation)");
        CLI::write("  php spark consensus:recover\n");

        CLI::write("  # Dry-run recovery + purge");
        CLI::write("  php spark consensus:recover-purge -d\n");

        CLI::write("  # Show monitoring dashboard");
        CLI::write("  php spark consensus:monitor\n");

        CLI::write("  # Rollback recovery #123");
        CLI::write("  php spark consensus:rollback 123\n");
    }

    /**
     * Helper: Confirm action with user
     */
    protected function confirm(string $message): bool
    {
        return CLI::prompt($message . " [yes/no]") === 'yes';
    }
}
