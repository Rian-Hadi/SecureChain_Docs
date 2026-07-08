<?= $this->extend('layouts/user') ?>

<?= $this->section('content') ?>

<div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
    <div class="border-b border-gray-200 p-6">
        <div class="flex items-center gap-3">
            <div class="bg-gray-900 rounded-lg p-2 flex-shrink-0">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-gray-900 truncate">Unggah Dokumen</h1>
                <p class="text-sm text-gray-600 truncate">Amankan dokumen Anda ke blockchain</p>
            </div>
        </div>
    </div>

    <div class="p-6 overflow-x-hidden">
        <!-- FIX: Display flash messages and validation errors -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-red-800">Error</p>
                        <p class="text-sm text-red-700 mt-1"><?= esc(session()->getFlashdata('error')) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center gap-3">
                    <div>
                        <p class="text-sm text-green-700 mt-1"><?= esc(session()->getFlashdata('success')) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php $validationErrors = session('_ci_validation_errors') ?? []; ?>
        <?php if ($validationErrors !== []): ?>
            <div class="mb-4 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                <p class="text-sm font-semibold text-orange-800 mb-2">Validasi Error:</p>
                <ul class="list-disc list-inside text-sm text-orange-700 space-y-1">
                    <?php foreach ($validationErrors as $field => $error): ?>
                        <li><?= esc($field) ?>: <?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('/create') ?>" method="post" enctype="multipart/form-data" x-data="{ submitting: false }" @submit="submitting = true">
            <?= csrf_field() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="nama_dokumen" class="block text-sm font-semibold text-gray-900">Nama Dokumen</label>
                    <input type="text" id="nama_dokumen" name="nama_dokumen" class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 placeholder-gray-400 transition-colors" value="<?= old('nama_dokumen') ?>" placeholder="Masukkan nama dokumen" required>
                </div>
                <div>
                    <label for="nomor_permohonan" class="block text-sm font-semibold text-gray-900">Nomor Permohonan</label>
                    <input type="text" id="nomor_permohonan" name="nomor_permohonan" class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 placeholder-gray-400 transition-colors" value="<?= old('nomor_permohonan') ?>" placeholder="Masukkan nomor" required>
                </div>
                <div>
                    <label for="nomor_dokumen" class="block text-sm font-semibold text-gray-900">Nomor Dokumen</label>
                    <input type="text" id="nomor_dokumen" name="nomor_dokumen" class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 placeholder-gray-400 transition-colors" value="<?= old('nomor_dokumen') ?>" placeholder="Masukkan nomor" required>
                </div>
                <div>
                    <label for="kategori_dokumen" class="block text-sm font-semibold text-gray-900">Kategori Dokumen</label>
                    <select id="kategori_dokumen" name="kategori_dokumen" class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-colors" required>
                        <?php
                        $kategoriList = ['Paten', 'Merek', 'Hak Cipta', 'Desain Industri'];
                        $userDivisi = $userDivisi ?? 'Paten';
                        foreach ($kategoriList as $kategori):
                            $selected = ($kategori === $userDivisi || old('kategori_dokumen') === $kategori) ? 'selected' : '';
                            $disabled = ($userDivisi !== 'Admin' && $kategori !== $userDivisi) ? 'disabled' : '';
                        ?>
                            <option value="<?= esc($kategori) ?>" <?= $selected ?> <?= $disabled ?>><?= esc($kategori) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($userDivisi !== 'Admin'): ?>
                        <p class="text-xs text-gray-500 mt-1">Kategori terkunci sesuai divisi Anda</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="tanggal_dokumen" class="block text-sm font-semibold text-gray-900">Tanggal Dokumen</label>
                    <input type="date" id="tanggal_dokumen" name="tanggal_dokumen" class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-colors" value="<?= old('tanggal_dokumen') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label for="tanggal_filing" class="block text-sm font-semibold text-gray-900">Tanggal Filing</label>
                    <input type="date" id="tanggal_filing" name="tanggal_filing" class="mt-1.5 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-1 focus:ring-gray-900 transition-colors" value="<?= old('tanggal_filing') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="md:col-span-2">
                    <label for="dokumen" class="block text-sm font-semibold text-gray-900">Pilih Dokumen</label>
                    <span class="text-xs text-gray-600 block mt-1">PDF, DOCX, JPG, PNG | Max: 5MB</span>
                    <input type="file" id="dokumen" name="dokumen"
                        accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/jpeg,image/png"
                        class="mt-1.5 block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-gray-800 file:cursor-pointer file:transition-colors"
                        required
                        onchange="validateFileInput(this)">
                    <p id="file-error" class="mt-1.5 text-xs text-red-600 hidden"></p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <a href="<?= base_url('/dokumen') ?>" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors flex-shrink-0">
                    Batal
                </a>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex-shrink-0" :disabled="submitting">
                    <span x-show="!submitting" class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                        </svg>
                        Unggah
                    </span>
                    <span x-show="submitting" style="display: none;" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Mengunggah...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<script>
/**
 * Validasi sisi klien untuk whitelist tipe file dan ukuran.
 * Ini bersifat UX — validasi sesungguhnya tetap di server (controller).
 */
const ALLOWED_EXTENSIONS = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];
const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

function validateFileInput(input) {
    const errorEl = document.getElementById('file-error');
    const submitBtn = input.closest('form').querySelector('button[type="submit"]');

    errorEl.textContent = '';
    errorEl.classList.add('hidden');

    if (!input.files || input.files.length === 0) return;

    const file = input.files[0];
    const ext = file.name.split('.').pop().toLowerCase();

    if (!ALLOWED_EXTENSIONS.includes(ext)) {
        const msg = `Tipe file ".${ext}" tidak diizinkan. Hanya PDF, DOCX, JPG, PNG yang diterima.`;
        showFileError(errorEl, submitBtn, input, msg);
        return;
    }

    if (file.size > MAX_SIZE_BYTES) {
        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        const msg = `Ukuran file (${sizeMB} MB) melebihi batas maksimum 5 MB.`;
        showFileError(errorEl, submitBtn, input, msg);
        return;
    }

    // File valid — pastikan tombol submit aktif kembali
    if (submitBtn) submitBtn.disabled = false;
}

function showFileError(errorEl, submitBtn, input, message) {
    errorEl.textContent = message;
    errorEl.classList.remove('hidden');
    input.value = ''; // reset input
    if (submitBtn) submitBtn.disabled = true;
}
</script>
