<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Consensus Check Results</h1>
            <p class="mt-2 text-slate-600">Hasil pengecekan konsensus mayoritas dari 3 database</p>
        </div>
        <a href="<?= base_url('/admin/monitoring') ?>"
            class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold transition-colors">
            ← Back to Monitoring
        </a>
    </div>
</div>

<?php
$anomalyCount = ($stats['minority_corrupt'] ?? 0) + ($stats['hash_repair'] ?? 0) + ($stats['no_consensus'] ?? 0) + ($stats['missing_in_db'] ?? 0);
$recoverableCount = ($stats['minority_corrupt'] ?? 0) + ($stats['hash_repair'] ?? 0) + ($stats['missing_in_db'] ?? 0);
$hasAnomaly = $anomalyCount > 0;
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <p class="text-sm text-slate-600 mb-2">Total Checked</p>
        <p class="text-3xl font-bold text-slate-900"><?= $stats['total_checked'] ?></p>
    </div>

    <div class="bg-emerald-50 border-2 border-emerald-200 rounded-lg p-6">
        <p class="text-sm text-emerald-700 mb-2">Healthy</p>
        <p class="text-3xl font-bold text-emerald-900"><?= $stats['healthy'] ?></p>
    </div>

    <div class="<?= ($stats['minority_corrupt'] ?? 0) > 0 ? 'bg-red-600 text-white border-2 border-red-800' : 'bg-amber-50 border-2 border-amber-200' ?> rounded-lg p-6">
        <p class="text-sm <?= ($stats['minority_corrupt'] ?? 0) > 0 ? 'text-white/90' : 'text-amber-700' ?> mb-2">Minority Corrupt</p>
        <p class="text-3xl font-bold <?= ($stats['minority_corrupt'] ?? 0) > 0 ? 'text-white' : 'text-amber-900' ?>"><?= $stats['minority_corrupt'] ?></p>
    </div>

    <div class="<?= ($stats['no_consensus'] ?? 0) > 0 ? 'bg-red-600 text-white border-2 border-red-800' : 'bg-red-50 border-2 border-red-200' ?> rounded-lg p-6">
        <p class="text-sm <?= ($stats['no_consensus'] ?? 0) > 0 ? 'text-white/90' : 'text-red-700' ?> mb-2">No Consensus</p>
        <p class="text-3xl font-bold <?= ($stats['no_consensus'] ?? 0) > 0 ? 'text-white' : 'text-red-900' ?>"><?= $stats['no_consensus'] ?></p>
    </div>


</div>

<?php if ($alert): ?>
    <div class="<?= $hasAnomaly ? 'bg-red-600 border-l-4 border-red-800 text-white' : 'bg-amber-50 border-l-4 border-amber-600' ?> p-6 mb-8">
        <div class="flex items-start gap-3">
            <?php if ($hasAnomaly): ?>
                <svg class="w-6 h-6 text-white/90 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            <?php else: ?>
                <svg class="w-6 h-6 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            <?php endif; ?>
            <div>
                <h3 class="<?= $hasAnomaly ? 'text-white font-semibold' : 'font-bold text-amber-900 mb-1' ?>"><?= $hasAnomaly ? 'Inkonsistensi Terdeteksi' : 'Inkonsistensi Terdeteksi' ?></h3>
                <p class="<?= $hasAnomaly ? 'text-white/90' : 'text-amber-800' ?> text-sm">
                    Ditemukan <?= $anomalyCount ?> record yang memerlukan perhatian.

                    <?php if ($recoverableCount > 0): ?>
                        <strong><?= $recoverableCount ?> record</strong> dapat dipulihkan otomatis (minority 2/3, hash legacy, atau sync missing).
                    <?php endif; ?>
                </p>
                <?php if ($recoverableCount > 0): ?>
                    <form action="<?= base_url('/admin/consensus/recover') ?>" method="POST" class="mt-4" onsubmit="return confirm('Recover <?= $recoverableCount ?> record dengan konsensus mayoritas?')">
                        <?= csrf_field() ?>
                        <button type="submit"
                            class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-700 font-semibold text-sm transition-colors">
                            Recover Now
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="bg-emerald-50 border-l-4 border-emerald-600 p-6 mb-8">
        <div class="flex items-start gap-3">
            <svg class="w-6 h-6 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h3 class="font-bold text-emerald-900 mb-1">Semua Database Sehat</h3>
                <p class="text-sm text-emerald-800">
                    Tidak ditemukan inkonsistensi. Ketiga database (UserDB, AdminDB, KonsensusDB) dalam kondisi sinkron sempurna.
                </p>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($details)): ?>
    <div class="bg-white border-2 border-slate-200 rounded-lg overflow-hidden">
        <div class="border-b-2 border-slate-200 p-6">
            <h2 class="text-xl font-bold text-slate-900">Detailed Issues</h2>
            <p class="mt-1 text-sm text-slate-600">Record yang memerlukan perhatian</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Record Key</th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Identifier</th>
                        <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-700">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Checksums</th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Perbandingan Data</th>
                        <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Recommendation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php foreach ($details as $detail): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-xs text-slate-900"><?= substr($detail['record_key'], 0, 16) ?>...</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-slate-900"><?= esc($detail['identifier']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($detail['status'] === 'minority'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-red-100 text-red-900 border border-red-300">
                                        2/3 vs 1/3
                                    </span>
                                <?php elseif ($detail['status'] === 'hash_repair'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-amber-100 text-amber-900 border border-amber-300">
                                        Hash Legacy
                                    </span>
                                <?php elseif ($detail['status'] === 'no_consensus'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-red-100 text-red-900 border border-red-300">
                                        No Consensus
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-slate-100 text-slate-900 border border-slate-300">
                                        <?= ucfirst($detail['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-xs space-y-1.5">
                                    <?php foreach ($detail['checksums'] as $db => $hash): ?>
                                        <?php
                                        $isMajority = isset($detail['majority_hash']) && $hash === $detail['majority_hash'];
                                        $isMinority = !$isMajority && $hash !== null && isset($detail['majority_hash']);
                                        ?>
                                        <div class="flex items-center gap-2">
                                            <?php if ($isMajority): ?>
                                                <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-semibold border border-emerald-300 min-w-[90px] text-center">
                                                    ✓ <?= strtoupper($db) ?>
                                                </span>
                                                <span class="text-emerald-600 text-[10px] font-bold">MAJORITY</span>
                                            <?php elseif ($isMinority): ?>
                                                <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded font-semibold border border-red-300 min-w-[90px] text-center">
                                                    ✗ <?= strtoupper($db) ?>
                                                </span>
                                                <span class="text-red-600 text-[10px] font-bold">MINORITY</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-semibold border border-slate-300 min-w-[90px] text-center">
                                                    <?= strtoupper($db) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="font-mono text-slate-500 text-[10px]">
                                                <?= $hash ? substr($hash, 0, 8) . '...' : 'NULL' ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <details class="cursor-pointer">
                                    <summary class="text-xs font-semibold text-slate-600 hover:text-slate-900">Lihat Detail</summary>
                                    <div class="mt-3 p-3 bg-slate-100 border border-slate-200 rounded-lg w-max max-w-lg">
                                        <?php
                                        $data = $detail['data'];
                                        $majorityHash = $detail['majority_hash'];
                                        $checksums = $detail['checksums'];

                                        $majorityData = null;
                                        foreach ($checksums as $db => $hash) {
                                            if ($hash === $majorityHash && !empty($data[$db])) {
                                                $majorityData = $data[$db];
                                                break;
                                            }
                                        }

                                        if (!$majorityData && !empty($data['userdb'])) {
                                            $majorityData = $data['userdb'];
                                        }

                                        $fieldsToCompare = ['nama_dokumen', 'nomor_permohonan', 'nomor_dokumen', 'tanggal_dokumen', 'tanggal_filing', 'block_hash', 'previous_hash'];
                                        ?>
                                        <table class="min-w-full text-xs">
                                            <thead class="font-bold">
                                                <tr class="border-b border-slate-300">
                                                    <td class="py-1 pr-2">Field</td>
                                                    <td class="py-1 px-2">UserDB</td>
                                                    <td class="py-1 px-2">AdminDB</td>
                                                    <td class="py-1 pl-2">KonsensusDB</td>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($fieldsToCompare as $field): ?>
                                                    <?php
                                                    $majorityValue = $majorityData[$field] ?? 'N/A';
                                                    $userValue = $data['userdb'][$field] ?? 'NULL';
                                                    $adminValue = $data['admindb'][$field] ?? 'NULL';
                                                    $konsensusValue = $data['konsensus'][$field] ?? 'NULL';

                                                    $displayUser = (strpos($field, 'hash') !== false) ? substr($userValue, 0, 8) . '...' : $userValue;
                                                    $displayAdmin = (strpos($field, 'hash') !== false) ? substr($adminValue, 0, 8) . '...' : $adminValue;
                                                    $displayKonsensus = (strpos($field, 'hash') !== false) ? substr($konsensusValue, 0, 8) . '...' : $konsensusValue;
                                                    ?>
                                                    <tr class="border-b border-slate-200">
                                                        <td class="py-1.5 pr-2 font-semibold text-slate-700"><?= str_replace('_', ' ', $field) ?></td>
                                                        <td class="py-1.5 px-2 font-mono <?= $userValue !== $majorityValue ? 'text-red-600 bg-red-50' : 'text-slate-600' ?>" title="<?= esc($userValue) ?>"><?= esc($displayUser) ?></td>
                                                        <td class="py-1.5 px-2 font-mono <?= $adminValue !== $majorityValue ? 'text-red-600 bg-red-50' : 'text-slate-600' ?>" title="<?= esc($adminValue) ?>"><?= esc($displayAdmin) ?></td>
                                                        <td class="py-1.5 pl-2 font-mono <?= $konsensusValue !== $majorityValue ? 'text-red-600 bg-red-50' : 'text-slate-600' ?>" title="<?= esc($konsensusValue) ?>"><?= esc($displayKonsensus) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs text-slate-600"><?= esc($detail['recommendation'] ?? '-') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>