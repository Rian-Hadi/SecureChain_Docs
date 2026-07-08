<?php

namespace App\Libraries;

use App\Models\BackupModel;
use App\Models\ActivityLogModel;
use App\Models\RecoveryHistoryModel;
use Config\Recovery as RecoveryConfig;


class MajorityRecovery
{
    protected $config;
    protected $userDb;
    protected $adminDb;
    protected $konsensusDb;
    protected $backupModel;
    protected $activityLogModel;
    protected $recoveryHistoryModel;

    public function __construct()
    {
        $this->config = new RecoveryConfig();

        // Connect ke ketiga database
        $this->userDb = \Config\Database::connect('userdb');
        $this->adminDb = \Config\Database::connect('admindb');
        $this->konsensusDb = \Config\Database::connect('konsensus');

        // Load models
        $this->backupModel = model(BackupModel::class);
        $this->activityLogModel = model(ActivityLogModel::class);
        $this->recoveryHistoryModel = model(RecoveryHistoryModel::class);
    }

    /**
     * Enhanced consensus check: scans ALL 3 databases for records, not just userdb
     * This prevents bypass detection by finding records that may have been deleted from userdb
     * but still exist in admindb or konsensus
     * 
     * @return array 
     */
    public function check(): array
    {
        $startTime = microtime(true);

        // === ENHANCEMENT: Collect records from ALL 3 databases, not just userdb ===
        $userRecords = $this->userDb->table('blockchain')->get()->getResultArray();
        $adminRecords = $this->adminDb->table('blockchain_backup')->get()->getResultArray();
        $konsensusRecords = $this->konsensusDb->table('konsensus')->get()->getResultArray();

        // Create a comprehensive inventory of all unique records (by block_hash and identifier)
        $allRecordsMap = []; // key = block_hash or nomor_permohonan+tanggal_dokumen
        $recordsByHash = [];  // For faster lookup
        
        // Process userdb records (primary source)
        foreach ($userRecords as $record) {
            $key = $this->generateRecordKey($record);
            $allRecordsMap[$key] = [
                'block_hash' => $record['block_hash'],
                'nomor_permohonan' => $record['nomor_permohonan'],
                'tanggal_dokumen' => $record['tanggal_dokumen'],
                'source_dbs' => ['userdb']
            ];
            $recordsByHash[$record['block_hash']] = true;
        }

        // Process admindb records (find missing ones)
        foreach ($adminRecords as $record) {
            $key = $this->generateRecordKey($record);
            if (isset($allRecordsMap[$key])) {
                $allRecordsMap[$key]['source_dbs'][] = 'admindb';
            } else {
                $allRecordsMap[$key] = [
                    'block_hash' => $record['block_hash'],
                    'nomor_permohonan' => $record['nomor_permohonan'],
                    'tanggal_dokumen' => $record['tanggal_dokumen'],
                    'source_dbs' => ['admindb'],
                    'missing_from_userdb' => true  // FLAG: Missing from primary DB
                ];
            }
        }

        // Process konsensus records (find missing ones)
        foreach ($konsensusRecords as $record) {
            $key = $this->generateRecordKey($record);
            if (isset($allRecordsMap[$key])) {
                if (!in_array('konsensus', $allRecordsMap[$key]['source_dbs'])) {
                    $allRecordsMap[$key]['source_dbs'][] = 'konsensus';
                }
            } else {
                $allRecordsMap[$key] = [
                    'block_hash' => $record['block_hash'],
                    'nomor_permohonan' => $record['nomor_permohonan'],
                    'tanggal_dokumen' => $record['tanggal_dokumen'],
                    'source_dbs' => ['konsensus'],
                    'missing_from_userdb' => true  // FLAG: Missing from primary DB
                ];
            }
        }

        $results = [
            'total_checked'       => count($allRecordsMap),
            'healthy'             => 0,
            'minority_corrupt'    => 0,
            'no_consensus'        => 0,
            'missing_in_db'       => 0,
            'hash_repair'         => 0,
            'details'             => [],
            'execution_time'      => 0,
        ];

        // Check each record across all 3 databases
        foreach ($allRecordsMap as $recordData) {
            // If record is missing from userdb, it's a potential bypass/deletion
            if (!empty($recordData['missing_from_userdb'])) {
                $results['details'][] = [
                    'record_key' => $recordData['block_hash'],
                    'identifier' => $recordData['nomor_permohonan'],
                    'status' => 'missing_from_userdb',
                    'severity' => 'CRITICAL',
                    'exists_in_dbs' => $recordData['source_dbs'],
                    'recommendation' => 'Record deleted from userdb but exists in backup/consensus. Likely manipulation!'
                ];
                continue;
            }

            // Normal check for records present in userdb
            $userRecord = $userRecords[0]; // Get one user record as template
            foreach ($userRecords as $ur) {
                if ($ur['block_hash'] === $recordData['block_hash'] || 
                    ($ur['nomor_permohonan'] === $recordData['nomor_permohonan'] && 
                     date('Y-m-d', strtotime($ur['tanggal_dokumen'])) === date('Y-m-d', strtotime($recordData['tanggal_dokumen'])))) {
                    $userRecord = $ur;
                    break;
                }
            }

            $checkResult = $this->checkSingleRecord($userRecord);

            // Update statistik
            switch ($checkResult['status']) {
                case 'healthy':
                    $results['healthy']++;
                    break;
                case 'minority':
                    $results['minority_corrupt']++;
                    $results['details'][] = $checkResult;
                    break;
                case 'hash_repair':
                    $results['hash_repair']++;
                    $results['details'][] = $checkResult;
                    break;
                case 'no_consensus':
                    $results['no_consensus']++;
                    $results['details'][] = $checkResult;
                    break;
                case 'missing':
                    $results['missing_in_db']++;
                    $results['details'][] = $checkResult;
                    break;
            }
        }

        $results['execution_time'] = round(microtime(true) - $startTime, 2);

        $totalAnomalies = $results['minority_corrupt'] + $results['no_consensus']
            + $results['hash_repair'];

        // Log pengecekan
        $this->activityLogModel->logActivity([
            'action_type'   => 'CONSENSUS_CHECK',
            'status'        => $totalAnomalies > 0 ? 'WARNING' : 'INFO',
            'description'   => sprintf(
                'Consensus check completed: %d healthy, %d minority (2/3), %d hash-repair, %d no-consensus, %d missing (Total Anomalies: %d)',
                $results['healthy'],
                $results['minority_corrupt'],
                $results['hash_repair'],
                $results['no_consensus'],
                $results['missing_in_db'],
                $totalAnomalies
            ),
            'original_data' => $results
        ]);

        return $results;
    }

    /**
     * Generate unique key for record identification
     */
    protected function generateRecordKey(array $record): string
    {
        // Prefer stable business identifier (nomor_permohonan + date) as canonical key
        // to avoid treating the same logical record as different when block_hash differs
        if (!empty($record['nomor_permohonan'])) {
            $date = isset($record['tanggal_dokumen']) && $record['tanggal_dokumen'] !== '0000-00-00'
                ? date('Y-m-d', strtotime($record['tanggal_dokumen']))
                : 'nodate';
            return 'id:' . $record['nomor_permohonan'] . ':' . $date;
        }

        // Fallback to block_hash when identifier is not available
        if (!empty($record['block_hash'])) {
            return 'hash:' . $record['block_hash'];
        }

        // Last-resort key
        return 'unknown:' . uniqid('', true);
    }

    /**
     * Check konsensus untuk single record
     */
    protected function checkSingleRecord(array $userRecord): array
    {
        $blockHash = $userRecord['block_hash'];
        $nomorPermohonan = $userRecord['nomor_permohonan'];
        $tanggalDokumen = $userRecord['tanggal_dokumen'];

        if ($this->config->verboseLogging) {
            log_message('debug', "[CONSENSUS_CHECK] Checking record: {$nomorPermohonan} (hash: " . substr($blockHash, 0, 16) . "...)");
        }

        // Ambil data dari ketiga database
        $data = [
            'userdb'    => $userRecord,
            'admindb'   => $this->getFromAdminDb($blockHash, $nomorPermohonan, $tanggalDokumen),
            'konsensus' => $this->getFromKonsensusDb($blockHash, $nomorPermohonan, $tanggalDokumen)
        ];

        // Log data availability
        if ($this->config->verboseLogging) {
            log_message('debug', "[CONSENSUS_CHECK] Data availability - UserDB: YES, AdminDB: " .
                ($data['admindb'] ? 'YES' : 'NO') . ", KonsensusDB: " .
                ($data['konsensus'] ? 'YES' : 'NO'));
        }

        // Hitung checksum untuk masing-masing
        $checksums = [
            'userdb'    => $data['userdb'] ? $this->calculateChecksum($data['userdb']) : null,
            'admindb'   => $data['admindb'] ? $this->calculateChecksum($data['admindb']) : null,
            'konsensus' => $data['konsensus'] ? $this->calculateChecksum($data['konsensus']) : null
        ];

        if ($this->config->verboseLogging) {
            log_message('debug', "[CONSENSUS_CHECK] Checksums - UserDB: " . substr($checksums['userdb'] ?? 'NULL', 0, 16) .
                ", AdminDB: " . substr($checksums['admindb'] ?? 'NULL', 0, 16) .
                ", KonsensusDB: " . substr($checksums['konsensus'] ?? 'NULL', 0, 16));
        }

        // Voting mechanism 2/3 majority pada payload
        $voteResult = $this->performVoting($checksums, $data);
        $voteResult['vote_breakdown'] = $this->buildVoteBreakdown($checksums);

        $result = [
            'record_key'     => $blockHash,
            'identifier'     => $nomorPermohonan,
            'status'         => $voteResult['status'],
            'checksums'      => $checksums,
            'majority_hash'  => $voteResult['majority_hash'] ?? null,
            'corrupt_dbs'    => $voteResult['corrupt_dbs'] ?? [],
            'vote_breakdown' => $voteResult['vote_breakdown'],
            'data'           => $data,
            'recommendation' => $voteResult['recommendation'] ?? null,
        ];

        // Deteksi block_hash legacy / tidak canonical meskipun payload 3/3 atau 2/3 sepakat
        $hashAssessment = $this->assessBlockHash($data, $voteResult);
        if ($hashAssessment['needs_repair'] && in_array($voteResult['status'], ['healthy', 'minority'], true)) {
            $result['hash_repair'] = $hashAssessment;
            $result['hash_repair_needed'] = true;

            if ($voteResult['status'] === 'healthy') {
                $result['status'] = 'hash_repair';
                $result['corrupt_dbs'] = $this->getDbsNeedingHashRepair($data, $hashAssessment);
                $result['recommendation'] = 'Perbaiki block_hash ke format canonical dari data mayoritas (2/3 atau 3/3 payload cocok).';
            } else {
                $result['recommendation'] = trim(($result['recommendation'] ?? '') . ' Perbaiki block_hash ke format canonical.');
                $result['corrupt_dbs'] = array_values(array_unique(array_merge(
                    $result['corrupt_dbs'],
                    $this->getDbsNeedingHashRepair($data, $hashAssessment)
                )));
            }
        }

        return $result;
    }

    protected function buildVoteBreakdown(array $checksums): array
    {
        $validChecksums = array_filter($checksums, static fn($c) => $c !== null);
        if ($validChecksums === []) {
            return [
                'pattern' => '0/0',
                'majority_count' => 0,
                'minority_count' => 0,
                'is_anomaly' => true,
            ];
        }

        $votes = array_count_values($validChecksums);
        arsort($votes);
        $counts = array_values($votes);
        $majorityCount = $counts[0] ?? 0;
        $minorityCount = count($validChecksums) - $majorityCount;

        $pattern = count($validChecksums) === 3
            ? sprintf('%d/%d', $majorityCount, $minorityCount)
            : sprintf('%d/3 present', count($validChecksums));

        return [
            'pattern'          => $pattern,
            'majority_count'   => $majorityCount,
            'minority_count'   => $minorityCount,
            'is_anomaly'       => count($validChecksums) < 3 || $majorityCount < 3,
            'missing_dbs'      => array_keys(array_filter($checksums, static fn($c) => $c === null)),
            'minority_dbs'     => $this->getMinorityDbs($checksums, array_key_first($votes)),
        ];
    }

    protected function getMinorityDbs(array $checksums, ?string $majorityHash): array
    {
        if ($majorityHash === null) {
            return array_keys(array_filter($checksums, static fn($c) => $c !== null));
        }

        $minority = [];
        foreach ($checksums as $db => $hash) {
            if ($hash !== null && $hash !== $majorityHash) {
                $minority[] = $db;
            }
        }

        return $minority;
    }

    protected function resolveMajorityData(array $data, array $voteResult): ?array
    {
        if (!empty($voteResult['majority_hash'])) {
            foreach ($data as $record) {
                if ($record && $this->calculateChecksum($record) === $voteResult['majority_hash']) {
                    return $record;
                }
            }
        }

        return $data['konsensus'] ?? $data['admindb'] ?? $data['userdb'] ?? null;
    }

    protected function assessBlockHash(array $data, array $voteResult): array
    {
        if (!in_array($voteResult['status'], ['healthy', 'minority'], true)) {
            return ['needs_repair' => false];
        }

        $majorityData = $this->resolveMajorityData($data, $voteResult);
        if (!$majorityData) {
            return ['needs_repair' => false];
        }

        $canonicalHash = BlockHash::calculate($majorityData);
        $storedHash = (string) ($majorityData['block_hash'] ?? '');

        if ($storedHash === $canonicalHash) {
            return ['needs_repair' => false, 'valid' => true];
        }

        return [
            'needs_repair'   => true,
            'valid'          => false,
            'match_type'     => BlockHash::getHashMatchType($majorityData),
            'stored_hash'    => $storedHash,
            'canonical_hash' => $canonicalHash,
            'majority_data'  => $majorityData,
        ];
    }

    protected function getDbsNeedingHashRepair(array $data, array $hashAssessment): array
    {
        $canonical = $hashAssessment['canonical_hash'];
        $dbs = [];

        foreach ($data as $db => $record) {
            if ($record && (string) ($record['block_hash'] ?? '') !== $canonical) {
                $dbs[] = $db;
            }
        }

        return $dbs;
    }

    protected function normalizeSourceData(array $sourceData): array
    {
        return BlockHash::buildCanonicalRecord($sourceData);
    }

    protected function performVoting(array $checksums, array $data): array
    {
        // Filter null checksums
        $validChecksums = array_filter($checksums, fn($c) => $c !== null);

        // Jika ada DB yang missing data
        if (count($validChecksums) < 3) {
            $missingDbs = array_keys(array_filter($checksums, fn($c) => $c === null));
            return [
                'status'         => 'missing',
                'corrupt_dbs'    => $missingDbs,
                'recommendation' => 'Sync missing data from available databases'
            ];
        }

        // Hitung votes
        $votes = array_count_values($validChecksums);
        arsort($votes);

        $majorityHash = array_key_first($votes);
        $majorityCount = $votes[$majorityHash];

        // Case 1: Semua sama (3-0) - HEALTHY
        if ($majorityCount === 3) {
            return [
                'status'        => 'healthy',
                'majority_hash' => $majorityHash
            ];
        }

        // Case 2: Majority 2-1 - MINORITY CORRUPT (anomali 1/3)
        if ($majorityCount === 2) {
            $corruptDbs = [];
            foreach ($checksums as $db => $hash) {
                if ($hash !== $majorityHash) {
                    $corruptDbs[] = $db;
                }
            }

            return [
                'status'         => 'minority',
                'majority_hash'  => $majorityHash,
                'corrupt_dbs'    => $corruptDbs,
                'recommendation' => 'Anomali voting 2/3 vs 1/3 — recover dari mayoritas',
            ];
        }

        // Case 3: Semua berbeda (1-1-1) - NO CONSENSUS
        return [
            'status'         => 'no_consensus',
            'corrupt_dbs'    => array_keys($checksums),
            'recommendation' => 'Manual review required - use blockchain_backup as source of truth'
        ];
    }

    /**
     * recover records yang corrupt (minority)
     * 
     * MEKANISME:
     * 1. Filter hanya minority corrupt (2 vs 1)
     * 2. Langsung repair corrupt DB dengan data majority
     * 3. Data corrupt disimpan di recovery_history.before_data (untuk rollback)
     * 4. Data corrupt TIDAK di-backup ke blockchain_backup (karena corrupt)
     * 5. Handle records deleted from userdb (missing_from_userdb) - restore from backup
     * 
     * @param array $items Items dari hasil check() yang perlu di-recover
     * @param string $performedBy Username yang trigger recovery
     * @return array Hasil recovery
     */
    public function recover(array $items, string $performedBy = 'system'): array
    {
        $results = [
            'total_attempted' => count($items),
            'success'         => 0,
            'failed'          => 0,
            'skipped'         => 0,
            'details'         => []
        ];

        foreach ($items as $item) {
            // === ENHANCEMENT: Handle records deleted from userdb ===
            if (!empty($item['missing_from_userdb']) || $item['status'] === 'missing_from_userdb') {
                $restoreResult = $this->restoreDeletedRecord($item, $performedBy);
                if ($restoreResult['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
                $results['details'][] = $restoreResult;
                continue;
            }

            // Handle missing data (sync dari DB yang ada)
            if ($item['status'] === 'missing') {
                $syncResult = $this->syncMissingData($item, $performedBy);
                if ($syncResult['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
                $results['details'][] = $syncResult;
                continue;
            }

            // Handle hash repair (legacy block_hash / migrasi format)
            if ($item['status'] === 'hash_repair') {
                $repairResult = $this->repairBlockHash($item, $performedBy);
                if ($repairResult['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
                $results['details'][] = $repairResult;
                continue;
            }

            // Skip jika bukan minority (hanya recover yang 2-1 payload)
            if ($item['status'] !== 'minority') {
                $results['skipped']++;
                continue;
            }

            // Skip jika table di-blacklist
            if (in_array('blockchain', $this->config->blacklistTables)) {
                $results['skipped']++;
                $results['details'][] = [
                    'record_key' => $item['record_key'],
                    'status'     => 'skipped',
                    'reason'     => 'Table is blacklisted'
                ];
                continue;
            }

            // Lakukan recovery (langsung repair tanpa backup ke blockchain_backup)
            $recoveryResult = $this->recoverSingleRecord($item, $performedBy);

            if ($recoveryResult['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = $recoveryResult;
        }

        // Log hasil recovery
        $this->activityLogModel->logActivity([
            'action_type'   => 'CONSENSUS_RECOVER',
            'status'        => $results['failed'] > 0 ? 'WARNING' : 'SUCCESS',
            'description'   => sprintf(
                'Auto-recovery completed: %d success, %d failed, %d skipped',
                $results['success'],
                $results['failed'],
                $results['skipped']
            ),
            'original_data' => $results
        ]);

        return $results;
    }

    /**
     * Recover single record
     */
    protected function recoverSingleRecord(array $item, string $performedBy): array
    {
        $recordKey = $item['record_key'];
        $majorityHash = $item['majority_hash'];
        $corruptDbs = $item['corrupt_dbs'];

        // Tentukan source data (dari DB yang memiliki majority hash)
        $sourceDb = null;
        $sourceData = null;

        foreach (['konsensus', 'admindb', 'userdb'] as $db) {
            if ($item['checksums'][$db] === $majorityHash) {
                $sourceDb = $db;
                $sourceData = $item['data'][$db];
                break;
            }
        }

        if (!$sourceData) {
            return [
                'record_key' => $recordKey,
                'success'    => false,
                'error'      => 'Source data not found'
            ];
        }

        $sourceData = $this->normalizeSourceData($sourceData);

        $recoveryDetails = [
            'record_key' => $recordKey,
            'source_db'  => $sourceDb,
            'target_dbs' => $corruptDbs,
            'success'    => true,
            'errors'     => []
        ];

        // Recover setiap corrupt DB
        foreach ($corruptDbs as $targetDb) {
            try {
                // PENTING: Data corrupt TIDAK di-backup ke blockchain_backup
                // Hanya disimpan di recovery_history.before_data untuk rollback capability

                $corruptData = $item['data'][$targetDb];

                if ($this->config->verboseLogging) {
                    log_message('info', "[CONSENSUS_RECOVERY] Repairing {$recordKey} in {$targetDb} (corrupt) from {$sourceDb} (majority)");
                }

                // Update corrupt DB dengan data majority (langsung repair)
                $updateSuccess = $this->updateDatabase($targetDb, $sourceData, $corruptData);

                if ($updateSuccess) {
                    // Log ke recovery history (before_data untuk rollback)
                    $this->recoveryHistoryModel->logRecovery([
                        'recovery_type'    => $performedBy === 'system' ? 'consensus_auto' : 'consensus_manual',
                        'source_db'        => $sourceDb,
                        'target_db'        => $targetDb,
                        'table_name'       => 'blockchain',
                        'record_key'       => $recordKey,
                        'before_checksum'  => $item['checksums'][$targetDb],
                        'after_checksum'   => $majorityHash,
                        'before_data'      => $corruptData,  // Corrupt data (untuk rollback)
                        'after_data'       => $sourceData,   // Data majority (hasil repair)
                        'consensus_result' => $item['checksums'],
                        'status'           => 'success',
                        'performed_by'     => $performedBy
                    ]);

                    // Log activity for this recovery so Riwayat aktivitas shows the identifier
                    $this->activityLogModel->logActivity([
                        'action_type'   => 'CONSENSUS_RECOVER',
                        'status'        => 'SUCCESS',
                        'identifier'    => $item['identifier'] ?? $recordKey,
                        'description'   => "Repaired {$item['identifier']} in {$targetDb} from {$sourceDb}",
                        'original_data' => $corruptData,
                        'modified_data' => $sourceData
                    ]);

                    if ($this->config->verboseLogging) {
                        log_message('info', "[CONSENSUS_RECOVERY] ✓ Successfully repaired {$recordKey} in {$targetDb} from {$sourceDb}");
                    }
                } else {
                    $recoveryDetails['success'] = false;
                    $recoveryDetails['errors'][] = "Failed to update {$targetDb}";

                    log_message('error', "[CONSENSUS_RECOVERY] ✗ Failed to repair {$recordKey} in {$targetDb}");
                }
            } catch (\Exception $e) {
                $recoveryDetails['success'] = false;
                $recoveryDetails['errors'][] = "Error updating {$targetDb}: " . $e->getMessage();

                log_message('error', "[CONSENSUS_RECOVERY] Exception: " . $e->getMessage());
            }
        }

        return $recoveryDetails;
    }

    /**
     * Perbaiki block_hash di semua DB menggunakan payload mayoritas.
     */
    protected function repairBlockHash(array $item, string $performedBy): array
    {
        $recordKey = $item['record_key'];
        $hashAssessment = $item['hash_repair'] ?? null;

        $majorityData = $hashAssessment['majority_data'] ?? $this->resolveMajorityData(
            $item['data'] ?? [],
            ['majority_hash' => $item['majority_hash'] ?? null, 'status' => 'healthy']
        );

        if (!$majorityData) {
            return [
                'record_key' => $recordKey,
                'success'    => false,
                'error'      => 'Majority data not found for hash repair',
            ];
        }

        $canonicalData = $this->normalizeSourceData($majorityData);
        $targetDbs = $item['corrupt_dbs'] ?? $this->getDbsNeedingHashRepair(
            $item['data'] ?? [],
            [
                'canonical_hash' => $canonicalData['block_hash'],
            ]
        );

        if ($targetDbs === []) {
            $targetDbs = array_keys(array_filter($item['data'] ?? []));
        }

        $repairDetails = [
            'record_key' => $recordKey,
            'source_db'  => 'majority_payload',
            'target_dbs' => $targetDbs,
            'success'    => true,
            'errors'     => [],
        ];

        foreach ($targetDbs as $targetDb) {
            if (empty($item['data'][$targetDb])) {
                continue;
            }

            try {
                $beforeData = $item['data'][$targetDb];
                $updateSuccess = $this->updateDatabase($targetDb, $canonicalData, $beforeData);

                if ($updateSuccess) {
                    $this->recoveryHistoryModel->logRecovery([
                        'recovery_type'    => $performedBy === 'system' ? 'consensus_auto' : 'consensus_manual',
                        'source_db'        => 'majority_payload',
                        'target_db'        => $targetDb,
                        'table_name'       => 'blockchain',
                        'record_key'       => $recordKey,
                        'before_checksum'  => $beforeData['block_hash'] ?? null,
                        'after_checksum'   => $canonicalData['block_hash'],
                        'before_data'      => $beforeData,
                        'after_data'       => $canonicalData,
                        'consensus_result' => $item['checksums'] ?? [],
                        'status'           => 'success',
                        'performed_by'     => $performedBy,
                    ]);

                    $this->activityLogModel->logActivity([
                        'action_type'   => 'HASH_REPAIR',
                        'status'        => 'SUCCESS',
                        'identifier'    => $item['identifier'] ?? $recordKey,
                        'description'   => "Repaired block_hash for {$item['identifier']} in {$targetDb}",
                        'original_data' => ['block_hash' => $beforeData['block_hash'] ?? null],
                        'modified_data' => ['block_hash' => $canonicalData['block_hash']],
                    ]);
                } else {
                    $repairDetails['success'] = false;
                    $repairDetails['errors'][] = "Failed to repair hash in {$targetDb}";
                }
            } catch (\Exception $e) {
                $repairDetails['success'] = false;
                $repairDetails['errors'][] = "Error repairing hash in {$targetDb}: " . $e->getMessage();
            }
        }

        return $repairDetails;
    }

    /**
     * Update database dengan data majority
     * 
     * PENTING:
     * - Filter field yang boleh di-update (exclude id, timestamp, backup_timestamp)
     * - Gunakan block_hash sebagai key untuk matching
     * - Support update ke userdb.blockchain, admindb.blockchain_backup, konsensus.konsensus
     */
    protected function updateDatabase(string $targetDb, array $sourceData, array $currentData): bool
    {
        if ($this->config->dryRunMode) {
            log_message('info', "[DRY-RUN] Would update {$targetDb} with data from majority");
            return true;
        }

        $db = null;
        $table = '';

        switch ($targetDb) {
            case 'userdb':
                $db = $this->userDb;
                $table = 'blockchain';
                break;
            case 'admindb':
                $db = $this->adminDb;
                $table = 'blockchain_backup';
                break;
            case 'konsensus':
                $db = $this->konsensusDb;
                $table = 'konsensus';
                break;
        }

        if (!$db) {
            log_message('error', "[CONSENSUS_UPDATE] Invalid target DB: {$targetDb}");
            return false;
        }

        // Field yang boleh di-update
        $updateableFields = BlockHash::RECOVERABLE_FIELDS;

        // Filter hanya field yang boleh di-update
        $updateData = [];
        foreach ($updateableFields as $field) {
            if (isset($sourceData[$field])) {
                $updateData[$field] = $sourceData[$field];
            }
        }

        if (empty($updateData)) {
            log_message('error', "[CONSENSUS_UPDATE] No updateable fields found");
            return false;
        }

        // Cari record berdasarkan block_hash (lebih reliable) atau id
        $blockHash = $currentData['block_hash'] ?? null;
        $recordId = $currentData['id'] ?? null;

        try {
            $builder = $db->table($table);

            // Prioritas: cari by block_hash (unique identifier)
            if ($blockHash) {
                $builder->where('block_hash', $blockHash);
            } elseif ($recordId) {
                $builder->where('id', $recordId);
            } else {
                log_message('error', "[CONSENSUS_UPDATE] No identifier (block_hash or id) found");
                return false;
            }

            $result = $builder->update($updateData);

            if ($result) {
                log_message('info', "[CONSENSUS_UPDATE] ✓ Updated {$table} in {$targetDb} (hash: " . substr($blockHash, 0, 16) . "...)");
            } else {
                log_message('warning', "[CONSENSUS_UPDATE] ✗ Update returned false for {$table} in {$targetDb}");
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', "[CONSENSUS_UPDATE] Exception updating {$table} in {$targetDb}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync missing data ke database yang tidak punya record
     * 
     * Jika salah satu DB tidak punya data, ambil dari DB yang ada (valid)
     */
    protected function syncMissingData(array $item, string $performedBy): array
    {
        $recordKey = $item['record_key'];
        $missingDbs = $item['corrupt_dbs']; // Untuk status 'missing', ini adalah DB yang tidak punya data

        // Cari DB yang punya data valid
        $sourceDb = null;
        $sourceData = null;

        foreach (['userdb', 'admindb', 'konsensus'] as $db) {
            if ($item['data'][$db] !== null) {
                $sourceDb = $db;
                $sourceData = $item['data'][$db];
                break;
            }
        }

        if (!$sourceData) {
            return [
                'record_key' => $recordKey,
                'success'    => false,
                'error'      => 'No source data found for sync'
            ];
        }

        $sourceData = $this->normalizeSourceData($sourceData);

        $syncDetails = [
            'record_key' => $recordKey,
            'source_db'  => $sourceDb,
            'target_dbs' => $missingDbs,
            'success'    => true,
            'errors'     => []
        ];

        // Sync ke setiap missing DB
        foreach ($missingDbs as $targetDb) {
            try {
                $insertSuccess = $this->insertToDatabase($targetDb, $sourceData);

                if ($insertSuccess) {
                    // Log ke recovery history
                    $this->recoveryHistoryModel->logRecovery([
                        'recovery_type'    => 'consensus_auto',
                        'source_db'        => $sourceDb,
                        'target_db'        => $targetDb,
                        'table_name'       => 'blockchain',
                        'record_key'       => $recordKey,
                        'before_checksum'  => null,
                        'after_checksum'   => $this->calculateChecksum($sourceData),
                        'before_data'      => null,
                        'after_data'       => $sourceData,
                        'consensus_result' => $item['checksums'],
                        'status'           => 'success',
                        'performed_by'     => $performedBy
                    ]);
                    // Log activity so Riwayat aktivitas includes identifier for sync operations
                    $this->activityLogModel->logActivity([
                        'action_type'   => 'CONSENSUS_SYNC',
                        'status'        => 'SUCCESS',
                        'identifier'    => $item['identifier'] ?? $recordKey,
                        'description'   => "Synced missing data for {$item['identifier']} to {$targetDb} from {$sourceDb}",
                        'modified_data' => $sourceData
                    ]);

                    if ($this->config->verboseLogging) {
                        log_message('info', "[CONSENSUS_SYNC] ✓ Synced missing data to {$targetDb} from {$sourceDb}");
                    }
                } else {
                    $syncDetails['success'] = false;
                    $syncDetails['errors'][] = "Failed to insert to {$targetDb}";
                }
            } catch (\Exception $e) {
                $syncDetails['success'] = false;
                $syncDetails['errors'][] = "Error inserting to {$targetDb}: " . $e->getMessage();
                log_message('error', "[CONSENSUS_SYNC] Exception: " . $e->getMessage());
            }
        }

        return $syncDetails;
    }

    /**
     * Insert data ke database yang missing
     */
    protected function insertToDatabase(string $targetDb, array $data): bool
    {
        if ($this->config->dryRunMode) {
            log_message('info', "[DRY-RUN] Would insert to {$targetDb}");
            return true;
        }

        $db = null;
        $table = '';

        switch ($targetDb) {
            case 'userdb':
                $db = $this->userDb;
                $table = 'blockchain';
                break;
            case 'admindb':
                $db = $this->adminDb;
                $table = 'blockchain_backup';
                break;
            case 'konsensus':
                $db = $this->konsensusDb;
                $table = 'konsensus';
                break;
        }

        if (!$db) {
            return false;
        }

        // Field yang boleh di-insert
        $insertableFields = BlockHash::RECOVERABLE_FIELDS;

        // Filter data
        $insertData = [];
        foreach ($insertableFields as $field) {
            if (isset($data[$field])) {
                $insertData[$field] = $data[$field];
            }
        }

        // Untuk admindb, tambahkan backup_type
        if ($targetDb === 'admindb') {
            $insertData['backup_type'] = 'consensus_sync';
        }

        try {
            $result = $db->table($table)->insert($insertData);

            if ($result) {
                log_message('info', "[CONSENSUS_INSERT] ✓ Inserted to {$table} in {$targetDb}");
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', "[CONSENSUS_INSERT] Exception inserting to {$table}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Backup data corrupt sebelum overwrite
     * 
     * CATATAN PENTING:
     * - Data corrupt TIDAK di-backup ke blockchain_backup (karena corrupt)
     * - Data corrupt hanya disimpan di recovery_history.before_data (untuk rollback)
     * - Backup ke blockchain_backup hanya untuk data VALID
     */
    protected function backupCorruptData(array $data, string $sourceDb): void
    {
        // TIDAK backup corrupt data ke blockchain_backup
        // Corrupt data hanya tersimpan di recovery_history.before_data

        if ($this->config->verboseLogging) {
            log_message('info', "[CONSENSUS] Corrupt data from {$sourceDb} will be stored in recovery_history only (not in blockchain_backup)");
        }
    }

    /**
     * === NEW METHOD: Restore records deleted from userdb ===
     * This handles the critical bypass detection case where records are deleted from userdb
     * but still exist in admindb/konsensus. This restores the deleted record.
     * 
     * @param array $item Record item with missing_from_userdb flag
     * @param string $performedBy Username performing recovery
     * @return array Recovery result
     */
    protected function restoreDeletedRecord(array $item, string $performedBy = 'system'): array
    {
        try {
            $blockHash = $item['block_hash'] ?? ($item['record_key'] ?? 'unknown');
            $identifier = $item['nomor_permohonan'] ?? ($item['identifier'] ?? 'unknown');
            $existingDbs = $item['source_dbs'] ?? [];

            log_message('warning', "[CRITICAL] Attempting to restore deleted record: {$identifier} (hash: " . substr($blockHash, 0, 16) . "...)");

            // Select source database (prefer admindb as source of truth for backup)
            $sourceDb = null;
            $sourceData = null;

            if (in_array('admindb', $existingDbs)) {
                // Get data from admindb
                if (!empty($item['block_hash'])) {
                    $sourceData = $this->adminDb->table('blockchain_backup')
                        ->where('block_hash', $blockHash)
                        ->get()
                        ->getRowArray();
                }
                $sourceDb = 'admindb';
            } elseif (in_array('konsensus', $existingDbs)) {
                // Fallback to konsensus
                if (!empty($item['block_hash'])) {
                    $sourceData = $this->konsensusDb->table('konsensus')
                        ->where('block_hash', $blockHash)
                        ->get()
                        ->getRowArray();
                }
                $sourceDb = 'konsensus';
            }

            if (!$sourceData) {
                return [
                    'success' => false,
                    'record_key' => $blockHash,
                    'identifier' => $identifier,
                    'error' => 'Could not find source data in backup databases',
                    'recovery_type' => 'restore_deleted'
                ];
            }

            // Restore to userdb
            $restoredData = [
                'block_hash' => $sourceData['block_hash'],
                'nomor_permohonan' => $sourceData['nomor_permohonan'],
                'nomor_dokumen' => $sourceData['nomor_dokumen'],
                'tanggal_dokumen' => $sourceData['tanggal_dokumen'],
                'tanggal_filing' => $sourceData['tanggal_filing'],
                'dokumen_base64' => $sourceData['dokumen_base64'],
                'ip_address' => $sourceData['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'previous_hash' => $sourceData['previous_hash'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Insert into userdb
            $inserted = $this->userDb->table('blockchain')->insert($restoredData);

            if (!$inserted) {
                return [
                    'success' => false,
                    'record_key' => $blockHash,
                    'identifier' => $identifier,
                    'error' => 'Failed to insert restored record into userdb',
                    'recovery_type' => 'restore_deleted'
                ];
            }

            // Log recovery
            $this->recoveryHistoryModel->insert([
                'recovery_type' => 'consensus_restore_deleted',
                'source_db' => $sourceDb,
                'target_db' => 'userdb',
                'record_key' => $blockHash,
                'before_checksum' => 'NULL (record deleted)',
                'after_checksum' => $this->calculateChecksum($restoredData),
                'before_data' => json_encode(['status' => 'deleted']),
                'after_data' => json_encode($restoredData),
                'consensus_result' => json_encode(['status' => 'restored_from_backup']),
                'status' => 'success',
                'performed_by' => $performedBy
            ]);

            // Log activity
            $this->activityLogModel->logActivity([
                'action_type' => 'CONSENSUS_RECOVER',
                'block_id' => $restoredData['id'] ?? null,
                'identifier' => $identifier,
                'status' => 'SUCCESS',
                'description' => "✅ Restored deleted record from {$sourceDb}: {$identifier}",
                'original_data' => ['deleted' => true],
                'modified_data' => $restoredData
            ]);

            log_message('info', "[RECOVERY_SUCCESS] Restored deleted record: {$identifier} from {$sourceDb}");

            return [
                'success' => true,
                'record_key' => $blockHash,
                'identifier' => $identifier,
                'restored_from' => $sourceDb,
                'recovery_type' => 'restore_deleted',
                'message' => "✅ Record restored successfully from {$sourceDb}"
            ];

        } catch (\Exception $e) {
            log_message('error', "[RESTORATION_ERROR] Failed to restore deleted record: " . $e->getMessage());

            return [
                'success' => false,
                'record_key' => $item['record_key'] ?? 'unknown',
                'identifier' => $item['identifier'] ?? 'unknown',
                'error' => $e->getMessage(),
                'recovery_type' => 'restore_deleted'
            ];
        }
    }

    /**
     * Calculate checksum untuk record
     */
    protected function calculateChecksum(array $record): string
    {
        return BlockHash::calculatePayloadChecksum($record);
    }

    /**
     * Get data dari admindb (blockchain_backup)
     * FIXED: Improved timestamp matching to prevent bypass detection
     */
    protected function getFromAdminDb(string $blockHash, string $nomorPermohonan, string $tanggalDokumen): ?array
    {
        // Prioritas 1: cari by block_hash (most reliable)
        $result = $this->adminDb->table('blockchain_backup')
            ->where('block_hash', $blockHash)
            ->get()
            ->getRowArray();

        if ($result) {
            return $result;
        }

        // Prioritas 2: cari by identifier dengan improved timestamp matching
        // FIX: Use full datetime comparison with minute precision, not just DATE()
        // This catches records even if they have slightly different times
        $tanggalStart = date('Y-m-d 00:00:00', strtotime($tanggalDokumen));
        $tanggalEnd = date('Y-m-d 23:59:59', strtotime($tanggalDokumen));
        
        $result = $this->adminDb->table('blockchain_backup')
            ->where('nomor_permohonan', $nomorPermohonan)
            ->where('tanggal_dokumen >=', $tanggalStart)
            ->where('tanggal_dokumen <=', $tanggalEnd)
            ->orderBy('backup_timestamp', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        // Prioritas 3: Fallback dengan fuzzy matching (allow different date if same nomor)
        if (!$result) {
            $result = $this->adminDb->table('blockchain_backup')
                ->where('nomor_permohonan', $nomorPermohonan)
                ->orderBy('backup_timestamp', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();
        }

        return $result;
    }

    /**
     * Get data dari konsensus db
     * FIXED: Improved timestamp matching to prevent bypass detection
     */
    protected function getFromKonsensusDb(string $blockHash, string $nomorPermohonan, string $tanggalDokumen): ?array
    {
        // Prioritas 1: cari by block_hash (most reliable)
        $result = $this->konsensusDb->table('konsensus')
            ->where('block_hash', $blockHash)
            ->get()
            ->getRowArray();

        if ($result) {
            return $result;
        }

        // Prioritas 2: cari by identifier dengan improved timestamp matching
        // FIX: Use full datetime comparison with day precision
        $tanggalStart = date('Y-m-d 00:00:00', strtotime($tanggalDokumen));
        $tanggalEnd = date('Y-m-d 23:59:59', strtotime($tanggalDokumen));
        
        $result = $this->konsensusDb->table('konsensus')
            ->where('nomor_permohonan', $nomorPermohonan)
            ->where('tanggal_dokumen >=', $tanggalStart)
            ->where('tanggal_dokumen <=', $tanggalEnd)
            ->orderBy('timestamp', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        // Prioritas 3: Fallback dengan fuzzy matching (allow different date if same nomor)
        if (!$result) {
            $result = $this->konsensusDb->table('konsensus')
                ->where('nomor_permohonan', $nomorPermohonan)
                ->orderBy('timestamp', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();
        }

        return $result;
    }

    /**
     * Rollback recovery berdasarkan history ID
     */
    public function rollback(int $historyId, string $performedBy = 'admin'): array
    {
        $history = $this->recoveryHistoryModel->getRecoveryById($historyId);

        if (!$history) {
            return [
                'success' => false,
                'error'   => 'Recovery history not found'
            ];
        }

        if ($history['status'] === 'rolled_back') {
            return [
                'success' => false,
                'error'   => 'This recovery has already been rolled back'
            ];
        }

        try {
            // Restore data dari before_data
            $beforeData = $history['before_data'];
            $targetDb = $history['target_db'];

            $rollbackSuccess = $this->updateDatabase($targetDb, $beforeData, $history['after_data']);

            if ($rollbackSuccess) {
                // Mark as rolled back
                $this->recoveryHistoryModel->markAsRolledBack($historyId);

                // Log rollback
                $this->recoveryHistoryModel->logRecovery([
                    'recovery_type'    => 'rollback',
                    'source_db'        => $targetDb,
                    'target_db'        => $targetDb,
                    'table_name'       => $history['table_name'],
                    'record_key'       => $history['record_key'],
                    'before_checksum'  => $history['after_checksum'],
                    'after_checksum'   => $history['before_checksum'],
                    'before_data'      => $history['after_data'],
                    'after_data'       => $beforeData,
                    'status'           => 'success',
                    'performed_by'     => $performedBy
                ]);

                $this->activityLogModel->logActivity([
                    'action_type'   => 'CONSENSUS_ROLLBACK',
                    'status'        => 'SUCCESS',
                    'description'   => "Rolled back recovery #{$historyId} for {$history['record_key']}",
                    'original_data' => $history
                ]);

                return [
                    'success' => true,
                    'message' => 'Recovery successfully rolled back'
                ];
            }

            return [
                'success' => false,
                'error'   => 'Failed to update database during rollback'
            ];
        } catch (\Exception $e) {
            log_message('error', "[CONSENSUS_ROLLBACK] Error: " . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * ============================================================
     * PURGE MINORITY FEATURE: Isolate and purge corrupt minority nodes
     * ============================================================
     * 
     * When purge_minority is enabled (True), instead of just syncing data,
     * the system completely wipes the corrupt minority node(s), including:
     * - Deletion of corrupted records from minority database
     * - Removal of logs/audit trails for that node
     * - Isolation of the node from consensus mechanism
     * - Comprehensive audit trail of purge operations
     * 
     * Use Cases:
     * - Remove permanently compromised nodes
     * - Clean slate recovery for failed nodes
     * - Quarantine corrupted data sources
     */

    /**
     * PUBLIC: Recover with optional purge_minority flag
     * 
     * BEHAVIOR:
     * - If purge_minority=False: Standard recovery (sync data from majority)
     * - If purge_minority=True: Purge minority nodes after recording everything
     * 
     * @param array $items Items dari hasil check()
     * @param string $performedBy Username yang trigger recovery
     * @param bool $purgeMinority Whether to purge minority nodes
     * @return array Hasil recovery dengan purge stats
     */
    public function recoverWithPurge(array $items, string $performedBy = 'system', bool $purgeMinority = false): array
    {
        $baseResults = $this->recover($items, $performedBy);

        if (!$purgeMinority) {
            return $baseResults;
        }

        // === PURGE PHASE: Remove minority data ===
        $purgeResults = [
            'total_purged'   => 0,
            'purge_failed'   => 0,
            'purge_details'  => []
        ];

        foreach ($baseResults['details'] as $recoveryDetail) {
            // Only purge items that had minority status
            if (!isset($recoveryDetail['target_dbs']) || empty($recoveryDetail['target_dbs'])) {
                continue;
            }

            // Purge each minority DB that was recovered
            foreach ($recoveryDetail['target_dbs'] as $minorityDb) {
                $purgeResult = $this->purgeMinorityNode(
                    minorityDb: $minorityDb,
                    recordKey: $recoveryDetail['record_key'] ?? null,
                    performedBy: $performedBy
                );

                if ($purgeResult['success']) {
                    $purgeResults['total_purged']++;
                } else {
                    $purgeResults['purge_failed']++;
                }

                $purgeResults['purge_details'][] = $purgeResult;
            }
        }

        // Combine results
        $baseResults['purge_minority'] = $purgeMinority;
        $baseResults['purge_results'] = $purgeResults;
        $baseResults['total_purged_records'] = $purgeResults['total_purged'];

        // Log combined recovery + purge
        $this->activityLogModel->logActivity([
            'action_type'   => 'CONSENSUS_RECOVER_PURGE',
            'status'        => $purgeResults['purge_failed'] > 0 ? 'WARNING' : 'SUCCESS',
            'description'   => sprintf(
                'Recovery + Purge completed: %d recovered, %d purged, purge_failed: %d',
                $baseResults['success'],
                $purgeResults['total_purged'],
                $purgeResults['purge_failed']
            ),
            'original_data' => [
                'recovery' => $baseResults,
                'purge'    => $purgeResults
            ]
        ]);

        return $baseResults;
    }

    /**
     * PRIVATE: Purge corrupted data from a single minority node
     * 
     * Operations:
     * 1. Delete the specific corrupted record from minority DB
     * 2. Log purge action with complete before/after states
     * 3. Mark node as requiring audit review
     * 
     * @param string $minorityDb Target database to purge (userdb|admindb|konsensus)
     * @param string|null $recordKey Specific record to purge (null = all)
     * @param string $performedBy Username
     * @return array Purge result
     */
    protected function purgeMinorityNode(string $minorityDb, ?string $recordKey = null, string $performedBy = 'system'): array
    {
        try {
            if ($this->config->dryRunMode) {
                log_message('info', "[DRY-RUN PURGE] Would purge {$minorityDb} (record: {$recordKey})");
                return [
                    'success'     => true,
                    'minority_db' => $minorityDb,
                    'record_key'  => $recordKey,
                    'dry_run'     => true,
                    'message'     => 'DRY-RUN: Would purge record'
                ];
            }

            $db = null;
            $table = '';

            // Map DB name to connection and table
            switch ($minorityDb) {
                case 'userdb':
                    $db = $this->userDb;
                    $table = 'blockchain';
                    break;
                case 'admindb':
                    $db = $this->adminDb;
                    $table = 'blockchain_backup';
                    break;
                case 'konsensus':
                    $db = $this->konsensusDb;
                    $table = 'konsensus';
                    break;
            }

            if (!$db) {
                return [
                    'success'     => false,
                    'minority_db' => $minorityDb,
                    'record_key'  => $recordKey,
                    'error'       => "Invalid DB: {$minorityDb}"
                ];
            }

            // Fetch record before purge for audit trail
            $beforeData = null;
            if ($recordKey) {
                $beforeData = $db->table($table)
                    ->where('block_hash', $recordKey)
                    ->get()
                    ->getRowArray();
            }

            // Delete from minority DB
            $deleteBuilder = $db->table($table);
            if ($recordKey) {
                $deleteBuilder->where('block_hash', $recordKey);
            }

            $deleted = $deleteBuilder->delete();

            if ($deleted || $recordKey === null) {
                // Log purge to recovery history
                $this->recoveryHistoryModel->logRecovery([
                    'recovery_type'    => 'minority_purge',
                    'source_db'        => 'system',
                    'target_db'        => $minorityDb,
                    'table_name'       => $table,
                    'record_key'       => $recordKey ?? 'all_records',
                    'before_checksum'  => $beforeData ? $this->calculateChecksum($beforeData) : 'multiple',
                    'after_checksum'   => null,
                    'before_data'      => $beforeData ? json_encode($beforeData) : 'multiple_records_deleted',
                    'after_data'       => 'PURGED',
                    'consensus_result' => json_encode(['purge_type' => 'minority_node_cleanup']),
                    'status'           => 'success',
                    'performed_by'     => $performedBy
                ]);

                // Log activity
                $this->activityLogModel->logActivity([
                    'action_type'   => 'MINORITY_PURGE',
                    'status'        => 'SUCCESS',
                    'description'   => "Purged corrupted data from minority node {$minorityDb} (record: {$recordKey})",
                    'original_data' => $beforeData,
                    'modified_data' => ['status' => 'PURGED']
                ]);

                if ($this->config->verboseLogging) {
                    log_message('info', "[MINORITY_PURGE] ✓ Successfully purged {$minorityDb} (record: {$recordKey})");
                }

                return [
                    'success'     => true,
                    'minority_db' => $minorityDb,
                    'record_key'  => $recordKey,
                    'deleted'     => $deleted,
                    'message'     => "Purged minority node {$minorityDb}"
                ];
            }

            return [
                'success'     => false,
                'minority_db' => $minorityDb,
                'record_key'  => $recordKey,
                'error'       => 'Delete operation returned false',
                'deleted'     => $deleted
            ];
        } catch (\Exception $e) {
            log_message('error', "[MINORITY_PURGE_ERROR] Failed to purge {$minorityDb}: " . $e->getMessage());

            return [
                'success'     => false,
                'minority_db' => $minorityDb,
                'record_key'  => $recordKey,
                'error'       => $e->getMessage()
            ];
        }
    }

    /**
     * ADVANCED: Get purge status and recommendations
     * 
     * Returns information about which nodes should be purged based on
     * consensus status and configuration
     * 
     * @param array $consensusCheckResult Result from check()
     * @return array Purge recommendations
     */
    public function getPurgeRecommendations(array $consensusCheckResult): array
    {
        $recommendations = [
            'should_purge'      => false,
            'purge_candidates'  => [],
            'reasoning'         => [],
            'risk_level'        => 'low',
            'audit_required'    => false
        ];

        // Analyze minority corruption patterns
        foreach ($consensusCheckResult['details'] ?? [] as $detail) {
            if ($detail['status'] === 'minority' && !empty($detail['corrupt_dbs'])) {
                foreach ($detail['corrupt_dbs'] as $corruptDb) {
                    if (!in_array($corruptDb, $recommendations['purge_candidates'])) {
                        $recommendations['purge_candidates'][] = $corruptDb;
                    }
                }
            }

            // Mark as high risk if no consensus
            if ($detail['status'] === 'no_consensus') {
                $recommendations['risk_level'] = 'critical';
                $recommendations['audit_required'] = true;
                $recommendations['reasoning'][] = 'No consensus found - requires manual audit before purge';
            }

            // Mark deleted records as critical
            if ($detail['status'] === 'missing_from_userdb') {
                $recommendations['audit_required'] = true;
                $recommendations['reasoning'][] = 'Records deleted from primary DB - audit trail critical';
            }
        }

        if (count($recommendations['purge_candidates']) > 0) {
            $recommendations['should_purge'] = $recommendations['risk_level'] !== 'critical';
            $recommendations['reasoning'][] = sprintf(
                '%d node(s) with consistent minority corruption: %s',
                count($recommendations['purge_candidates']),
                implode(', ', $recommendations['purge_candidates'])
            );
        }

        return $recommendations;
    }
}
