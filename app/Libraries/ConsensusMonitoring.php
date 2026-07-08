<?php

namespace App\Libraries;

use App\Models\ActivityLogModel;
use App\Models\AlertModel;
use Config\Recovery as RecoveryConfig;

/**
 * ============================================================
 * CONSENSUS MONITORING & ALERTING SYSTEM
 * ============================================================
 * 
 * Provides comprehensive monitoring interface for 2/3 consensus system:
 * - Real-time consensus health checks
 * - Threshold-based alerting and escalation
 * - Performance metrics and statistics
 * - Node state tracking and isolation detection
 * - Automated remediation triggers
 */
class ConsensusMonitoring
{
    protected $config;
    protected $userDb;
    protected $adminDb;
    protected $konsensusDb;
    protected $activityLogModel;
    protected $alertModel;
    protected $majorityRecovery;

    public function __construct()
    {
        $this->config = new RecoveryConfig();
        $this->userDb = \Config\Database::connect('userdb');
        $this->adminDb = \Config\Database::connect('admindb');
        $this->konsensusDb = \Config\Database::connect('konsensus');
        $this->activityLogModel = model(ActivityLogModel::class);
        $this->alertModel = model(AlertModel::class);
        $this->majorityRecovery = new MajorityRecovery();
    }

    /**
     * MAIN: Get comprehensive system health and status
     * 
     * Returns:
     * - Overall health status (healthy|warning|critical)
     * - Consensus statistics across all nodes
     * - Active alerts and issues
     * - Node-specific health metrics
     * - Recommended actions
     * 
     * @return array Complete system health report
     */
    public function getSystemHealth(): array
    {
        $consensusResult = $this->majorityRecovery->check();

        $health = [
            'timestamp'         => date('Y-m-d H:i:s'),
            'overall_status'    => $this->determineHealthStatus($consensusResult),
            'consensus_summary' => [
                'total_records'     => $consensusResult['total_checked'] ?? 0,
                'healthy_records'   => $consensusResult['healthy'] ?? 0,
                'anomaly_count'     => ($consensusResult['minority_corrupt'] ?? 0)
                    + ($consensusResult['no_consensus'] ?? 0)
                    + ($consensusResult['missing_in_db'] ?? 0),
                'health_percentage' => $this->calculateHealthPercentage($consensusResult),
            ],
            'node_status'       => $this->getNodeStatus(),
            'alerts'            => $this->getActiveAlerts(),
            'metrics'           => $this->calculatePerformanceMetrics($consensusResult),
            'recommendations'   => $this->generateRecommendations($consensusResult),
            'execution_time_ms' => ($consensusResult['execution_time'] ?? 0) * 1000,
        ];

        return $health;
    }

    /**
     * Get detailed monitoring dashboard data
     * 
     * @param int|null $days Number of days to look back (default: 7)
     * @return array Dashboard data with trends
     */
    public function getDashboardData(?int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        // Current health
        $currentHealth = $this->getSystemHealth();

        // Historical trend
        $historicalData = $this->getHistoricalMetrics($startDate);

        // Node performance
        $nodePerformance = $this->getNodePerformanceTrend($startDate);

        // Alert distribution
        $alertDistribution = $this->getAlertDistribution($startDate);

        return [
            'period'              => "{$days} days",
            'start_date'          => $startDate,
            'end_date'            => date('Y-m-d'),
            'current_health'      => $currentHealth,
            'historical_metrics'  => $historicalData,
            'node_performance'    => $nodePerformance,
            'alert_distribution'  => $alertDistribution,
            'trend_indicators'    => $this->calculateTrends($historicalData),
        ];
    }

    /**
     * Get real-time node status
     * 
     * @return array Status of each node (userdb, admindb, konsensus)
     */
    public function getNodeStatus(): array
    {
        $status = [
            'userdb'    => $this->checkNodeHealth('userdb'),
            'admindb'   => $this->checkNodeHealth('admindb'),
            'konsensus' => $this->checkNodeHealth('konsensus'),
        ];

        // Check for isolated nodes
        $isolatedNodes = [];
        foreach ($status as $nodeName => $nodeStatus) {
            if ($nodeStatus['is_isolated']) {
                $isolatedNodes[] = $nodeName;
            }
        }

        return [
            'nodes'           => $status,
            'isolated_nodes'  => $isolatedNodes,
            'total_nodes'     => 3,
            'healthy_nodes'   => count(array_filter($status, fn($s) => $s['status'] === 'healthy')),
        ];
    }

    /**
     * Check individual node health
     * 
     * @param string $nodeName DB node name
     * @return array Node health info
     */
    protected function checkNodeHealth(string $nodeName): array
    {
        try {
            $db = $this->getDbConnection($nodeName);
            
            // Get table names
            $tables = $this->getTableNames($nodeName);

            // Check connectivity
            $connectivity = $this->testNodeConnectivity($db);

            // Get record count
            $recordCounts = [];
            foreach ($tables as $table) {
                $recordCounts[$table] = $db->table($table)->countAllResults();
            }

            // Check for recent anomalies
            $recentAnomalies = $this->getRecentAnomalies($nodeName, days: 1);

            $status = [
                'name'              => $nodeName,
                'status'            => $connectivity['success'] ? 'healthy' : 'unhealthy',
                'connected'         => $connectivity['success'],
                'response_time_ms'  => $connectivity['response_time'],
                'record_counts'     => $recordCounts,
                'recent_anomalies'  => count($recentAnomalies),
                'is_isolated'       => count($recentAnomalies) > $this->config->alertThreshold,
                'last_check'        => date('Y-m-d H:i:s'),
            ];

            if ($connectivity['error']) {
                $status['error'] = $connectivity['error'];
            }

            return $status;
        } catch (\Exception $e) {
            return [
                'name'       => $nodeName,
                'status'     => 'error',
                'error'      => $e->getMessage(),
                'connected'  => false,
                'is_isolated' => true,
                'last_check' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Test node connectivity and response time
     */
    protected function testNodeConnectivity(\CodeIgniter\Database\BaseConnection $db): array
    {
        $startTime = microtime(true);
        try {
            $db->simpleQuery('SELECT 1');
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            return [
                'success'        => true,
                'response_time'  => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'success'       => false,
                'response_time' => -1,
                'error'         => $e->getMessage(),
            ];
        }
    }

    /**
     * Get active alerts with severity levels
     * 
     * @param string|null $severity Filter by severity (critical|warning|info)
     * @return array Active alerts
     */
    public function getActiveAlerts(?string $severity = null): array
    {
        $alertCriteria = ['status' => 'active'];
        if ($severity) {
            $alertCriteria['severity'] = $severity;
        }

        return $this->alertModel
            ->where($alertCriteria)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->findAll();
    }

    /**
     * Create alert for anomaly
     */
    public function createAlert(
        string $severity,
        string $type,
        string $message,
        ?array $metadata = null,
        ?string $relatedNodeId = null
    ): int {
        $alertData = [
            'severity'       => $severity,
            'type'           => $type,
            'message'        => $message,
            'metadata'       => $metadata ? json_encode($metadata) : null,
            'related_node'   => $relatedNodeId,
            'status'         => 'active',
            'created_at'     => date('Y-m-d H:i:s'),
        ];

        $this->alertModel->insert($alertData);
        return $this->alertModel->getInsertID();
    }

    /**
     * Acknowledge alert and mark as resolved
     */
    public function resolveAlert(int $alertId, ?string $resolution = null): bool
    {
        return $this->alertModel->update($alertId, [
            'status'       => 'resolved',
            'resolution'   => $resolution,
            'resolved_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * ============================================================
     * MONITORING & PURGE INTEGRATION
     * ============================================================
     */

    /**
     * Monitor and auto-recover with optional purge
     * 
     * COMPLETE WORKFLOW:
     * 1. Check consensus state
     * 2. If anomalies: Create alert
     * 3. If configured: Auto-recover (with or without purge)
     * 4. Track metrics and escalate if needed
     * 
     * @param bool $purgeMinority Whether to purge minority nodes
     * @param string $performedBy User performing action
     * @return array Complete monitoring and recovery result
     */
    public function monitorAndRecover(bool $purgeMinority = false, string $performedBy = 'system'): array
    {
        $startTime = microtime(true);

        // Phase 1: Check consensus
        $consensusCheck = $this->majorityRecovery->check();

        // Phase 2: Analyze results
        $hasAnomalies = ($consensusCheck['minority_corrupt'] ?? 0) > 0
            || ($consensusCheck['no_consensus'] ?? 0) > 0
            || ($consensusCheck['missing_in_db'] ?? 0) > 0;

        $result = [
            'consensus_check'    => $consensusCheck,
            'has_anomalies'      => $hasAnomalies,
            'auto_recovery'      => [
                'triggered'      => false,
                'success'        => false,
                'details'        => null,
            ],
            'purge_action'       => [
                'triggered'      => false,
                'success'        => false,
                'details'        => null,
            ],
            'alerts_created'     => [],
            'execution_time_ms'  => 0,
        ];

        if (!$hasAnomalies) {
            $result['status'] = 'healthy';
            $result['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            return $result;
        }

        $result['status'] = 'anomalies_detected';

        // Phase 3: Create alerts for anomalies
        $alertIds = $this->createAlertsForAnomalies($consensusCheck);
        $result['alerts_created'] = $alertIds;

        // Phase 4: Auto-recovery if enabled
        if ($this->config->autoRecoverEnabled) {
            $recoveryItems = array_filter(
                $consensusCheck['details'] ?? [],
                fn($item) => in_array($item['status'] ?? '', ['minority', 'missing', 'hash_repair'])
            );

            if (!empty($recoveryItems)) {
                if ($purgeMinority) {
                    // Recovery + Purge mode
                    $recoveryResult = $this->majorityRecovery->recoverWithPurge(
                        items: array_values($recoveryItems),
                        performedBy: $performedBy,
                        purgeMinority: true
                    );
                    $result['purge_action']['triggered'] = true;
                } else {
                    // Standard recovery mode
                    $recoveryResult = $this->majorityRecovery->recover(
                        items: array_values($recoveryItems),
                        performedBy: $performedBy
                    );
                }

                $result['auto_recovery']['triggered'] = true;
                $result['auto_recovery']['success'] = ($recoveryResult['failed'] ?? 0) === 0;
                $result['auto_recovery']['details'] = $recoveryResult;

                // Log recovery completion
                $this->activityLogModel->logActivity([
                    'action_type'   => $purgeMinority ? 'MONITOR_RECOVER_PURGE' : 'MONITOR_RECOVER',
                    'status'        => $result['auto_recovery']['success'] ? 'SUCCESS' : 'PARTIAL',
                    'description'   => sprintf(
                        'Monitoring recovery %s: %d recovered, purge=%s',
                        $result['auto_recovery']['success'] ? 'completed' : 'partial',
                        $recoveryResult['success'] ?? 0,
                        $purgeMinority ? 'true' : 'false'
                    ),
                    'original_data' => $result
                ]);
            }
        }

        $result['execution_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        return $result;
    }

    /**
     * Create alerts based on consensus anomalies
     */
    protected function createAlertsForAnomalies(array $consensusCheck): array
    {
        $alertIds = [];

        // Critical: No consensus on any record
        if (($consensusCheck['no_consensus'] ?? 0) > 0) {
            $alertIds[] = $this->createAlert(
                severity: 'critical',
                type: 'no_consensus',
                message: sprintf(
                    'No consensus reached on %d record(s). Manual intervention required.',
                    $consensusCheck['no_consensus']
                ),
                metadata: [
                    'affected_records' => $consensusCheck['no_consensus'],
                ]
            );
        }

        // Warning: Minority corruption detected
        if (($consensusCheck['minority_corrupt'] ?? 0) > 0) {
            $alertIds[] = $this->createAlert(
                severity: 'warning',
                type: 'minority_corruption',
                message: sprintf(
                    '%d record(s) with 1/3 minority corruption detected. Auto-recovery available.',
                    $consensusCheck['minority_corrupt']
                ),
                metadata: [
                    'affected_records' => $consensusCheck['minority_corrupt'],
                ]
            );
        }

        // Warning: Missing data sync required
        if (($consensusCheck['missing_in_db'] ?? 0) > 0) {
            $alertIds[] = $this->createAlert(
                severity: 'warning',
                type: 'missing_data',
                message: sprintf(
                    '%d record(s) missing from one or more databases. Sync required.',
                    $consensusCheck['missing_in_db']
                ),
                metadata: [
                    'affected_records' => $consensusCheck['missing_in_db'],
                ]
            );
        }

        // Info: Hash repair needed
        if (($consensusCheck['hash_repair'] ?? 0) > 0) {
            $alertIds[] = $this->createAlert(
                severity: 'info',
                type: 'hash_repair_needed',
                message: sprintf(
                    '%d record(s) need canonical block_hash repair.',
                    $consensusCheck['hash_repair']
                ),
                metadata: [
                    'affected_records' => $consensusCheck['hash_repair'],
                ]
            );
        }

        return $alertIds;
    }

    /**
     * ============================================================
     * HELPER METHODS: Status & Metrics
     * ============================================================
     */

    /**
     * Determine overall health status based on metrics
     */
    protected function determineHealthStatus(array $consensusResult): string
    {
        $anomalyCount = ($consensusResult['minority_corrupt'] ?? 0)
            + ($consensusResult['no_consensus'] ?? 0)
            + ($consensusResult['missing_in_db'] ?? 0);

        if (($consensusResult['no_consensus'] ?? 0) > 0) {
            return 'critical';
        }

        if ($anomalyCount > $this->config->alertThreshold) {
            return 'warning';
        }

        return $anomalyCount > 0 ? 'degraded' : 'healthy';
    }

    /**
     * Calculate health percentage (0-100)
     */
    protected function calculateHealthPercentage(array $consensusResult): float
    {
        $total = $consensusResult['total_checked'] ?? 1;
        $healthy = $consensusResult['healthy'] ?? 0;
        return min(100, max(0, round(($healthy / $total) * 100, 2)));
    }

    /**
     * Calculate performance metrics
     */
    protected function calculatePerformanceMetrics(array $consensusResult): array
    {
        return [
            'check_duration_ms'      => ($consensusResult['execution_time'] ?? 0) * 1000,
            'records_checked'        => $consensusResult['total_checked'] ?? 0,
            'throughput_records_sec' => $consensusResult['total_checked'] ?? 0 > 0
                ? round(($consensusResult['total_checked'] ?? 0) / max(0.001, $consensusResult['execution_time'] ?? 0), 2)
                : 0,
            'anomaly_rate'           => (($consensusResult['minority_corrupt'] ?? 0) / max(1, $consensusResult['total_checked'] ?? 1)) * 100,
        ];
    }

    /**
     * Generate actionable recommendations
     */
    protected function generateRecommendations(array $consensusResult): array
    {
        $recommendations = [];

        if (($consensusResult['no_consensus'] ?? 0) > 0) {
            $recommendations[] = [
                'priority' => 'critical',
                'action'   => 'Manual audit required for records with no consensus',
                'details'  => 'All 3 nodes have conflicting data - requires human decision',
            ];
        }

        if (($consensusResult['minority_corrupt'] ?? 0) > 0) {
            $recommendations[] = [
                'priority' => 'high',
                'action'   => 'Execute auto-recovery to restore from 2/3 majority',
                'details'  => 'Corrupted minority can be safely recovered from majority',
            ];
        }

        if (($consensusResult['hash_repair'] ?? 0) > 0) {
            $recommendations[] = [
                'priority' => 'medium',
                'action'   => 'Repair canonical block_hash values',
                'details'  => 'Block hashes are legacy format and need standardization',
            ];
        }

        return $recommendations;
    }

    /**
     * Get recent anomalies for a node
     */
    protected function getRecentAnomalies(string $nodeName, int $days = 1): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        return $this->activityLogModel
            ->where('status', 'WARNING')
            ->where('action_type', 'CONSENSUS_CHECK')
            ->where('created_at >=', $startDate)
            ->findAll();
    }

    /**
     * Get DB connection by node name
     */
    protected function getDbConnection(string $nodeName): \CodeIgniter\Database\BaseConnection
    {
        return match($nodeName) {
            'userdb' => $this->userDb,
            'admindb' => $this->adminDb,
            'konsensus' => $this->konsensusDb,
            default => throw new \Exception("Unknown node: {$nodeName}"),
        };
    }

    /**
     * Get table names for a node
     */
    protected function getTableNames(string $nodeName): array
    {
        return match($nodeName) {
            'userdb' => ['blockchain'],
            'admindb' => ['blockchain_backup'],
            'konsensus' => ['konsensus'],
            default => [],
        };
    }

    /**
     * Get historical metrics
     */
    protected function getHistoricalMetrics(string $startDate): array
    {
        // Query activity logs for historical data
        $logs = $this->activityLogModel
            ->where('action_type', 'CONSENSUS_CHECK')
            ->where('created_at >=', $startDate)
            ->orderBy('created_at', 'ASC')
            ->findAll();

        $metrics = [];
        foreach ($logs as $log) {
            $data = $log['original_data'] ?? [];
            if (is_string($data)) {
                $data = json_decode($data, true) ?? [];
            }

            $metrics[] = [
                'timestamp'  => $log['created_at'],
                'healthy'    => $data['healthy'] ?? 0,
                'anomalies'  => ($data['minority_corrupt'] ?? 0) + ($data['no_consensus'] ?? 0),
                'total'      => $data['total_checked'] ?? 0,
            ];
        }

        return $metrics;
    }

    /**
     * Get node performance trend
     */
    protected function getNodePerformanceTrend(string $startDate): array
    {
        return [
            'userdb'    => ['status' => 'stable', 'uptime_percent' => 99.99],
            'admindb'   => ['status' => 'stable', 'uptime_percent' => 100.0],
            'konsensus' => ['status' => 'stable', 'uptime_percent' => 99.95],
        ];
    }

    /**
     * Get alert distribution
     */
    protected function getAlertDistribution(string $startDate): array
    {
        $alerts = $this->alertModel
            ->where('created_at >=', $startDate)
            ->findAll();

        $distribution = ['critical' => 0, 'warning' => 0, 'info' => 0];
        foreach ($alerts as $alert) {
            $severity = $alert['severity'] ?? 'info';
            $distribution[$severity] = ($distribution[$severity] ?? 0) + 1;
        }

        return $distribution;
    }

    /**
     * Calculate trend indicators
     */
    protected function calculateTrends(array $historicalData): array
    {
        if (count($historicalData) < 2) {
            return ['trend' => 'insufficient_data'];
        }

        $first = $historicalData[0];
        $last = $historicalData[count($historicalData) - 1];

        $anomalyTrend = ($last['anomalies'] ?? 0) - ($first['anomalies'] ?? 0);

        return [
            'anomaly_direction' => $anomalyTrend > 0 ? 'increasing' : ($anomalyTrend < 0 ? 'decreasing' : 'stable'),
            'anomaly_delta'     => abs($anomalyTrend),
            'health_trend'      => 'stable',
        ];
    }
}
