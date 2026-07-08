<?= $this->extend('layouts/user') ?>

<?= $this->section('content') ?>

<div class="rounded-2xl border border-gray-200 bg-white shadow-sm">
    <div class="flex flex-col items-start gap-4 border-b border-gray-200 p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Dokumen Tersimpan</h2>
            <p class="mt-1 text-sm text-gray-600">Daftar dokumen <?= $userDivisi !== 'Admin' ? $userDivisi : 'semua kategori' ?> yang telah diamankan dalam rantai blok.</p>
        </div>
        <form action="<?= base_url('/') ?>" method="get" class="relative w-full sm:w-auto">
            <input type="text" name="keyword" placeholder="Cari dokumen..." class="w-full px-4 py-2.5 pr-10 text-sm border border-gray-300 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-colors sm:w-64" value="<?= esc(request()->getGet('keyword')) ?>">
            <button type="submit" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-900 transition-colors">
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </button>
        </form>
    </div>

    <div class="p-6">
        <?php if (!empty($documents)): ?>
            <div class="space-y-4">
                <?php foreach ($documents as $doc): ?>
                    <div class="flex flex-col sm:flex-row gap-4 p-5 border border-gray-200 rounded-xl hover:border-gray-300 hover:shadow-md transition-all duration-200 bg-gray-50">
                        <!-- ID Badge & Main Info -->
                        <div class="flex gap-4 flex-1">
                            <div>
                                <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-gray-900 text-sm font-bold text-white">
                                    <?= esc($doc['id']) ?>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <!-- Document Name -->
                                <div class="mb-4">
                                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Nama Dokumen</p>
                                    <p class="text-base font-bold text-gray-900 break-words"><?= esc($doc['nama_dokumen']) ?></p>
                                </div>

                                <!-- Kategori Badge -->
                                <div class="mb-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-900 text-white">
                                        <?= esc($doc['kategori_dokumen'] ?? 'Paten') ?>
                                    </span>
                                </div>

                                <!-- Header with dates -->
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                                    <div>
                                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">No. Permohonan</p>
                                        <p class="text-sm font-semibold text-gray-900 break-words"><?= esc($doc['nomor_permohonan']) ?></p>
                                    </div>
                                    <div class="flex gap-4 text-xs">
                                        <div class="text-center sm:text-left">
                                            <p class="text-gray-600 font-medium">Tgl. Dokumen</p>
                                            <p class="font-semibold text-gray-900"><?= esc($doc['tanggal_dokumen']) ?></p>
                                        </div>
                                        <div class="text-center sm:text-left">
                                            <p class="text-gray-600 font-medium">Tgl. Filing</p>
                                            <p class="font-semibold text-gray-900"><?= esc($doc['tanggal_filing']) ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Document Number -->
                                <div class="mb-4">
                                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">No. Dokumen</p>
                                    <p class="text-sm font-semibold text-gray-900"><?= esc($doc['nomor_dokumen']) ?></p>
                                </div>

                                <!-- Hash Section -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Hash Blok</p>
                                        <p class="font-mono text-xs text-gray-900 break-all"><?= substr(esc($doc['block_hash']), 0, 40) ?>...</p>
                                    </div>
                                    <div class="bg-white p-3 rounded-lg border border-gray-200">
                                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Hash Sebelumnya</p>
                                        <p class="font-mono text-xs text-gray-700 break-all"><?= substr(esc($doc['previous_hash']), 0, 40) ?>...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="flex items-center sm:flex-col gap-3 sm:gap-2 sm:justify-center sm:min-w-max">
                            <a href="<?= base_url('/download/' . esc($doc['block_hash'])) ?>" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-6 py-2.5 text-xs font-semibold text-white hover:bg-black transition-colors duration-200">
                                Unduh
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="mt-4 text-gray-500 font-medium">Tidak ada dokumen yang ditemukan.</p>
                <p class="text-sm text-gray-400">Mulai dengan mengunggah dokumen pertama Anda.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($pager) : ?>
        <div class="border-t border-gray-200 p-6">
            <?php if (!request()->getGet('keyword')): ?>
                <?= $pager->links('default', 'tailwind_pager') ?>
            <?php endif; ?>
        </div>
    <?php endif ?>
</div>

<?= $this->endSection() ?>
