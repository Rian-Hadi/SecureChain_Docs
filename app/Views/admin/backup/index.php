<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="mb-6 rounded-lg bg-slate-100 border-l-4 border-slate-900 p-4">
        <p class="text-slate-900 font-medium"><?= session()->getFlashdata('success') ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1"><?= number_format(count($backups)) ?></p>
        <p class="text-sm text-slate-600">Total Cadangan</p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1">
            <?= number_format(count(array_filter($backups, function ($b) {
                return $b['backup_type'] === 'auto';
            }))) ?>
        </p>
        <p class="text-sm text-slate-600">Cadangan Otomatis</p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1">
            <?= number_format(count(array_filter($backups, function ($b) {
                return $b['backup_type'] === 'manual';
            }))) ?>
        </p>
        <p class="text-sm text-slate-600">Cadangan Manual</p>
    </div>
</div>

<div class="mb-6 flex items-center justify-between">
    <div class="bg-white rounded-lg border-2 border-slate-200 px-4 py-3">
        <p class="text-sm text-slate-600">Cadangan Terakhir:</p>
        <p class="text-lg font-bold text-slate-900">
            <?php if (!empty($backups)): ?>
                <?= date('d M Y, H:i', strtotime($backups[0]['backup_timestamp'])) ?>
            <?php else: ?>
                N/A
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= base_url('/admin/backup/create') ?>"
        class="flex items-center gap-2 px-6 py-3 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Buat Cadangan
    </a>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg">
    <div class="border-b-2 border-slate-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Daftar Cadangan</h2>
                <p class="mt-1 text-sm text-slate-600">Semua cadangan data rantai blok yang tersimpan</p>
            </div>
            <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold border border-slate-200">
                <?= count($backups) ?> Cadangan
            </span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">ID</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Detail Dokumen</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Hash Blok</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Hash Sebelumnya</th>
                    <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-700">Jenis</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Waktu Cadangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (!empty($backups)): ?>
                    <?php foreach ($backups as $backup): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-slate-900 text-white font-bold text-sm">
                                    <?= esc($backup['id']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <p class="text-sm font-bold text-slate-900 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <?= esc($backup['nomor_permohonan']) ?>
                                    </p>
                                    <p class="text-sm text-slate-600">
                                        Dok: <span class="font-semibold"><?= esc($backup['nomor_dokumen']) ?></span>
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        <?= date('d/m/Y', strtotime($backup['tanggal_dokumen'])) ?> |
                                        Filing: <?= date('d/m/Y', strtotime($backup['tanggal_filing'])) ?>
                                    </p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs text-slate-600 bg-slate-100 px-2 py-1 rounded border border-slate-200">
                                        <?= substr(esc($backup['block_hash']), 0, 16) ?>...
                                    </span>
                                    <button onclick="copyToClipboard('<?= esc($backup['block_hash']) ?>')"
                                        class="text-slate-600 hover:text-slate-900" title="Copy full hash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono text-xs text-slate-500 bg-slate-50 px-2 py-1 rounded border border-slate-200">
                                    <?= substr(esc($backup['previous_hash']), 0, 16) ?>...
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($backup['backup_type'] === 'auto'): ?>
                                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-lg bg-slate-900 text-white">
                                        Auto
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-lg bg-slate-200 text-slate-900 border border-slate-900">
                                        Manual
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">
                                            <?= date('d/m/Y', strtotime($backup['backup_timestamp'])) ?>
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            <?= date('H:i:s', strtotime($backup['backup_timestamp'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            <p class="text-lg font-semibold">Belum ada backup yang tersedia</p>
                            <p class="text-sm mt-1">Buat backup pertama untuk melindungi data blockchain Anda</p>
                            <a href="<?= base_url('/admin/backup/create') ?>"
                                class="inline-block mt-4 px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold">
                                Create Backup
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Hash berhasil disalin ke clipboard!');
        }, function(err) {
            console.error('Gagal menyalin: ', err);
        });
    }
</script>

<?= $this->endSection() ?>