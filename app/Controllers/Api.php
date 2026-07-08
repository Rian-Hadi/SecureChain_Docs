<?php

namespace App\Controllers;

use App\Models\BlockModel;
use App\Models\BackupModel;
use App\Models\WhitelistModel;
use App\Models\ActivityLogModel;
use App\Libraries\BlockHash;
use App\Libraries\MajorityRecovery;
use App\Libraries\ConsensusMonitoring;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class Api extends ResourceController
{
    use ResponseTrait;

    protected $blockModel;
    protected $backupModel;
    protected $whitelistModel;
    protected $activityLogModel;
    protected $majorityRecovery;
    protected $monitoring;
    protected $format = 'json';

    public function __construct()
    {
        // Inisialisasi model-model yang dibutuhkan untuk API.
        $this->blockModel = model(BlockModel::class);
        $this->backupModel = model(BackupModel::class);
        $this->whitelistModel = model(WhitelistModel::class);
        $this->activityLogModel = model(ActivityLogModel::class);
        $this->majorityRecovery = new MajorityRecovery();
        $this->monitoring = new ConsensusMonitoring();
    }

    public function blocks()
    {
        // [GET] /api/blocks - Mengambil semua data blok dari blockchain.
        try {
            $blocks = $this->blockModel->getAllBlocks();

            return $this->respond([
                'status' => 'success',
                'message' => 'Data blocks berhasil diambil',
                'data' => $blocks,
                'total' => count($blocks)
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal mengambil data blocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function block($id = null)
    {
        // [GET] /api/blocks/{id} - Mengambil detail satu blok berdasarkan ID.
        try {
            if (!$id) {
                return $this->failValidationError('ID block harus diisi');
            }

            $block = $this->blockModel->find($id);

            if (!$block) {
                return $this->failNotFound('Block tidak ditemukan');
            }

            return $this->respond([
                'status' => 'success',
                'message' => 'Data block berhasil diambil',
                'data' => $block
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal mengambil data block',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function blockByHash($hash = null)
    {
        // [GET] /api/blocks/hash/{hash} - Mengambil detail satu blok berdasarkan hash.
        try {
            if (!$hash) {
                return $this->failValidationError('Hash harus diisi');
            }

            $block = $this->blockModel->getBlockByHash($hash);

            if (!$block) {
                return $this->failNotFound('Block dengan hash tersebut tidak ditemukan');
            }

            return $this->respond([
                'status' => 'success',
                'message' => 'Data block berhasil diambil',
                'data' => $block
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal mengambil data block',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function validateChain()
    {
        // [GET] /api/chain/validate - Memvalidasi integritas keseluruhan rantai blok.
        try {
            $allBlocks = $this->blockModel->getAllBlocks();
            $previousHash = '0';
            $invalidBlocks = [];
            $isValid = true;

            foreach ($allBlocks as $block) {
                $errors = [];

                // Validasi previous hash
                if ($block['previous_hash'] !== $previousHash) {
                    $errors[] = "Previous hash tidak cocok";
                    $isValid = false;
                }

                // Validasi block hash
                $recalculatedHash = BlockHash::calculate($block);

                if ($recalculatedHash !== $block['block_hash']) {
                    $errors[] = "Block hash tidak valid";
                    $isValid = false;
                }

                if (!empty($errors)) {
                    $invalidBlocks[] = [
                        'block_id' => $block['id'],
                        'nomor_permohonan' => $block['nomor_permohonan'],
                        'errors' => $errors
                    ];
                }

                $previousHash = $block['block_hash'];
            }

            return $this->respond([
                'status' => 'success',
                'message' => $isValid ? 'Blockchain valid' : 'Blockchain tidak valid',
                'data' => [
                    'is_valid' => $isValid,
                    'total_blocks' => count($allBlocks),
                    'invalid_count' => count($invalidBlocks),
                    'invalid_blocks' => $invalidBlocks
                ]
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal memvalidasi blockchain',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function backups()
    {
        // [GET] /api/backups - Mengambil semua data backup.
        try {
            $backups = $this->backupModel->getAllBackups();

            return $this->respond([
                'status' => 'success',
                'message' => 'Data backups berhasil diambil',
                'data' => $backups,
                'total' => count($backups)
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal mengambil data backups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function whitelist()
    {
        // [GET] /api/whitelist - Mengambil semua IP yang ada di whitelist.
        try {
            $whitelist = $this->whitelistModel->getAllIPs();

            return $this->respond([
                'status' => 'success',
                'message' => 'Data whitelist berhasil diambil',
                'data' => $whitelist,
                'total' => count($whitelist)
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal mengambil data whitelist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addWhitelist()
    {
        // [POST] /api/whitelist - Menambahkan IP baru ke dalam whitelist.
        try {
            $rules = [
                'ip_address' => 'required|valid_ip',
                'description' => 'permit_empty|max_length[255]'
            ];

            if (!$this->validate($rules)) {
                return $this->failValidationErrors($this->validator->getErrors());
            }

            $ipAddress = $this->request->getPost('ip_address');
            $description = $this->request->getPost('description') ?? '';
            $addedBy = $this->request->getPost('added_by') ?? 'api';

            if ($this->whitelistModel->addIP($ipAddress, $description, $addedBy)) {
                return $this->respondCreated([
                    'status' => 'success',
                    'message' => "IP {$ipAddress} berhasil ditambahkan ke whitelist"
                ]);
            }

            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal menambahkan IP ke whitelist'
            ], 500);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal menambahkan IP ke whitelist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function activateWhitelist($id = null)
    {
        // [PUT] /api/whitelist/{id}/activate - Mengaktifkan sebuah IP di whitelist.
        try {
            if (!$id) {
                return $this->failValidationError('ID harus diisi');
            }

            if ($this->whitelistModel->activateIP($id)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'IP berhasil diaktifkan'
                ], 200);
            }

            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal mengaktifkan IP'
            ], 500);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal mengaktifkan IP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deactivateWhitelist($id = null)
    {
        // [PUT] /api/whitelist/{id}/deactivate - Menonaktifkan sebuah IP di whitelist.
        try {
            if (!$id) {
                return $this->failValidationError('ID harus diisi');
            }

            if ($this->whitelistModel->deactivateIP($id)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'IP berhasil dinonaktifkan'
                ], 200);
            }

            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal menonaktifkan IP'
            ], 500);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal menonaktifkan IP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteWhitelist($id = null)
    {
        // [DELETE] /api/whitelist/{id} - Menghapus sebuah IP dari whitelist.
        try {
            if (!$id) {
                return $this->failValidationError('ID harus diisi');
            }

            if ($this->whitelistModel->removeIP($id)) {
                return $this->respondDeleted([
                    'status' => 'success',
                    'message' => 'IP berhasil dihapus dari whitelist'
                ]);
            }

            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal menghapus IP dari whitelist'
            ], 500);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal menghapus IP dari whitelist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function stats()
    {
        // [GET] /api/stats - Mengambil statistik umum dari sistem blockchain.
        try {
            $allBlocks = $this->blockModel->getAllBlocks();
            $totalBackups = $this->backupModel->countBackups();
            $totalWhitelist = $this->whitelistModel->countAllResults(false);
            $activeWhitelist = count($this->whitelistModel->getActiveIPs());

            // FIX: Get latest block by timestamp, not by ID position in array
            $latestBlockByTime = $this->blockModel->getLatestBlockByTimestamp();
            
            $stats = [
                'total_blocks' => count($allBlocks),
                'total_backups' => $totalBackups,
                'total_whitelist' => $totalWhitelist,
                'active_whitelist' => $activeWhitelist,
                'latest_block_time' => $latestBlockByTime ? $latestBlockByTime['timestamp'] : null,
                'genesis_block' => !empty($allBlocks) ? $allBlocks[0] : null
            ];

            return $this->respond([
                'status' => 'success',
                'message' => 'Statistik berhasil diambil',
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal mengambil statistik',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recovery($id = null)
    {
        // [POST] /api/recovery/{id} - Memulihkan satu blok secara manual dari backup.
        try {
            if (!$id) {
                return $this->failValidationError('ID block harus diisi');
            }

            $block = $this->blockModel->find($id);

            if (!$block) {
                return $this->failNotFound('Block tidak ditemukan');
            }

            $backup = $this->backupModel->getBackupByIdentifier(
                $block['nomor_permohonan'],
                $block['tanggal_dokumen']
            );

            if (!$backup) {
                return $this->failNotFound('Backup tidak ditemukan untuk block ini');
            }

            $recoveryData = [
                'nomor_permohonan' => $backup['nomor_permohonan'],
                'nomor_dokumen' => $backup['nomor_dokumen'],
                'tanggal_dokumen' => $backup['tanggal_dokumen'],
                'tanggal_filing' => $backup['tanggal_filing'],
                'dokumen_base64' => $backup['dokumen_base64'],
                'ip_address' => $backup['ip_address'],
                'block_hash' => $backup['block_hash'],
                'previous_hash' => $backup['previous_hash']
            ];

            if ($this->blockModel->update($id, $recoveryData)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'Block berhasil di-recovery dari backup',
                    'data' => [
                        'block_id' => $id,
                        'nomor_permohonan' => $backup['nomor_permohonan'],
                        'recovered_at' => date('Y-m-d H:i:s')
                    ]
                ], 200);
            }

            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal melakukan recovery'
            ], 500);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Gagal melakukan recovery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkIntegrity()
    {
        // [POST] /api/check-integrity - Memeriksa integritas data menggunakan konsensus 3 database.
        try {
            // Gunakan MajorityRecovery untuk consensus check dari 3 database
            $majorityRecovery = new \App\Libraries\MajorityRecovery();
            $checkResult = $majorityRecovery->check();

            $manipulated = [];

            // Ambil data yang corrupt (minority atau no consensus)
            if (!empty($checkResult['details'])) {
                foreach ($checkResult['details'] as $detail) {
                    // Include minority corrupt dan no consensus items
                    if (in_array($detail['status'], ['minority', 'no_consensus'])) {
                        // Extract data dari salah satu database yang ada
                        $blockData = null;
                        if (!empty($detail['data']['userdb'])) {
                            $blockData = $detail['data']['userdb'];
                        } elseif (!empty($detail['data']['admindb'])) {
                            $blockData = $detail['data']['admindb'];
                        } elseif (!empty($detail['data']['konsensus'])) {
                            $blockData = $detail['data']['konsensus'];
                        }

                        if ($blockData) {
                            // Tentukan database mana yang corrupt
                            $dbStatus = [
                                'userdb' => 'unknown',
                                'admindb' => 'unknown',
                                'konsensusdb' => 'unknown'
                            ];

                            if (!empty($detail['corrupt_dbs'])) {
                                foreach ($detail['corrupt_dbs'] as $corruptDb) {
                                    if ($corruptDb === 'userdb') $dbStatus['userdb'] = 'corrupt';
                                    elseif ($corruptDb === 'admindb') $dbStatus['admindb'] = 'corrupt';
                                    elseif ($corruptDb === 'konsensus') $dbStatus['konsensusdb'] = 'corrupt';
                                }
                            }

                            // Database yang sehat (tidak corrupt)
                            if ($detail['status'] === 'minority') {
                                foreach (array_keys($dbStatus) as $db) {
                                    $dbKey = $db === 'konsensusdb' ? 'konsensus' : $db;
                                    if (!in_array($dbKey, $detail['corrupt_dbs'] ?? [])) {
                                        $dbStatus[$db] = 'healthy';
                                    }
                                }
                            }

                            $detailDescription = $detail['status'] === 'minority'
                                ? 'Minority Corrupt: ' . implode(', ', $detail['corrupt_dbs'] ?? []) . ' mismatch'
                                : 'No Consensus: All databases have different data';

                            $manipulated[] = [
                                'block_id' => $blockData['id'] ?? 'N/A',
                                'nomor_permohonan' => $blockData['nomor_permohonan'] ?? 'N/A',
                                'nomor_dokumen' => $blockData['nomor_dokumen'] ?? 'N/A',
                                'tanggal_dokumen' => $blockData['tanggal_dokumen'] ?? 'N/A',
                                'stored_hash' => $blockData['block_hash'] ?? 'N/A',
                                'calculated_hash' => $detail['majority_hash'] ?? 'N/A',
                                'has_backup' => false,
                                'can_recover' => true,
                                'database_status' => $dbStatus,
                                'status' => $detail['status']
                            ];
                            // ---- Bangun field_changes: perbandingan nilai per field per DB corrupt ----
                            $compareFields = [
                                'nomor_permohonan', 'nomor_dokumen',
                                'tanggal_dokumen', 'tanggal_filing', 'block_hash'
                            ];
                            $fieldLabels = [
                                'nomor_permohonan' => 'Nomor Permohonan',
                                'nomor_dokumen'    => 'Nomor Dokumen',
                                'tanggal_dokumen'  => 'Tanggal Dokumen',
                                'tanggal_filing'   => 'Tanggal Filing',
                                'block_hash'       => 'Hash Blok',
                            ];

                            // Cari DB majority (sehat) sebagai data referensi asli
                            $majorityDb   = null;
                            $majorityData = null;
                            foreach (['userdb', 'admindb', 'konsensus'] as $db) {
                                if (!in_array($db, $detail['corrupt_dbs'] ?? [])) {
                                    $majorityDb   = $db;
                                    $majorityData = $detail['data'][$db] ?? null;
                                    break;
                                }
                            }

                            // Bangun perbandingan nilai per field untuk setiap DB corrupt
                            $fieldChanges = [];
                            foreach ($detail['corrupt_dbs'] ?? [] as $corruptDb) {
                                $corruptData = $detail['data'][$corruptDb] ?? null;
                                if (!$corruptData || !$majorityData) continue;
                                foreach ($compareFields as $field) {
                                    $nilaiAsli        = $majorityData[$field] ?? null;
                                    $nilaiManipulasi  = $corruptData[$field] ?? null;
                                    if ($nilaiAsli !== $nilaiManipulasi) {
                                        $fieldChanges[$corruptDb][] = [
                                            'field'            => $field,
                                            'label'            => $fieldLabels[$field] ?? $field,
                                            'nilai_asli'       => $nilaiAsli,
                                            'nilai_manipulasi' => $nilaiManipulasi,
                                        ];
                                    }
                                }
                            }

                            $dbLabelMap = [
                                'userdb'    => 'User DB (poa_user_db)',
                                'admindb'   => 'Admin DB (poa_admin_db)',
                                'konsensus' => 'Konsensus DB (poa_konsensus_db)',
                            ];

                            // Log manipulasi dengan data lengkap
                            $this->activityLogModel->logActivity([
                                'action_type'   => 'MANIPULATE',
                                'block_id'      => $blockData['id'] ?? null,
                                'identifier'    => $blockData['nomor_permohonan'] ?? 'N/A',
                                'status'        => 'Manipulated',
                                'description'   => $detailDescription . ' - Detected via consensus check',
                                'original_data' => [
                                    'status'          => $detail['status'],
                                    'majority_db'     => $majorityDb,
                                    'majority_db_label' => $dbLabelMap[$majorityDb] ?? $majorityDb,
                                    'corrupt_dbs'     => $detail['corrupt_dbs'] ?? [],
                                    'field_changes'   => $fieldChanges,
                                    'total_diperiksa' => $checkResult['total_checked'] ?? 0,
                                    'total_corrupt'   => ($checkResult['minority_corrupt'] ?? 0) + ($checkResult['no_consensus'] ?? 0),
                                    'block_hash_asli' => $majorityData['block_hash'] ?? null,
                                    'block_id'        => $blockData['id'] ?? null,
                                ],
                                'modified_data' => [
                                    'recommendation' => $detail['recommendation'] ?? 'Review required',
                                    'db_labels'      => $dbLabelMap,
                                ],
                            ]);
                        }
                    }
                }
            }

            $summary = [
                'total_blocks' => $checkResult['total_checked'] ?? 0,
                'healthy_blocks' => $checkResult['healthy'] ?? 0,
                'manipulated_blocks' => count($manipulated),
                'minority_corrupt' => $checkResult['minority_corrupt'] ?? 0,
                'no_consensus' => $checkResult['no_consensus'] ?? 0,
                'integrity_status' => count($manipulated) === 0 ? 'intact' : 'compromised',
                'can_recover' => count($manipulated)
            ];

            return $this->respond([
                'status' => 'success',
                'message' => count($manipulated) === 0 ? '✓ All data is intact across all 3 databases' : "⚠ " . count($manipulated) . " records have inconsistent data across databases",
                'summary' => $summary,
                'manipulated_data' => $manipulated,
                'checked_at' => date('Y-m-d H:i:s'),
                'check_method' => 'consensus_3_database'
            ]);
        } catch (\Exception $e) {
            log_message('error', '[API_CHECK_INTEGRITY] Error: ' . $e->getMessage());
            return $this->fail([
                'status' => 'error',
                'message' => 'Error checking integrity',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function autoRecovery()
    {
        // [POST] /api/auto-recovery - Menjalankan pemulihan otomatis berdasarkan konsensus 3 database.
        try {
            $majorityRecovery = new \App\Libraries\MajorityRecovery();
            $checkResult = $majorityRecovery->check();

            $recoveredCount = 0;
            $failedCount = 0;
            $recoveredBlocks = [];
            $recoveredData = [];

            // Filter records yang memiliki consensus mismatch
            foreach ($checkResult['details'] as $detail) {
                $blockId = $detail['block_id'];
                $status = $detail['status'];
                $corruptDbs = $detail['corrupt_dbs'] ?? [];

                // Recovery dapat dilakukan untuk status 'minority' (2-1 voting)
                if ($status === 'minority' && !empty($corruptDbs)) {
                    // Ambil data dari database yang SEHAT (majority)
                    $blockData = null;
                    $healthyDb = null;

                    if (in_array('userdb', $corruptDbs)) {
                        $blockData = $detail['data']['admindb'] ?? $detail['data']['konsensus'];
                        $healthyDb = !empty($detail['data']['admindb']) ? 'admindb' : 'konsensus';
                    } elseif (in_array('admindb', $corruptDbs)) {
                        $blockData = $detail['data']['userdb'] ?? $detail['data']['konsensus'];
                        $healthyDb = !empty($detail['data']['userdb']) ? 'userdb' : 'konsensus';
                    } elseif (in_array('konsensus', $corruptDbs)) {
                        $blockData = $detail['data']['userdb'] ?? $detail['data']['admindb'];
                        $healthyDb = !empty($detail['data']['userdb']) ? 'userdb' : 'admindb';
                    }

                    if ($blockData && $healthyDb) {
                        // Prepare recovery data dengan hash yang sudah benar
                        $recoveryData = [
                            'nomor_permohonan' => $blockData['nomor_permohonan'],
                            'nomor_dokumen' => $blockData['nomor_dokumen'],
                            'tanggal_dokumen' => $blockData['tanggal_dokumen'],
                            'tanggal_filing' => $blockData['tanggal_filing'],
                            'dokumen_base64' => $blockData['dokumen_base64'],
                            'ip_address' => $blockData['ip_address'],
                            'block_hash' => $blockData['block_hash'],  // Gunakan hash dari majority database
                            'previous_hash' => $blockData['previous_hash']
                        ];

                        if ($this->blockModel->update($blockId, $recoveryData)) {
                            $recoveredCount++;
                            $recoveredBlocks[] = [
                                'block_id' => $blockId,
                                'nomor_permohonan' => $blockData['nomor_permohonan'],
                                'corrupted_databases' => $corruptDbs,
                                'recovered_from' => $healthyDb
                            ];

                            // Log recovery
                            $this->activityLogModel->logActivity([
                                'action_type' => 'RECOVER',
                                'block_id' => $blockId,
                                'identifier' => $blockData['nomor_permohonan'],
                                'status' => 'Recovered',
                                'description' => "Data dipulihkan dari {$healthyDb} database (consensus voting: 2-1)",
                                'original_data' => [
                                    'corrupted_databases' => $corruptDbs,
                                    'recovery_method' => 'consensus_3_database'
                                ],
                                'modified_data' => ['block_hash' => $blockData['block_hash']]
                            ]);
                        } else {
                            $failedCount++;
                        }
                    }
                }
                // Status 'no_consensus' (1-1-1) tidak bisa di-recover, perlu manual review
                elseif ($status === 'no_consensus') {
                    $failedCount++;
                }
            }

            $message = "Recovery completed: {$recoveredCount} recovered using 3-database consensus, {$failedCount} failed or require manual review";

            return $this->respond([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'recovered_count' => $recoveredCount,
                    'failed_count' => $failedCount,
                    'recovered_blocks' => $recoveredBlocks,
                    'recovery_method' => 'consensus_3_database_majority_voting',
                    'recovered_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            log_message('error', '[API_AUTO_RECOVERY] Error: ' . $e->getMessage());
            return $this->fail([
                'status' => 'error',
                'message' => 'Error during auto-recovery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function activityLogs()
    {
        // [GET] /api/activity-logs - Mengambil daftar log aktivitas terbaru.
        try {
            $limit = $this->request->getGet('limit') ?? 50;
            $type = $this->request->getGet('type');

            if ($type) {
                $logs = $this->activityLogModel->getLogsByType($type, $limit);
            } else {
                $logs = $this->activityLogModel->getRecentLogs($limit);
            }

            return $this->respond([
                'status' => 'success',
                'message' => 'Activity logs retrieved successfully',
                'data' => [
                    'logs' => $logs
                ]
            ]);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Error retrieving activity logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recoveryStatus()
    {
        // [GET] /api/recovery/status - Get current countdown recovery status
        try {
            $countdownService = new \App\Libraries\CountdownRecoveryService();
            $statusData = $countdownService->getStateForApi();

            return $this->respond([
                'status' => 'success',
                'message' => 'Recovery countdown status retrieved',
                'data' => $statusData,
                'retrieved_at' => date('Y-m-d H:i:s')
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Error retrieving recovery status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recoveryCountdownReset()
    {
        // [POST] /api/recovery/countdown/reset - Force reset countdown (admin only)
        try {
            // Optional: Add authorization check here
            // Example: if (!$this->isAdmin()) { return $this->fail('Unauthorized', 403); }

            $countdownService = new \App\Libraries\CountdownRecoveryService();
            
            if ($countdownService->forceReset()) {
                $this->activityLogModel->logActivity([
                    'action_type' => 'RECOVERY_COUNTDOWN_RESET',
                    'status' => 'INFO',
                    'description' => 'Recovery countdown reset by admin via API'
                ]);

                return $this->respond([
                    'status' => 'success',
                    'message' => 'Recovery countdown reset successfully',
                    'reset_at' => date('Y-m-d H:i:s')
                ], 200);
            }

            return $this->fail([
                'status' => 'error',
                'message' => 'Failed to reset recovery countdown'
            ], 500);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Error resetting recovery countdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ============================================================
     * CONSENSUS & RECOVERY API ENDPOINTS
     * ============================================================
     * 
     * REST API for 2/3 Majority Consensus & Recovery System
     */

    /**
     * GET /api/consensus/check
     * 
     * Check consensus status across all 3 databases
     * 
     * Response:
     * {
     *   "status": "success|critical",
     *   "consensus": {
     *     "total_checked": 100,
     *     "healthy": 98,
     *     "minority_corrupt": 2,
     *     "no_consensus": 0,
     *     "no_consensus": 0
     *   },
     *   "health_percentage": 98.0,
     *   "execution_time_ms": 245.32
     * }
     */
    public function consensusCheck()
    {
        try {
            $startTime = microtime(true);
            $result = $this->majorityRecovery->check();
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Determine overall status
            $anomalyCount = ($result['minority_corrupt'] ?? 0)
                + ($result['no_consensus'] ?? 0);
            $status = $anomalyCount === 0 ? 'healthy' : 'anomalies_detected';
            if (($result['no_consensus'] ?? 0) > 0) {
                $status = 'critical';
            }

            $healthPercent = $result['total_checked'] > 0
                ? round(($result['healthy'] / $result['total_checked']) * 100, 2)
                : 100;

            return $this->respond([
                'status' => 'success',
                'consensus_status' => $status,
                'consensus' => [
                    'total_checked'      => $result['total_checked'] ?? 0,
                    'healthy'            => $result['healthy'] ?? 0,
                    'minority_corrupt'   => $result['minority_corrupt'] ?? 0,
                    'no_consensus'       => $result['no_consensus'] ?? 0,
                    'missing_in_db'      => $result['missing_in_db'] ?? 0,
                    'hash_repair_needed' => $result['hash_repair'] ?? 0,
                ],
                'health_percentage' => $healthPercent,
                'anomaly_details' => $result['details'] ?? [],
                'execution_time_ms' => $executionTime,
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Consensus check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/consensus/recover
     * 
     * Trigger automatic recovery from 2/3 majority
     * 
     * POST Body:
     * {
     *   "purge_minority": false
     * }
     * 
     * Response:
     * {
     *   "status": "success",
     *   "recovery": {
     *     "total_attempted": 2,
     *     "success": 2,
     *     "failed": 0
     *   }
     * }
     */
    public function consensusRecover()
    {
        try {
            // Check consensus first
            $consensusResult = $this->majorityRecovery->check();

            // Filter items to recover
            $toRecover = array_filter(
                $consensusResult['details'] ?? [],
                fn($item) => in_array($item['status'] ?? '', ['minority', 'missing', 'hash_repair'])
            );

            if (empty($toRecover)) {
                return $this->respond([
                    'status' => 'success',
                    'message' => 'No items require recovery',
                    'recovery' => [
                        'total_attempted' => 0,
                        'success' => 0,
                        'failed' => 0,
                    ]
                ], 200);
            }

            // Get request data
            $request = $this->request->getJSON();
            $purgeMinority = $request->purge_minority ?? false;
            $performedBy = $request->performed_by ?? 'api_user';

            // Perform recovery
            if ($purgeMinority) {
                $result = $this->majorityRecovery->recoverWithPurge(
                    items: array_values($toRecover),
                    performedBy: $performedBy,
                    purgeMinority: true
                );
            } else {
                $result = $this->majorityRecovery->recover(
                    items: array_values($toRecover),
                    performedBy: $performedBy
                );
            }

            return $this->respond([
                'status' => 'success',
                'message' => sprintf(
                    'Recovery completed: %d success, %d failed',
                    $result['success'],
                    $result['failed']
                ),
                'recovery' => [
                    'total_attempted' => $result['total_attempted'],
                    'success' => $result['success'],
                    'failed' => $result['failed'],
                    'skipped' => $result['skipped'] ?? 0,
                    'purge_minority' => $purgeMinority,
                    'total_purged' => $result['total_purged_records'] ?? 0,
                ],
                'details' => $result['details'] ?? [],
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Recovery operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/consensus/health
     * 
     * Get comprehensive system health report
     * 
     * Response includes:
     * - Overall health status
     * - Node-by-node status
     * - Performance metrics
     * - Active alerts
     * - Recommendations
     */
    public function consensusHealth()
    {
        try {
            $health = $this->monitoring->getSystemHealth();

            return $this->respond([
                'status' => 'success',
                'health' => $health,
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Failed to get health report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/consensus/dashboard
     * 
     * Get monitoring dashboard data (7-day trend)
     * 
     * Query Parameters:
     * - days: Number of days to look back (default: 7)
     */
    public function consensusDashboard()
    {
        try {
            $days = $this->request->getVar('days') ?? 7;
            $days = min((int)$days, 30); // Cap at 30 days

            $dashboard = $this->monitoring->getDashboardData(days: $days);

            return $this->respond([
                'status' => 'success',
                'dashboard' => $dashboard,
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Failed to get dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/consensus/alerts
     * 
     * Get active alerts
     * 
     * Query Parameters:
     * - severity: Filter by severity (critical|warning|info)
     * - limit: Max alerts to return (default: 50)
     */
    public function consensusAlerts()
    {
        try {
            $severity = $this->request->getVar('severity');
            $limit = (int)($this->request->getVar('limit') ?? 50);

            $alerts = $this->monitoring->getActiveAlerts(severity: $severity);

            return $this->respond([
                'status' => 'success',
                'alert_count' => count($alerts),
                'alerts' => array_slice($alerts, 0, $limit),
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Failed to get alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/consensus/monitor
     * 
     * Run monitoring + auto-recovery with optional purge
     * 
     * POST Body:
     * {
     *   "purge_minority": false
     * }
     * 
     * Complete workflow: check → alert → recover → (optionally purge)
     */
    public function consensusMonitor()
    {
        try {
            $request = $this->request->getJSON();
            $purgeMinority = $request->purge_minority ?? false;

            $result = $this->monitoring->monitorAndRecover(
                purgeMinority: $purgeMinority,
                performedBy: 'api_monitor'
            );

            return $this->respond([
                'status' => 'success',
                'monitoring_result' => $result,
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Monitoring operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/consensus/rollback/{id}
     * 
     * Rollback a previous recovery operation
     * 
     * Parameters:
     * - id: Recovery history ID
     * 
     * Warning: Cannot be undone!
     */
    public function consensusRollback($id = null)
    {
        try {
            if (!$id || !is_numeric($id)) {
                return $this->failValidationError('Recovery history ID required');
            }

            $result = $this->majorityRecovery->rollback(
                historyId: (int)$id,
                performedBy: 'api_admin'
            );

            if (!$result['success']) {
                return $this->fail([
                    'status' => 'error',
                    'message' => $result['error'],
                ], 400);
            }

            return $this->respond([
                'status' => 'success',
                'message' => 'Recovery successfully rolled back',
                'result' => $result,
            ], 200);
        } catch (\Exception $e) {
            return $this->fail([
                'status' => 'error',
                'message' => 'Rollback operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}

