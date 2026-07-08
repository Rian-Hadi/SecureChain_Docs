<?= $this->extend('layouts/user') ?>

<?= $this->section('content') ?>

<div class="max-w-4xl mx-auto">
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-8">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Selamat Datang di Sistem Blockchain</h1>
            <p class="text-gray-600 mb-8">Gunakan menu di sidebar untuk mengunggah dokumen atau melihat dokumen yang tersimpan.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?= base_url('/upload') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-6 py-3 text-sm font-semibold text-white hover:bg-black transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Unggah Dokumen
                </a>
                <a href="<?= base_url('/dokumen') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-900 hover:bg-gray-50 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Lihat Dokumen
                </a>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
