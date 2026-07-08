<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php if (session()->getFlashdata('success') || session()->getFlashdata('error')): ?>
    <?php $isSuccess = session()->getFlashdata('success') !== null; ?>
    <div id="flash-message" class="mb-6 rounded-lg <?= $isSuccess ? 'bg-emerald-600 border-l-4 border-emerald-800' : 'bg-red-600 border-l-4 border-red-800' ?> p-4 text-white overflow-hidden transition-all duration-500 ease-out" role="alert">
        <p class="font-medium"><?= $isSuccess ? session()->getFlashdata('success') : session()->getFlashdata('error') ?></p>
    </div>
<?php endif; ?>

<?php if (!empty($manipulatedData)): ?>
    <div class="mb-6 rounded-lg bg-red-600 border-l-4 border-red-800 p-3 text-white">
        <div class="flex items-center justify-between">
            <div>
                <span class="font-bold">Manipulasi Terdeteksi:</span>
                <span class="text-sm ml-2"><?= count($manipulatedData) ?> dokumen dimanipulasi
                    <?php if (!empty($recoveryResults)): ?>
                        | <?= count($recoveryResults) ?> dipulihkan
                    <?php endif; ?>
                </span>
            </div>
            <button onclick="this.parentElement.parentElement.classList.toggle('hidden')" class="text-white hover:text-red-200 transition-colors" aria-label="close-alert">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1"><?= number_format($stats['total_blocks'] ?? 0) ?></p>
        <p class="text-sm text-slate-600">Total Blok</p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1"><?= number_format($stats['total_backups'] ?? 0) ?></p>
        <p class="text-sm text-slate-600">Total Cadangan</p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1"><?= number_format($stats['active_whitelist'] ?? 0) ?></p>
        <p class="text-sm text-slate-600">IP Aktif (<?= number_format($stats['total_whitelist'] ?? 0) ?> total)</p>
    </div>

    <div class="bg-<?= ($stats['chain_valid'] ?? true) ? 'white' : 'red-600' ?> border-2 border-<?= ($stats['chain_valid'] ?? true) ? 'slate-200' : 'red-700' ?> rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-<?= ($stats['chain_valid'] ?? true) ? 'slate-900' : 'white' ?> rounded-lg p-3">
                <?php if ($stats['chain_valid'] ?? true): ?>
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                <?php else: ?>
                    <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-2xl font-bold text-<?= ($stats['chain_valid'] ?? true) ? 'slate-900' : 'white' ?> mb-1">
            <?= ($stats['chain_valid'] ?? true) ? 'VALID' : 'TIDAK VALID' ?>
        </p>
        <p class="text-sm text-<?= ($stats['chain_valid'] ?? true) ? 'slate-600' : 'red-100' ?>">
            <?php if ($stats['chain_valid'] ?? true): ?>
                Integritas Rantai OK
            <?php else: ?>
                <?= ($stats['manipulated_count'] ?? 0) > 0 ? $stats['manipulated_count'] . ' Data Termanipulasi' : 'Struktur Rantai Tidak Valid' ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white border-2 border-slate-200 rounded-lg">
        <div class="border-b-2 border-slate-200 p-6">
            <h2 class="text-xl font-bold text-slate-900">Quick Actions</h2>
            <p class="mt-1 text-sm text-slate-600">Akses cepat ke fitur utama</p>
        </div>
        <div class="p-6 space-y-3">
            <a href="<?= base_url('/admin/explorer') ?>"
                class="flex items-center justify-between p-4 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors border border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="bg-slate-900 rounded-lg p-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <span class="font-semibold text-slate-900">Blockchain Explorer</span>
                </div>
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>

            <a href="<?= base_url('/admin/backup/create') ?>"
                class="flex items-center justify-between p-4 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors border border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="bg-slate-900 rounded-lg p-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <span class="font-semibold text-slate-900">Create Backup</span>
                </div>
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>

            <a href="<?= base_url('/admin/whitelist') ?>"
                class="flex items-center justify-between p-4 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors border border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="bg-slate-900 rounded-lg p-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <span class="font-semibold text-slate-900">Manage IP Whitelist</span>
                </div>
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>

            <a href="<?= base_url('/admin/monitoring') ?>"
                class="flex items-center justify-between p-4 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors border border-slate-200">
                <div class="flex items-center gap-3">
                    <div class="bg-slate-900 rounded-lg p-2">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <span class="font-semibold text-slate-900">System Monitoring</span>
                </div>
                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg">
        <div class="border-b-2 border-slate-200 p-6">
            <h2 class="text-xl font-bold text-slate-900">System Information</h2>
            <p class="mt-1 text-sm text-slate-600">Informasi sistem blockchain</p>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                <span class="text-sm font-medium text-slate-700">Genesis Block</span>
                <span class="text-sm font-mono font-semibold text-slate-900">
                    <?= $stats['genesis_block'] ? '#' . $stats['genesis_block']['id'] : 'N/A' ?>
                </span>
            </div>
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                <span class="text-sm font-medium text-slate-700">Latest Block Time</span>
                <span class="text-sm font-semibold text-slate-900">
                    <?= $stats['latest_block_time'] ? date('d/m/Y H:i', strtotime($stats['latest_block_time'])) : 'N/A' ?>
                </span>
            </div>
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                <span class="text-sm font-medium text-slate-700">Chain Integrity</span>
                <span class="text-sm font-semibold text-slate-900">
                    <?= ($stats['chain_valid'] ?? true) ? 'Valid' : 'Invalid' ?>
                </span>
            </div>
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200">
                <span class="text-sm font-medium text-slate-700">Manipulated Data</span>
                <span class="text-sm font-semibold text-slate-900">
                    <?= $stats['manipulated_count'] ?? 0 ?> documents
                </span>
            </div>
        </div>
    </div>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg">
    <div class="border-b-2 border-slate-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Latest Blocks</h2>
                <p class="mt-1 text-sm text-slate-600">10 blok terbaru dalam blockchain</p>
            </div>
            <a href="<?= base_url('/admin/explorer') ?>"
                class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold text-sm transition-colors">
                View All
            </a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Block ID</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Nama Dokumen</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Nomor Permohonan</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Tanggal Dokumen</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Timestamp</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Hash</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (!empty($latestBlocks)): ?>
                    <?php
                    // Bangun set block ID yang terdeteksi dimanipulasi
                    $manipulatedBlockIds = [];
                    if (!empty($manipulatedData)) {
                        foreach ($manipulatedData as $mData) {
                            if (!empty($mData['block_id'])) {
                                $manipulatedBlockIds[] = (string) $mData['block_id'];
                            }
                        }
                    }
                    ?>
                    <?php foreach ($latestBlocks as $block): ?>
                        <?php $isBlockManipulated = in_array((string) $block['id'], $manipulatedBlockIds, true); ?>
                        <tr class="transition-colors <?= $isBlockManipulated ? 'bg-red-50 hover:bg-red-100 border-l-4 border-l-red-600' : 'hover:bg-slate-50' ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($isBlockManipulated): ?>
                                    <div class="flex items-center gap-1">
                                        <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="font-mono text-sm font-bold text-red-700">#<?= $block['id'] ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="font-mono text-sm font-semibold text-slate-900">#<?= $block['id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium <?= $isBlockManipulated ? 'text-red-700' : 'text-slate-900' ?>"><?= esc($block['nama_dokumen']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium <?= $isBlockManipulated ? 'text-red-700' : 'text-slate-900' ?>"><?= esc($block['nomor_permohonan']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?= $isBlockManipulated ? 'text-red-600' : 'text-slate-600' ?>">
                                <?= date('d/m/Y', strtotime($block['tanggal_dokumen'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm <?= $isBlockManipulated ? 'text-red-600' : 'text-slate-600' ?>">
                                <?= date('d/m/Y H:i', strtotime($block['timestamp'])) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($isBlockManipulated): ?>
                                    <span class="font-mono text-xs text-red-500">⚠ <?= substr($block['block_hash'], 0, 16) ?>...</span>
                                <?php else: ?>
                                    <span class="font-mono text-xs text-slate-500"><?= substr($block['block_hash'], 0, 16) ?>...</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                            Belum ada blok dalam blockchain
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-8 bg-white border-2 border-slate-200 rounded-lg">
    <div class="border-b-2 border-slate-200 p-6">
        <h2 class="text-xl font-bold text-slate-900">Log Aktivitas</h2>
        <p class="mt-1 text-sm text-slate-600">Riwayat aktivitas manipulasi dan pemulihan data</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Waktu</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Aksi</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Kondisi</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white" id="activity-logs-tbody">
                <?php if (!empty($activityLogs)): ?>
                    <?php foreach ($activityLogs as $log): ?>
                        <?php
                        // Tentukan apakah baris ini terkait manipulasi (untuk warna baris)
                        $rowIsManipulation = in_array($log['action_type'], ['MANIPULATE', 'DELETE']) ||
                            $log['status'] === 'Manipulated' ||
                            $log['status'] === 'Failed';

                        // Cek consensus anomaly untuk warna baris
                        $rowConsensusAnomaly = false;
                        if (in_array($log['action_type'], ['CONSENSUS_CHECK', 'CONSENSUS_RECOVER'])) {
                            $d = $log['description'];
                            if (preg_match('/(\d+)\s+minority.*?(\d+)\s+no-consensus.*?(\d+)\s+missing/', $d, $rm)) {
                                $rowConsensusAnomaly = ((int)$rm[1] + (int)$rm[2] + (int)$rm[3]) > 0;
                            }
                        }
                        $rowHighlight = $rowIsManipulation || $rowConsensusAnomaly;
                        ?>
                        <tr class="transition-colors <?= $rowHighlight ? 'bg-red-50 hover:bg-red-100 border-l-4 border-l-red-600' : 'hover:bg-slate-50' ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-xs <?= $rowHighlight ? 'text-red-600 font-medium' : 'text-slate-600' ?>">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $actionLabels = [
                                    'CREATE' => 'BUAT',
                                    'UPDATE' => 'UBAH',
                                    'DELETE' => 'HAPUS',
                                    'MANIPULATE' => 'MANIPULASI',
                                    'RECOVER' => 'PEMULIHAN',
                                    'CHECK' => 'PENGECEKAN',
                                    'CONSENSUS_CHECK' => 'PENGECEKAN',
                                    'CONSENSUS_RECOVER' => 'PEMULIHAN',
                                    'CONSENSUS_SYNC' => 'SYNC'
                                ];
                                $colors = [
                                    'CREATE' => 'bg-slate-900 text-white',
                                    'UPDATE' => 'bg-amber-600 text-white',
                                    'DELETE' => 'bg-red-600 text-white',
                                    'MANIPULATE' => 'bg-red-600 text-white',
                                    'RECOVER' => 'bg-green-600 text-white',
                                    'CHECK' => 'bg-slate-600 text-white',
                                    'CONSENSUS_CHECK' => 'bg-slate-600 text-white',
                                    'CONSENSUS_RECOVER' => 'bg-green-600 text-white',
                                    'CONSENSUS_SYNC' => 'bg-amber-600 text-white'
                                ];
                                $color = $colors[$log['action_type']] ?? 'bg-slate-600 text-white';
                                ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded <?= $color ?>">
                                    <?= $actionLabels[$log['action_type']] ?? esc($log['action_type']) ?>
                                </span>
                            </td>
                            <?php
                            // Tentukan apakah log ini terkait manipulasi/anomali
                            $isManipulationLog = in_array($log['action_type'], ['MANIPULATE', 'DELETE']);
                            $isConsensusAnomaly = false;
                            $consensusTotalAnomalies = 0;

                            if ($log['action_type'] === 'CONSENSUS_CHECK' || $log['action_type'] === 'CONSENSUS_RECOVER') {
                                $desc = $log['description'];
                                if (preg_match('/(\d+)\s+healthy.*?(\d+)\s+minority.*?(\d+)\s+no-consensus.*?(\d+)\s+missing/', $desc, $cm)) {
                                    $consensusTotalAnomalies = (int)$cm[2] + (int)$cm[3] + (int)$cm[4];
                                    $isConsensusAnomaly = $consensusTotalAnomalies > 0;
                                }
                            }

                            $isAnyAnomaly = $isManipulationLog || $isConsensusAnomaly ||
                                ($log['status'] === 'Manipulated') ||
                                ($log['status'] === 'Failed');
                            ?>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($log['action_type'] === 'CONSENSUS_CHECK'): ?>
                                    <?php
                                    $description = $log['description'];
                                    if (preg_match('/(\d+)\s+healthy.*?(\d+)\s+minority.*?(\d+)\s+no-consensus.*?(\d+)\s+missing/', $description, $matches)):
                                        $healthy      = $matches[1];
                                        $minority     = $matches[2];
                                        $noConsensus  = $matches[3];
                                        $missing      = $matches[4];
                                        $totalAnomalies = $minority + $noConsensus + $missing;
                                    ?>
                                        <?php if ($totalAnomalies > 0): ?>
                                            <div class="space-y-1">
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-600 text-white text-xs font-bold rounded">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <?= $totalAnomalies ?> Anomali
                                                </span>
                                                <div class="text-xs text-red-700 mt-1 font-medium">
                                                    <?php if ($minority > 0): ?><div>• Minority: <?= $minority ?></div><?php endif; ?>
                                                    <?php if ($noConsensus > 0): ?><div>• No-Consensus: <?= $noConsensus ?></div><?php endif; ?>
                                                    <?php if ($missing > 0): ?><div>• Missing: <?= $missing ?></div><?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="space-y-1">
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">
                                                    ✓ Sehat
                                                </span>
                                                <div class="text-xs text-slate-500 mt-1">Healthy: <?= $healthy ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>

                                <?php elseif ($log['block_id']): ?>
                                    <?php if ($isAnyAnomaly): ?>
                                        <div class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="font-mono font-bold text-red-700">#<?= $log['block_id'] ?></span>
                                        </div>
                                        <?php if ($log['identifier']): ?>
                                            <div class="text-red-500 text-xs mt-1 font-medium">(<?= esc($log['identifier']) ?>)</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="font-mono text-slate-900">#<?= $log['block_id'] ?></span>
                                        <?php if ($log['identifier']): ?>
                                            <div class="text-slate-500 text-xs mt-1">(<?= esc($log['identifier']) ?>)</div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <span class="text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusLabels = [
                                    'Manipulated' => 'Dimanipulasi',
                                    'Recovered' => 'Dipulihkan',
                                    'SUCCESS' => 'Berhasil',
                                    'Failed' => 'Gagal',
                                    'WARNING' => 'Peringatan',
                                    'INFO' => 'Info'
                                ];
                                $statusText = $statusLabels[$log['status']] ?? $log['status'];
                                ?>
                                <?php
                                $statusClass = 'bg-slate-600 text-white';
                                if ($log['status'] === 'Manipulated' || $log['status'] === 'Failed') {
                                    $statusClass = 'bg-red-600 text-white';
                                } elseif ($log['status'] === 'Recovered' || $log['status'] === 'SUCCESS') {
                                    $statusClass = 'bg-green-600 text-white';
                                } elseif ($log['status'] === 'WARNING') {
                                    $statusClass = 'bg-amber-600 text-white';
                                }
                                ?>
                                <span class="px-2 py-1 text-xs font-medium rounded <?= $statusClass ?>">
                                    <?= esc($statusText) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php
                                $originalData = !empty($log['original_data']) ? json_decode($log['original_data'], true) : null;
                                $modifiedData = !empty($log['modified_data']) ? json_decode($log['modified_data'], true) : null;
                                $dbLabelMap   = [
                                    'userdb'    => 'User DB',
                                    'admindb'   => 'Admin DB',
                                    'konsensus' => 'Konsensus DB',
                                ];
                                ?>

                                <?php if ($log['action_type'] === 'MANIPULATE'): ?>
                                    <?php
                                    $fieldChanges   = $originalData['field_changes'] ?? [];
                                    $corruptDbs     = $originalData['corrupt_dbs'] ?? [];
                                    $majorityDbLabel = $originalData['majority_db_label'] ?? ($dbLabelMap[$originalData['majority_db'] ?? ''] ?? 'N/A');
                                    $manipStatus    = $originalData['status'] ?? '';
                                    $totalDiperiksa = $originalData['total_diperiksa'] ?? '-';
                                    $totalCorrupt   = $originalData['total_corrupt'] ?? '-';
                                    $rekomendasi    = $modifiedData['recommendation'] ?? '';
                                    $storedDbLabels = $modifiedData['db_labels'] ?? $dbLabelMap;
                                    ?>
                                    <div class="space-y-2">

                                        <!-- Header manipulasi -->
                                        <p class="font-bold text-red-700 flex items-center gap-1">
                                            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            Ketidaksesuaian Data Ditemukan
                                        </p>

                                        <!-- Info blok & tipe -->
                                        <div class="bg-red-50 border border-red-300 rounded-lg p-3 space-y-1.5 text-xs">
                                            <?php if ($log['block_id']): ?>
                                            <div class="flex gap-2">
                                                <span class="font-semibold text-red-600 w-32 flex-shrink-0">ID Blok:</span>
                                                <span class="font-mono font-bold text-red-800">#<?= esc($log['block_id']) ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($log['identifier']): ?>
                                            <div class="flex gap-2">
                                                <span class="font-semibold text-red-600 w-32 flex-shrink-0">No. Permohonan:</span>
                                                <span class="font-mono text-red-800"><?= esc($log['identifier']) ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="flex gap-2">
                                                <span class="font-semibold text-red-600 w-32 flex-shrink-0">Tipe Anomali:</span>
                                                <?php if ($manipStatus === 'minority'): ?>
                                                    <span class="px-1.5 py-0.5 bg-amber-500 text-white font-bold rounded">Minority Corrupt</span>
                                                    <span class="text-red-600">(1 dari 3 DB berbeda)</span>
                                                <?php elseif ($manipStatus === 'no_consensus'): ?>
                                                    <span class="px-1.5 py-0.5 bg-red-700 text-white font-bold rounded">No Consensus</span>
                                                    <span class="text-red-600">(ketiga DB berbeda)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex gap-2">
                                                <span class="font-semibold text-red-600 w-32 flex-shrink-0">Data Referensi:</span>
                                                <span class="px-1.5 py-0.5 bg-slate-700 text-white rounded"><?= esc($majorityDbLabel) ?></span>
                                                <span class="text-slate-500">(data asli/sehat)</span>
                                            </div>
                                        </div>

                                        <!-- Perbandingan per field per DB corrupt -->
                                        <?php if (!empty($fieldChanges)): ?>
                                            <?php foreach ($fieldChanges as $dbKey => $changes): ?>
                                                <?php $dbLabel = $storedDbLabels[$dbKey] ?? $dbLabelMap[$dbKey] ?? strtoupper($dbKey); ?>
                                                <div class="border border-red-300 rounded-lg overflow-hidden">
                                                    <!-- Header database -->
                                                    <div class="bg-red-600 text-white px-3 py-1.5 flex items-center gap-2">
                                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"/>
                                                            <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"/>
                                                            <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"/>
                                                        </svg>
                                                        <span class="text-xs font-bold">Database Dimanipulasi: <?= esc($dbLabel) ?></span>
                                                    </div>
                                                    <!-- Tabel perbandingan -->
                                                    <table class="w-full text-xs">
                                                        <thead>
                                                            <tr class="bg-red-100">
                                                                <th class="px-3 py-1.5 text-left text-red-700 font-semibold w-1/3">Field</th>
                                                                <th class="px-3 py-1.5 text-left text-slate-700 font-semibold w-1/3">Nilai Asli (Sebelum)</th>
                                                                <th class="px-3 py-1.5 text-left text-red-700 font-semibold w-1/3">Nilai Dimanipulasi (Sesudah)</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-red-100">
                                                            <?php foreach ($changes as $change): ?>
                                                                <tr class="bg-white">
                                                                    <td class="px-3 py-1.5 font-semibold text-slate-700"><?= esc($change['label']) ?></td>
                                                                    <td class="px-3 py-1.5">
                                                                        <?php $nilaiAsli = $change['nilai_asli']; ?>
                                                                        <?php if ($change['field'] === 'block_hash'): ?>
                                                                            <span class="font-mono text-slate-600 bg-slate-100 px-1 rounded"><?= esc(substr($nilaiAsli ?? '', 0, 16)) ?>...</span>
                                                                        <?php else: ?>
                                                                            <span class="text-slate-700 font-medium"><?= esc($nilaiAsli ?? '-') ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="px-3 py-1.5">
                                                                        <?php $nilaiManipulasi = $change['nilai_manipulasi']; ?>
                                                                        <?php if ($change['field'] === 'block_hash'): ?>
                                                                            <span class="font-mono text-red-700 bg-red-100 px-1 rounded font-bold"><?= esc(substr($nilaiManipulasi ?? '', 0, 16)) ?>...</span>
                                                                        <?php else: ?>
                                                                            <span class="text-red-700 font-bold"><?= esc($nilaiManipulasi ?? '-') ?></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-xs text-red-600 bg-red-50 border border-red-200 rounded p-2">
                                                <?= esc($log['description']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Statistik & Rekomendasi -->
                                        <div class="bg-slate-50 border border-slate-200 rounded-lg p-2.5 text-xs space-y-1">
                                            <div class="flex gap-2">
                                                <span class="font-semibold text-slate-600 w-32 flex-shrink-0">Total Diperiksa:</span>
                                                <span class="text-slate-800 font-bold"><?= esc($totalDiperiksa) ?> blok</span>
                                            </div>
                                            <div class="flex gap-2">
                                                <span class="font-semibold text-slate-600 w-32 flex-shrink-0">Total Corrupt:</span>
                                                <span class="font-bold <?= (int)$totalCorrupt > 3 ? 'text-red-700' : 'text-amber-600' ?>"><?= esc($totalCorrupt) ?> blok</span>
                                            </div>
                                            <?php if ($rekomendasi): ?>
                                            <div class="flex gap-2 pt-1 border-t border-slate-200">
                                                <span class="font-semibold text-slate-600 w-32 flex-shrink-0">Rekomendasi:</span>
                                                <span class="text-slate-700 italic"><?= esc($rekomendasi) ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ((int)$totalCorrupt > 3): ?>
                                            <div class="mt-1 p-2 bg-red-100 border border-red-300 rounded text-red-800 font-semibold">
                                                &#9888; Perhatian: Ditemukan banyak blok corrupt (<?= esc($totalCorrupt) ?> blok). Segera lakukan recovery data atau audit sistem.
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                <?php elseif ($log['action_type'] === 'CONSENSUS_CHECK' || $log['action_type'] === 'CONSENSUS_RECOVER'): ?>
                                    <?php if ($isConsensusAnomaly): ?>
                                        <div class="space-y-2">
                                            <p class="font-bold text-red-700 flex items-center gap-1">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                                Ditemukan Ketidaksesuaian Data
                                            </p>
                                            <div class="bg-red-50 border border-red-300 rounded-lg p-3 space-y-1.5 text-xs overflow-hidden">
                                                <?php
                                                $desc = $log['description'];
                                                preg_match('/(\d+)\s+healthy.*?(\d+)\s+minority.*?(\d+)\s+no-consensus.*?(\d+)\s+missing/', $desc, $cm);
                                                ?>
                                                <div class="text-red-700 font-semibold mb-2">
                                                    Consensus check completed: <?= $cm[1] ?? 0 ?> healthy, <?= $cm[2] ?? 0 ?> minority, <?= $cm[3] ?? 0 ?> no-consensus, <?= $cm[4] ?? 0 ?> missing
                                                </div>

                                                <?php
                                                $originalData = !empty($log['original_data']) ? json_decode($log['original_data'], true) : [];
                                                $details = $originalData['details'] ?? [];
                                                
                                                if (!empty($details)):
                                                    $count = 0;
                                                    foreach ($details as $detail):
                                                        if ($count >= 5) break;
                                                        $count++;
                                                        
                                                        $data = $detail['data'] ?? [];
                                                        $majorityHash = $detail['majority_hash'] ?? null;
                                                        $checksums = $detail['checksums'] ?? [];
                                                        
                                                        $majorityData = null;
                                                        if ($majorityHash) {
                                                            foreach ($checksums as $db => $hash) {
                                                                if ($hash === $majorityHash && !empty($data[$db])) {
                                                                    $majorityData = $data[$db];
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                        if (!$majorityData && !empty($data['userdb'])) {
                                                            $majorityData = $data['userdb'];
                                                        }

                                                        $fieldsToCompare = ['nama_dokumen', 'nomor_permohonan', 'nomor_dokumen', 'tanggal_dokumen', 'tanggal_filing', 'block_hash', 'previous_hash'];
                                                ?>
                                                    <div class="mt-2 p-2 bg-white rounded border border-red-200 overflow-x-auto">
                                                        <div class="font-bold text-red-800 mb-1">Identitas Blok: <?= esc($detail['identifier'] ?? 'Unknown') ?> <span class="text-slate-500 font-normal text-[10px]">(<?= esc($detail['status'] ?? '') ?>)</span></div>
                                                        <table class="min-w-full text-[10px]">
                                                            <thead class="bg-red-50 text-red-700">
                                                                <tr>
                                                                    <th class="text-left py-1 px-1">Field</th>
                                                                    <th class="text-left py-1 px-1">UserDB</th>
                                                                    <th class="text-left py-1 px-1">AdminDB</th>
                                                                    <th class="text-left py-1 px-1">KonsensusDB</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($fieldsToCompare as $field): ?>
                                                                    <?php
                                                                    $majorityValue = $majorityData[$field] ?? 'N/A';
                                                                    $userValue = $data['userdb'][$field] ?? 'NULL';
                                                                    $adminValue = $data['admindb'][$field] ?? 'NULL';
                                                                    $konsensusValue = $data['konsensus'][$field] ?? 'NULL';

                                                                    $isChangedUser = $userValue !== $majorityValue;
                                                                    $isChangedAdmin = $adminValue !== $majorityValue;
                                                                    $isChangedKonsensus = $konsensusValue !== $majorityValue;

                                                                    // Only show manipulated fields or block_hash
                                                                    if (!$isChangedUser && !$isChangedAdmin && !$isChangedKonsensus && strpos($field, 'hash') === false) {
                                                                        continue;
                                                                    }

                                                                    $displayUser = (strpos($field, 'hash') !== false && $userValue !== 'NULL') ? substr($userValue, 0, 8) . '...' : $userValue;
                                                                    $displayAdmin = (strpos($field, 'hash') !== false && $adminValue !== 'NULL') ? substr($adminValue, 0, 8) . '...' : $adminValue;
                                                                    $displayKonsensus = (strpos($field, 'hash') !== false && $konsensusValue !== 'NULL') ? substr($konsensusValue, 0, 8) . '...' : $konsensusValue;
                                                                    ?>
                                                                    <tr class="border-t border-red-100">
                                                                        <td class="py-1 px-1 font-semibold text-slate-700"><?= str_replace('_', ' ', $field) ?></td>
                                                                        <td class="py-1 px-1 <?= $isChangedUser ? 'text-red-600 bg-red-100 font-bold' : 'text-slate-600' ?>"><?= esc($displayUser) ?></td>
                                                                        <td class="py-1 px-1 <?= $isChangedAdmin ? 'text-red-600 bg-red-100 font-bold' : 'text-slate-600' ?>"><?= esc($displayAdmin) ?></td>
                                                                        <td class="py-1 px-1 <?= $isChangedKonsensus ? 'text-red-600 bg-red-100 font-bold' : 'text-slate-600' ?>"><?= esc($displayKonsensus) ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endforeach; ?>

                                                <?php if (count($details) > 5): ?>
                                                    <div class="mt-3 text-center">
                                                        <a href="<?= base_url('/admin/consensus/check') ?>" class="inline-block px-3 py-1.5 bg-red-600 text-white rounded text-xs font-bold hover:bg-red-700 transition-colors">
                                                            Pengecekan Konsensus (Ada <?= count($details) - 5 ?> data anomali lain)
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-1">
                                            <p class="font-semibold text-slate-700 flex items-center gap-1">
                                                <svg class="w-4 h-4 text-slate-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                                Tidak Ada Anomali
                                            </p>
                                            <div class="text-xs text-slate-600 bg-emerald-50 border border-emerald-200 rounded p-2">
                                                <?php
                                                $desc = $log['description'];
                                                if (preg_match('/(\d+)\s+healthy.*?(\d+)\s+minority.*?(\d+)\s+no-consensus.*?(\d+)\s+missing/', $desc, $cm)) {
                                                    echo "<span class='font-semibold text-emerald-700'>Consensus check completed: {$cm[1]} healthy, {$cm[2]} minority, {$cm[3]} no-consensus, {$cm[4]} missing</span>";
                                                } else {
                                                    echo esc($desc);
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                <?php elseif ($isAnyAnomaly): ?>
                                    <span class="text-red-700 font-medium"><?= esc($log['description']) ?></span>

                                <?php else: ?>
                                    <span class="text-slate-600"><?= esc($log['description']) ?></span>

                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                            Belum ada log aktivitas
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    async function refreshActivityLogs() {
        try {
            const response = await fetch('<?= base_url('/api/activity-logs?limit=10') ?>');
            const result = await response.json();

            if (result.status === 'success' && result.data.logs) {
                const tbody = document.getElementById('activity-logs-tbody');
                tbody.innerHTML = result.data.logs.map(log => {
                    const actionLabels = {
                        'CREATE': 'BUAT',
                        'UPDATE': 'UBAH',
                        'DELETE': 'HAPUS',
                        'MANIPULATE': 'MANIPULASI',
                        'RECOVER': 'PEMULIHAN',
                        'CHECK': 'PENGECEKAN',
                        'CONSENSUS_CHECK': 'PENGECEKAN',
                        'CONSENSUS_RECOVER': 'PEMULIHAN',
                        'CONSENSUS_SYNC': 'SYNC'
                    };
                    const statusLabels = {
                        'Manipulated': 'Dimanipulasi',
                        'Recovered': 'Dipulihkan',
                        'Success': 'Berhasil',
                        'Failed': 'Gagal',
                        'WARNING': 'Peringatan',
                        'INFO': 'Info'
                    };
                    const colors = {
                        'CREATE': 'bg-slate-900 text-white',
                        'UPDATE': 'bg-amber-600 text-white',
                        'DELETE': 'bg-red-600 text-white',
                        'MANIPULATE': 'bg-red-600 text-white',
                        'RECOVER': 'bg-green-600 text-white',
                        'CHECK': 'bg-slate-600 text-white',
                        'CONSENSUS_CHECK': 'bg-slate-600 text-white',
                        'CONSENSUS_RECOVER': 'bg-green-600 text-white',
                        'CONSENSUS_SYNC': 'bg-amber-600 text-white'
                    };
                    const color = colors[log.action_type] || 'bg-slate-600 text-white';
                    const actionLabel = actionLabels[log.action_type] || log.action_type;
                    const statusLabel = statusLabels[log.status] || log.status;

                    return `
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-600">
                            ${new Date(log.created_at).toLocaleString('id-ID')}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-semibold rounded ${color}">
                                ${actionLabel}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            ${log.action_type === 'CONSENSUS_CHECK' ? `
                                ${log.description.includes('minority') || log.description.includes('no-consensus') || log.description.includes('missing') ? `
                                    <div class="space-y-1">
                                        <span class="inline-block px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded">Manipulasi</span>
                                        <div class="text-xs text-slate-600 mt-1">${log.description}</div>
                                    </div>
                                ` : `
                                    <div class="space-y-1">
                                        <span class="inline-block px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">✓ Sehat</span>
                                        <div class="text-xs text-slate-600 mt-1">${log.description}</div>
                                    </div>
                                `}
                            ` : `
                                ${log.block_id ? `<span class="font-mono text-slate-900">#${log.block_id}</span>` : ''}
                                ${log.identifier ? `<span class="text-slate-600 text-xs">(${log.identifier})</span>` : ''}
                            `}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 text-xs font-medium rounded ${log.status === 'Manipulated' ? 'bg-red-600 text-white' : (log.status === 'Recovered' ? 'bg-green-600 text-white' : 'bg-slate-600 text-white')}">
                                ${statusLabel}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            ${log.action_type === 'MANIPULATE' && log.description.startsWith('Field yang dimanipulasi:') ? `
                                <div class="space-y-1">
                                    <p class="font-semibold text-red-700">⚠️ Anomali Terdeteksi - Manipulasi:</p>
                                    <div class="text-xs text-slate-700 bg-red-50 border border-red-200 rounded p-2 mt-1">
                                        ${log.description.replace('Field yang dimanipulasi: ', '').split(', ').map(field =>
                                            `<div class="py-0.5"><span class="font-mono text-red-800">→</span> ${field}</div>`
                                        ).join('')}
                                    </div>
                                </div>
                            ` : log.action_type === 'CONSENSUS_CHECK' ? `
                                ${log.description.includes('minority') || log.description.includes('no-consensus') || log.description.includes('missing') ? `
                                    <div class="space-y-1">
                                        <p class="font-semibold text-amber-700">Manipulasi Terdeteksi</p>
                                        <div class="text-xs text-slate-700 bg-amber-50 border border-amber-200 rounded p-2 mt-1">
                                            <div class="py-0.5">${log.description}</div>
                                        </div>
                                    </div>
                                ` : `
                                    <div class="space-y-1">
                                        <p class="font-semibold text-green-700">✓ Tidak Ada Anomali</p>
                                        <div class="text-xs text-slate-700 bg-green-50 border border-green-200 rounded p-2 mt-1">
                                            <div class="py-0.5">${log.description}</div>
                                        </div>
                                    </div>
                                `}
                            ` : `
                                <span class="text-slate-600">${log.description}</span>
                            `}
                        </td>
                    </tr>
                `;
                }).join('');
            }
        } catch (error) {
            console.error('Error refreshing logs:', error);
        }
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 px-6 py-4 rounded-lg shadow-lg border-2 z-50 ${
        type === 'success' ? 'bg-green-600 border-green-700 text-white' :
        type === 'warning' ? 'bg-amber-600 border-amber-700 text-white' :
        type === 'error' ? 'bg-red-600 border-red-700 text-white' :
        'bg-slate-900 border-slate-900 text-white'
    }`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    const flashMessage = document.getElementById('flash-message');
    if (flashMessage) {
        setTimeout(() => {
            flashMessage.style.opacity = '0';
            flashMessage.style.transform = 'translateY(-10px)';
            flashMessage.style.margin = '0';
            flashMessage.style.paddingTop = '0';
            flashMessage.style.paddingBottom = '0';
            flashMessage.style.maxHeight = '0';

            setTimeout(() => {
                flashMessage.remove();
            }, 500);
        }, 5000);
    }
</script>

<?= $this->endSection() ?>
