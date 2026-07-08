<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="mb-6 bg-<?= $chainIntegrity['is_valid'] ? 'white' : 'red-600' ?> border-2 border-<?= $chainIntegrity['is_valid'] ? 'slate-200' : 'red-700' ?> rounded-lg p-6">
    <div class="flex items-center gap-4">
        <div class="bg-<?= $chainIntegrity['is_valid'] ? 'slate-900' : 'white' ?> rounded-lg p-4">
            <svg class="w-10 h-10 text-<?= $chainIntegrity['is_valid'] ? 'white' : 'red-600' ?>" fill="currentColor" viewBox="0 0 20 20">
                <?php if ($chainIntegrity['is_valid']): ?>
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                <?php else: ?>
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                <?php endif; ?>
            </svg>
        </div>
        <div class="flex-grow">
            <h2 class="text-2xl font-bold text-<?= $chainIntegrity['is_valid'] ? 'slate-900' : 'white' ?>">
                <?= $chainIntegrity['is_valid'] ? 'Rantai Blok Valid' : 'Rantai Blok Tidak Valid' ?>
            </h2>
            <?php if (!$chainIntegrity['is_valid']): ?>
                <p class="text-red-100 mt-1">
                    Ditemukan <strong><?= $chainIntegrity['invalid_count'] ?> blok tidak valid</strong> dari total <?= $chainIntegrity['total_blocks'] ?> blok.
                </p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <p class="text-3xl font-bold text-<?= $chainIntegrity['is_valid'] ? 'slate-900' : 'white' ?>">
                <?= $chainIntegrity['total_blocks'] ?>
            </p>
            <p class="text-sm text-<?= $chainIntegrity['is_valid'] ? 'slate-600' : 'red-200' ?>">Total Blok</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center gap-3 mb-3">
            <div class="bg-slate-900 rounded-lg p-2">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <span class="text-sm font-semibold text-slate-700">Total Blok</span>
        </div>
        <p class="text-3xl font-bold text-slate-900"><?= number_format($stats['total_blocks']) ?></p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center gap-3 mb-3">
            <div class="bg-slate-900 rounded-lg p-2">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-sm font-semibold text-slate-700">Blok Valid</span>
        </div>
        <p class="text-3xl font-bold text-slate-900"><?= number_format($stats['total_blocks'] - $chainIntegrity['invalid_count']) ?></p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center gap-3 mb-3">
            <div class="bg-red-600 rounded-lg p-2">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-sm font-semibold text-slate-700">Blok Tidak Valid</span>
        </div>
        <p class="text-3xl font-bold <?= $chainIntegrity['invalid_count'] > 0 ? 'text-red-600' : 'text-slate-900' ?>">
            <?= number_format($chainIntegrity['invalid_count']) ?>
        </p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center gap-3 mb-3">
            <div class="bg-slate-900 rounded-lg p-2">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <span class="text-sm font-semibold text-slate-700">Blok Terbaru</span>
        </div>
        <p class="text-lg font-bold text-slate-900">
            <?= $stats['latest_block_time'] ? date('H:i', strtotime($stats['latest_block_time'])) : 'N/A' ?>
        </p>
        <p class="text-xs text-slate-500 mt-1">
            <?= $stats['latest_block_time'] ? date('d M Y', strtotime($stats['latest_block_time'])) : '' ?>
        </p>
    </div>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg">
    <div class="border-b-2 border-slate-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Semua Blok Blockchain</h2>
                <p class="mt-1 text-sm text-slate-600">Daftar lengkap semua blok dalam rantai blok</p>
            </div>
            <div class="flex items-center gap-3">
                <?php if ($chainIntegrity['invalid_count'] > 0): ?>
                    <span class="flex items-center gap-1 px-3 py-1 bg-red-100 text-red-700 rounded-lg text-sm font-semibold border border-red-300">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <?= $chainIntegrity['invalid_count'] ?> Blok Terindikasi Manipulasi
                    </span>
                <?php endif; ?>
                <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold border border-slate-200">
                    <?= count($blocks) ?> Blok
                </span>
            </div>
        </div>
    </div>

    <?php if ($chainIntegrity['invalid_count'] > 0): ?>
        <div class="mx-6 mt-4 p-3 bg-red-50 border border-red-300 rounded-lg flex items-start gap-2">
            <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <p class="text-sm text-red-700">
                <strong>Peringatan:</strong> Baris berwarna merah menunjukkan blok dengan integritas data tidak valid. Lihat kolom <strong>Status Integritas</strong> untuk detail komponen mana yang bermasalah (Admin, User, atau Konsensus).
            </p>
        </div>
    <?php endif; ?>

    <div class="overflow-x-auto mt-4">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">ID Blok</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Nama Dokumen</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Nomor Permohonan</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Nomor Dokumen</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Kategori</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Tanggal Dokumen</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Timestamp</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Hash Blok</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Hash Sebelumnya</th>
                    <!-- FIX: Perlebar kolom Status Integritas agar badge muat dalam satu baris -->
                    <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-700 min-w-[320px]">Status Integritas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (!empty($blocks)): ?>
                    <?php
                    $invalidBlockIds = array_column($chainIntegrity['invalid_blocks'], 'block_number');

                    foreach ($blocks as $block):
                        $isValid = !in_array($block['id'], $invalidBlockIds);
                        
                        $invalidDetail = null;
                        $errorSources = [];
                        $errorsBySource = [];
                        
                        if (!$isValid) {
                            foreach ($chainIntegrity['invalid_blocks'] as $inv) {
                                if ($inv['block_number'] == $block['id']) {
                                    $invalidDetail = $inv;
                                    
                                    if (!empty($inv['error_sources']) && is_array($inv['error_sources'])) {
                                        $errorSources = $inv['error_sources'];
                                    }
                                    
                                    if (!empty($inv['errors']) && is_array($inv['errors'])) {
                                        foreach ($inv['errors'] as $error) {
                                            $source = 'unknown';
                                            if (stripos($error, 'primary') !== false || stripos($error, 'blockchain') !== false) {
                                                $source = 'primary';
                                            } elseif (stripos($error, 'backup') !== false) {
                                                $source = 'backup';
                                            } elseif (stripos($error, 'consensus') !== false || stripos($error, 'konsensus') !== false) {
                                                $source = 'consensus';
                                            }
                                            
                                            if (!isset($errorsBySource[$source])) {
                                                $errorsBySource[$source] = [];
                                            }
                                            $errorsBySource[$source][] = $error;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        
                        $primaryValid   = empty($errorSources) || !in_array('primary', $errorSources);
                        $backupValid    = empty($errorSources) || !in_array('backup', $errorSources);
                        $consensusValid = empty($errorSources) || !in_array('consensus', $errorSources);
                    ?>
                        <tr class="transition-colors <?= !$isValid
                            ? 'bg-red-50 hover:bg-red-100 border-l-4 border-l-red-600'
                            : 'hover:bg-slate-50' ?>">

                            <!-- ID Blok -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!$isValid): ?>
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                        <span class="font-mono text-sm font-bold text-red-700">#<?= $block['id'] ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="font-mono text-sm font-bold text-slate-900">#<?= $block['id'] ?></span>
                                <?php endif; ?>
                            </td>
                            <!-- Nama Dokumen -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold <?= !$isValid ? 'text-red-700' : 'text-slate-900' ?>">
                                    <?= esc($block['nama_dokumen']) ?>
                                </span>
                            </td> 

                            <!-- Nomor Permohonan -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold <?= !$isValid ? 'text-red-700' : 'text-slate-900' ?>">
                                    <?= esc($block['nomor_permohonan']) ?>
                                </span>
                            </td>

                            <!-- Nomor Dokumen -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm <?= !$isValid ? 'text-red-600' : 'text-slate-700' ?>">
                                    <?= esc($block['nomor_dokumen']) ?>
                                </span>
                            </td>

                            <!-- Kategori Dokumen -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= !$isValid ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700' ?>">
                                    <?= esc($block['kategori_dokumen'] ?? 'N/A') ?>
                                </span>
                            </td>

                            <!-- Tanggal Dokumen -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm <?= !$isValid ? 'text-red-600' : 'text-slate-600' ?>">
                                    <?= date('d/m/Y', strtotime($block['tanggal_dokumen'])) ?>
                                </span>
                            </td>

                            <!-- Timestamp -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm <?= !$isValid ? 'text-red-600' : 'text-slate-600' ?>">
                                    <?= date('d/m/Y H:i:s', strtotime($block['timestamp'])) ?>
                                </span>
                            </td>

                            <!-- Hash Blok -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs px-2 py-1 rounded <?= !$isValid
                                        ? 'text-red-700 bg-red-100 border border-red-300'
                                        : 'text-slate-500 bg-slate-100' ?>">
                                        <?= substr($block['block_hash'], 0, 20) ?>...
                                    </span>
                                    <button onclick="copyToClipboard('<?= $block['block_hash'] ?>')"
                                        class="<?= !$isValid ? 'text-red-500 hover:text-red-700' : 'text-slate-600 hover:text-slate-900' ?>"
                                        title="Copy full hash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>

                            <!-- Hash Sebelumnya -->
                            <td class="px-6 py-4">
                                <span class="font-mono text-xs px-2 py-1 rounded <?= !$isValid
                                    ? 'text-red-700 bg-red-100 border border-red-300'
                                    : 'text-slate-500 bg-slate-100' ?>">
                                    <?= substr($block['previous_hash'], 0, 20) ?>...
                                </span>
                            </td>

                            <!-- =====================================================
                                 FIX: Status Integritas - layout diubah menjadi grid
                                 agar 3 badge selalu sejajar dalam satu baris,
                                 dan pesan error ditampilkan di bawah masing-masing badge
                                 ===================================================== -->
                            <td class="px-4 py-4">
                                <?php if ($isValid): ?>
                                    <!-- Blok valid: tampilkan 3 badge hijau sejajar -->
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-300 whitespace-nowrap">
                                            ✓ User Valid
                                        </span>
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-300 whitespace-nowrap">
                                            ✓ Admin Valid
                                        </span>
                                        <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-300 whitespace-nowrap">
                                            ✓ Konsensus Valid
                                        </span>
                                    </div>

                                <?php elseif (!$isValid && empty($errorSources)): ?>
                                    <!-- Fallback: tidak ada error_sources, tampilkan badge "Dimanipulasi" -->
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-lg bg-orange-600 text-white border border-orange-700">
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                            Dimanipulasi
                                        </span>
                                        <?php if ($invalidDetail && !empty($invalidDetail['errors'])): ?>
                                            <div class="mt-1 w-full">
                                                <?php foreach ($invalidDetail['errors'] as $err): ?>
                                                    <p class="text-xs text-red-600 mt-0.5 leading-tight break-words"><?= esc($err) ?></p>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                <?php else: ?>
                                    <!-- FIX: Badge 3 komponen dalam satu baris (grid), error di bawah masing-masing -->
                                    <div class="flex flex-col gap-2">
                                        <!-- Baris badge: selalu dalam 1 baris, tidak wrap -->
                                        <div class="flex items-center justify-center gap-2 flex-nowrap">

                                            <!-- User (Primary) -->
                                            <?php if ($primaryValid): ?>
                                                <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-300 whitespace-nowrap">
                                                    ✓ User Valid
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg bg-red-100 text-red-700 border border-red-300 whitespace-nowrap">
                                                    <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    User Error
                                                </span>
                                            <?php endif; ?>

                                            <!-- Admin (Backup) -->
                                            <?php if ($backupValid): ?>
                                                <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-300 whitespace-nowrap">
                                                    ✓ Admin Valid
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg bg-red-100 text-red-700 border border-red-300 whitespace-nowrap">
                                                    <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    Admin Error
                                                </span>
                                            <?php endif; ?>

                                            <!-- Konsensus -->
                                            <?php if ($consensusValid): ?>
                                                <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-100 text-emerald-700 border border-emerald-300 whitespace-nowrap">
                                                    ✓ Konsensus Valid
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-bold rounded-lg bg-red-100 text-red-700 border border-red-300 whitespace-nowrap">
                                                    <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                    </svg>
                                                    Konsensus Error
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- FIX: Pesan error dikumpulkan dalam satu area di bawah badge, 
                                             dengan break-words agar tidak overflow -->
                                        <?php
                                        $allErrors = [];
                                        foreach ($errorsBySource as $src => $errs) {
                                            foreach ($errs as $e) {
                                                $allErrors[] = $e;
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($allErrors)): ?>
                                            <div class="bg-red-50 border border-red-200 rounded-md px-3 py-2 mt-1">
                                                <?php foreach ($allErrors as $errMsg): ?>
                                                    <p class="text-xs text-red-600 leading-snug break-words">
                                                        • <?= esc($errMsg) ?>
                                                    </p>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <!-- ===================================================== -->

                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-slate-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="text-lg font-semibold">Belum ada blok dalam blockchain</p>
                            <p class="text-sm mt-1">Blok akan muncul setelah dokumen pertama di-upload</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($blocks) && $chainIntegrity['invalid_count'] > 0): ?>
        <div class="border-t-2 border-slate-200 p-4 bg-red-50">
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2">
                    <span class="inline-block w-4 h-4 bg-red-600 rounded"></span>
                    <span class="text-sm text-red-700 font-medium">= Blok dengan salah satu komponen error/manipulasi</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Hash copied to clipboard!');
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }
</script>

<?= $this->endSection() ?>