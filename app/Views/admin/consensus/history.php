<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">Riwayat Pemulihan</h1>
            <p class="mt-2 text-slate-600">Riwayat pemulihan otomatis dan rollback konsensus mayoritas</p>
        </div>
        <a href="<?= base_url('/admin/monitoring') ?>"
            class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold transition-colors">
            ← Kembali ke Pemantauan
        </a>
    </div>
</div>

<!-- Statistics Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <p class="text-sm text-slate-600 mb-2">Total Pemulihan</p>
        <p class="text-3xl font-bold text-slate-900"><?= $stats['total_recoveries'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-2">Selamanya</p>
    </div>

    <div class="bg-emerald-50 border-2 border-emerald-200 rounded-lg p-6">
        <p class="text-sm text-emerald-700 mb-2">Tingkat Keberhasilan</p>
        <p class="text-3xl font-bold text-emerald-900">
            <?php
            $total = $stats['total_recoveries'] ?? 0;
            $success = $stats['success_count'] ?? 0;
            echo $total > 0 ? round(($success / $total) * 100, 1) : 100;
            ?>%
        </p>
        <p class="text-xs text-emerald-600 mt-2"><?= $stats['success_count'] ?? 0 ?> berhasil</p>
    </div>

    <div class="bg-amber-50 border-2 border-amber-200 rounded-lg p-6">
        <p class="text-sm text-amber-700 mb-2">Minggu Ini</p>
        <p class="text-3xl font-bold text-amber-900"><?= $stats['recoveries_this_week'] ?? 0 ?></p>
        <p class="text-xs text-amber-600 mt-2">7 hari terakhir</p>
    </div>

    <div class="bg-slate-50 border-2 border-slate-200 rounded-lg p-6">
        <p class="text-sm text-slate-600 mb-2">Hari Ini</p>
        <p class="text-3xl font-bold text-slate-900"><?= $stats['recoveries_today'] ?? 0 ?></p>
        <p class="text-xs text-slate-500 mt-2">
            <?php if (!empty($stats['last_recovery'])): ?>
                Terakhir: <?= date('H:i', strtotime($stats['last_recovery']['created_at'])) ?>
            <?php else: ?>
                Tidak ada aktivitas
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white border-2 border-slate-200 rounded-lg p-6 mb-6">
    <form method="GET" action="<?= base_url('/admin/consensus/history') ?>" class="flex gap-4 items-end">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-slate-700 mb-2">Jenis Pemulihan</label>
            <select name="type" class="w-full px-4 py-2 border-2 border-slate-200 rounded-lg focus:border-slate-900 focus:outline-none">
                <option value="">Semua Jenis</option>
                <option value="consensus_auto" <?= ($filters['recovery_type'] ?? '') === 'consensus_auto' ? 'selected' : '' ?>>Pemulihan Otomatis</option>
                <option value="consensus_manual" <?= ($filters['recovery_type'] ?? '') === 'consensus_manual' ? 'selected' : '' ?>>Pemulihan Manual</option>
                <option value="rollback" <?= ($filters['recovery_type'] ?? '') === 'rollback' ? 'selected' : '' ?>>Pengembalian</option>
            </select>
        </div>

        <div class="flex-1">
            <label class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
            <select name="status" class="w-full px-4 py-2 border-2 border-slate-200 rounded-lg focus:border-slate-900 focus:outline-none">
                <option value="">Semua Status</option>
                <option value="success" <?= ($filters['status'] ?? '') === 'success' ? 'selected' : '' ?>>Berhasil</option>
                <option value="failed" <?= ($filters['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Gagal</option>
                <option value="rolled_back" <?= ($filters['status'] ?? '') === 'rolled_back' ? 'selected' : '' ?>>Dikembalikan</option>
            </select>
        </div>

        <div class="flex-1">
            <label class="block text-sm font-semibold text-slate-700 mb-2">Limit</label>
            <select name="limit" class="w-full px-4 py-2 border-2 border-slate-200 rounded-lg focus:border-slate-900 focus:outline-none">
                <option value="50" selected>50 catatan</option>
                <option value="100">100 catatan</option>
                <option value="200">200 catatan</option>
            </select>
        </div>

        <button type="submit" class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold transition-colors">
            Terapkan Filter
        </button>

        <a href="<?= base_url('/admin/consensus/history') ?>" class="px-6 py-2 bg-slate-200 text-slate-900 rounded-lg hover:bg-slate-300 font-semibold transition-colors">
            Atur Ulang
        </a>
    </form>
</div>

<!-- History Table -->
<div class="bg-white border-2 border-slate-200 rounded-lg overflow-hidden">
    <div class="border-b-2 border-slate-200 p-6">
        <h2 class="text-xl font-bold text-slate-900">Recovery Records</h2>
        <p class="mt-1 text-sm text-slate-600">Showing <?= count($history) ?> records</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Record Key</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Consensus Result</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Recovery</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Perbandingan Data</th>
                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-700">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Performed By</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Timestamp</th>
                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (!empty($history)): ?>
                    <?php foreach ($history as $record): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm font-bold text-slate-900">#<?= $record['id'] ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($record['recovery_type'] === 'consensus_auto'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-900 border border-emerald-300">
                                        Auto
                                    </span>
                                <?php elseif ($record['recovery_type'] === 'consensus_manual'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-amber-100 text-amber-900 border border-amber-300">
                                        Manual
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-slate-100 text-slate-900 border border-slate-300">
                                        Rollback
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-xs text-slate-600"><?= substr($record['record_key'], 0, 16) ?>...</span>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $consensusResult = $record['consensus_result'] ? json_decode($record['consensus_result'], true) : null;
                                if ($consensusResult && is_array($consensusResult)):
                                    // Hitung votes untuk menentukan majority
                                    $votes = array_count_values(array_filter($consensusResult));
                                    arsort($votes);
                                    $majorityHash = array_key_first($votes);
                                ?>
                                    <div class="space-y-1">
                                        <?php foreach ($consensusResult as $db => $hash): ?>
                                            <?php
                                            $isMajority = ($hash === $majorityHash && $votes[$majorityHash] >= 2);
                                            $isTarget = ($db === $record['target_db']);
                                            ?>
                                            <div class="flex items-center gap-2 text-xs">
                                                <?php if ($isMajority): ?>
                                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded font-semibold border border-emerald-300">
                                                        ✓ <?= strtoupper($db) ?>
                                                    </span>
                                                    <span class="text-emerald-600 text-[10px]">MAJORITY</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded font-semibold border border-red-300">
                                                        ✗ <?= strtoupper($db) ?>
                                                    </span>
                                                    <span class="text-red-600 text-[10px]">MINORITY</span>
                                                <?php endif; ?>
                                                <?php if ($isTarget): ?>
                                                    <span class="text-amber-600 text-[10px]">(REPAIRED)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="font-semibold text-emerald-700"><?= strtoupper($record['source_db']) ?></span>
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                    <span class="font-semibold text-amber-700"><?= strtoupper($record['target_db']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($record['before_data']) || !empty($record['after_data'])): ?>
                                    <details class="cursor-pointer">
                                        <summary class="text-xs font-semibold text-slate-600 hover:text-slate-900">Lihat Detail</summary>
                                        <div class="mt-3 p-3 bg-slate-100 border border-slate-200 rounded-lg w-max max-w-lg">
                                            <?php
                                            $beforeData = is_string($record['before_data']) ? json_decode($record['before_data'], true) : ($record['before_data'] ?? []);
                                            $afterData = is_string($record['after_data']) ? json_decode($record['after_data'], true) : ($record['after_data'] ?? []);

                                            $fieldsToCompare = ['nama_dokumen', 'nomor_permohonan', 'nomor_dokumen', 'tanggal_dokumen', 'tanggal_filing', 'block_hash', 'previous_hash'];
                                            ?>
                                            <table class="min-w-full text-xs">
                                                <thead class="font-bold">
                                                    <tr class="border-b border-slate-300">
                                                        <td class="py-1 pr-2">Field</td>
                                                        <td class="py-1 px-2">Sebelum (<?= strtoupper($record['target_db']) ?>)</td>
                                                        <td class="py-1 px-2">Sesudah (Mayoritas)</td>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($fieldsToCompare as $field): ?>
                                                        <?php
                                                        $beforeValue = $beforeData[$field] ?? 'NULL';
                                                        $afterValue = $afterData[$field] ?? 'NULL';

                                                        $displayBefore = (strpos($field, 'hash') !== false && $beforeValue !== 'NULL') ? substr($beforeValue, 0, 8) . '...' : $beforeValue;
                                                        $displayAfter = (strpos($field, 'hash') !== false && $afterValue !== 'NULL') ? substr($afterValue, 0, 8) . '...' : $afterValue;

                                                        $isChanged = $beforeValue !== $afterValue;
                                                        ?>
                                                        <tr class="border-b border-slate-200">
                                                            <td class="py-1.5 pr-2 font-semibold text-slate-700"><?= str_replace('_', ' ', $field) ?></td>
                                                            <td class="py-1.5 px-2 font-mono <?= $isChanged ? 'text-red-600 bg-red-50' : 'text-slate-600' ?>" title="<?= esc($beforeValue) ?>"><?= esc($displayBefore) ?></td>
                                                            <td class="py-1.5 px-2 font-mono <?= $isChanged ? 'text-emerald-600 bg-emerald-50' : 'text-slate-600' ?>" title="<?= esc($afterValue) ?>"><?= esc($displayAfter) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </details>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">Tidak ada detail</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($record['status'] === 'success'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-900">
                                        ✓ Success
                                    </span>
                                <?php elseif ($record['status'] === 'failed'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-red-100 text-red-900">
                                        ✗ Failed
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-slate-100 text-slate-900">
                                        ↶ Rolled Back
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                <?= esc($record['performed_by']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                <?= date('d/m/Y H:i:s', strtotime($record['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($record['status'] === 'success' && $record['recovery_type'] !== 'rollback'): ?>
                                    <form action="<?= base_url('/admin/consensus/rollback/' . $record['id']) ?>" method="POST"
                                        onsubmit="return confirm('Rollback recovery ini? Data akan dikembalikan ke kondisi sebelum recovery.')">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                            class="px-3 py-1 bg-amber-600 text-white rounded-lg hover:bg-amber-700 text-xs font-semibold transition-colors">
                                            Rollback
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-slate-500">
                            <svg class="w-12 h-12 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="font-semibold">No recovery history found</p>
                            <p class="text-sm mt-1">Recovery records will appear here</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>