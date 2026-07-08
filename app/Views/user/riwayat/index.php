<?= $this->extend('layouts/user') ?>

<?= $this->section('content') ?>

<!-- Statistik Ringkas -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5 flex items-center gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gray-900 text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Upload</p>
            <p class="text-2xl font-bold text-gray-900"><?= $totalUpload ?? 0 ?></p>
        </div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5 flex items-center gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-600 text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Berhasil</p>
            <p class="text-2xl font-bold text-green-700"><?= $totalSuccess ?? 0 ?></p>
        </div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-5 flex items-center gap-4">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-500 text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Gagal</p>
            <p class="text-2xl font-bold text-red-600"><?= $totalFailed ?? 0 ?></p>
        </div>
    </div>
</div>

<!-- Tabel Riwayat -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
    <div class="flex flex-col items-start gap-4 border-b border-gray-200 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Riwayat Upload</h2>
            <p class="mt-1 text-sm text-gray-600">Catatan seluruh aktivitas unggah dokumen Anda.</p>
        </div>
        <form action="<?= base_url('/riwayat') ?>" method="get" class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <input type="date" name="tanggal" value="<?= esc($selectedDate ?? '') ?>" onchange="this.form.submit()" class="px-4 py-2.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-900 focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-colors">
            <div class="relative w-full sm:w-64">
                <input type="text" name="keyword" placeholder="Cari riwayat..." class="w-full px-4 py-2.5 pr-10 text-sm border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-colors" value="<?= esc(request()->getGet('keyword')) ?>">
                <button type="submit" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-900 transition-colors">
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <div class="p-6">
        <?php if (!empty($histories)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">No</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Waktu</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Nama Dokumen</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Kategori</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Tipe</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Ukuran</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3 text-xs font-semibold text-gray-600 uppercase tracking-wide">Block Hash</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php
                        $page = (int)(request()->getGet('page_upload_history') ?? request()->getGet('page') ?? 1);
                        $startNo = ($page - 1) * 10 + 1;
                        ?>
                        <?php foreach ($histories as $i => $h): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3.5 text-gray-700 font-medium"><?= $startNo + $i ?></td>
                                <td class="px-4 py-3.5 text-gray-700 whitespace-nowrap">
                                    <div class="text-sm font-medium"><?= date('d M Y', strtotime($h['uploaded_at'])) ?></div>
                                    <div class="text-xs text-gray-500"><?= date('H:i:s', strtotime($h['uploaded_at'])) ?> WIB</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold text-gray-900 truncate max-w-[200px]" title="<?= esc($h['nama_dokumen']) ?>"><?= esc($h['nama_dokumen']) ?></p>
                                    <p class="text-xs text-gray-500 truncate max-w-[200px]" title="<?= esc($h['nomor_permohonan']) ?>">No: <?= esc($h['nomor_permohonan']) ?></p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-900 text-white">
                                        <?= esc($h['kategori_dokumen'] ?? '-') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-gray-700 font-mono text-xs">
                                    <?= esc($h['file_type'] ?? '-') ?>
                                </td>
                                <td class="px-4 py-3.5 text-gray-700 text-xs whitespace-nowrap">
                                    <?php
                                    if (!empty($h['file_size'])) {
                                        $sizeKB = $h['file_size'] / 1024;
                                        if ($sizeKB >= 1024) {
                                            echo number_format($sizeKB / 1024, 2) . ' MB';
                                        } else {
                                            echo number_format($sizeKB, 1) . ' KB';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td class="px-4 py-3.5">
                                    <?php if ($h['status'] === 'success'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            Berhasil
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800" title="<?= esc($h['keterangan'] ?? '') ?>">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                            </svg>
                                            Gagal
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3.5">
                                    <?php if (!empty($h['block_hash'])): ?>
                                        <span class="font-mono text-xs text-gray-600 break-all" title="<?= esc($h['block_hash']) ?>">
                                            <?= substr(esc($h['block_hash']), 0, 16) ?>...
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="mt-4 text-gray-500 font-medium">Tidak ada riwayat upload pada tanggal ini.</p>
                <p class="text-sm text-gray-400">Pilih tanggal lain atau mulai mengunggah dokumen.</p>
                <a href="<?= base_url('/upload') ?>" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Upload Dokumen
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($pager)) : ?>
        <div class="border-t border-gray-200 p-6">
            <?php if (!request()->getGet('keyword')): ?>
                <?= $pager->links('default', 'tailwind_pager') ?>
            <?php endif; ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
