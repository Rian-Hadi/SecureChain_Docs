<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-slate-900">Upload History</h1>
    <p class="text-sm text-slate-600 mt-1">Pantau statistik dan riwayat upload dokumen.</p>
</div>

<!-- Filter Section -->
<div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6 mb-8">
    <form action="<?= base_url('/admin/history') ?>" method="get" class="flex flex-col md:flex-row md:items-end gap-4">
        <div class="flex-1">
            <label for="tanggal" class="block text-sm font-medium text-slate-700 mb-1">Tanggal</label>
            <input type="date" id="tanggal" name="tanggal" value="<?= esc($tanggal) ?>" 
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-[#2d3e50] focus:ring focus:ring-[#2d3e50] focus:ring-opacity-50">
        </div>
        <div class="flex-1">
            <label for="kategori" class="block text-sm font-medium text-slate-700 mb-1">Kategori / Divisi</label>
            <select id="kategori" name="kategori" 
                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-[#2d3e50] focus:ring focus:ring-[#2d3e50] focus:ring-opacity-50">
                <option value="">Semua Kategori</option>
                <option value="Paten" <?= $kategori === 'Paten' ? 'selected' : '' ?>>Paten</option>
                <option value="Merek" <?= $kategori === 'Merek' ? 'selected' : '' ?>>Merek</option>
                <option value="Desain Industri" <?= $kategori === 'Desain Industri' ? 'selected' : '' ?>>Desain Industri</option>
                <option value="Hak Cipta" <?= $kategori === 'Hak Cipta' ? 'selected' : '' ?>>Hak Cipta</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-6 py-2 bg-[#2d3e50] text-white rounded-lg hover:bg-[#1a252f] transition-colors font-medium">
                Filter
            </button>
            <a href="<?= base_url('/admin/history') ?>" class="px-6 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors font-medium text-center">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Statistics Cards -->
<?php if (!empty($statistics)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <?php foreach ($statistics as $kat => $stat): ?>
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
            <h3 class="text-lg font-bold text-slate-900 mb-4"><?= esc($kat) ?></h3>
            <div class="flex justify-between items-end">
                <div>
                    <p class="text-3xl font-bold text-[#2d3e50]"><?= esc($stat['total']) ?></p>
                    <p class="text-xs text-slate-500 uppercase tracking-wider mt-1">Total Upload</p>
                </div>
                <div class="text-right space-y-1">
                    <p class="text-sm font-medium text-green-600 flex items-center justify-end gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <?= esc($stat['success']) ?> Berhasil
                    </p>
                    <p class="text-sm font-medium text-red-600 flex items-center justify-end gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        <?= esc($stat['failed']) ?> Gagal
                    </p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-8">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-blue-700">Tidak ada data statistik untuk tanggal ini.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- History Table -->
<div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal & Waktu</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">User</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Dokumen</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Kategori</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-200">
                <?php if (!empty($history)): ?>
                    <?php foreach ($history as $item): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                <?= date('d/m/Y H:i', strtotime($item['uploaded_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900"><?= esc($item['username'] ?? 'Unknown') ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-900"><?= esc($item['nama_dokumen']) ?></div>
                                <div class="text-xs text-slate-500">No. Permohonan: <?= esc($item['nomor_permohonan']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                    <?= esc($item['kategori_dokumen'] ?? 'Tidak Diketahui') ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($item['status'] === 'success'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="-ml-0.5 mr-1.5 h-3 w-3 text-green-500" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                                        Sukses
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <svg class="-ml-0.5 mr-1.5 h-3 w-3 text-red-500" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                                        Gagal
                                    </span>
                                    <?php if (!empty($item['keterangan'])): ?>
                                        <div class="text-xs text-red-500 mt-1 max-w-xs truncate" title="<?= esc($item['keterangan']) ?>">
                                            <?= esc($item['keterangan']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                            Tidak ada riwayat upload ditemukan untuk kriteria ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($pager): ?>
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
            <?= $pager->links() ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
