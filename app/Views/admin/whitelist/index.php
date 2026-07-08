<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="mb-6 rounded-lg bg-slate-100 border-l-4 border-slate-900 p-4">
        <p class="text-slate-900 font-medium"><?= session()->getFlashdata('success') ?></p>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="mb-6 rounded-lg bg-slate-100 border-l-4 border-slate-900 p-4">
        <p class="text-slate-900 font-medium"><?= session()->getFlashdata('error') ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1"><?= number_format(count($whitelistIPs)) ?></p>
        <p class="text-sm text-slate-600">Total IPs</p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1">
            <?= number_format(count(array_filter($whitelistIPs, function ($ip) {
                return $ip['is_active'];
            }))) ?>
        </p>
        <p class="text-sm text-slate-600">Active IPs</p>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1">
            <?= number_format(count(array_filter($whitelistIPs, function ($ip) {
                return !$ip['is_active'];
            }))) ?>
        </p>
        <p class="text-sm text-slate-600">Inactive IPs</p>
    </div>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg mb-8">
    <div class="border-b-2 border-slate-200 p-6">
        <h2 class="text-xl font-bold text-slate-900">Add New IP</h2>
        <p class="mt-1 text-sm text-slate-600">Masukkan IP address yang ingin diizinkan mengakses admin panel</p>
    </div>
    <div class="p-6">
        <form action="<?= base_url('/admin/whitelist/add') ?>" method="post" class="space-y-5">
            <?= csrf_field() ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="ip_address" class="block text-sm font-bold text-slate-700 mb-2">
                        IP Address <span class="text-slate-900">*</span>
                    </label>
                    <input type="text" id="ip_address" name="ip_address"
                        placeholder="192.168.1.100"
                        class="w-full px-4 py-3 rounded-lg border-2 border-slate-300 focus:border-slate-900 focus:ring-2 focus:ring-slate-200 transition-all"
                        required>
                    <p class="mt-1 text-xs text-slate-500">Format: xxx.xxx.xxx.xxx</p>
                </div>
                <div>
                    <label for="description" class="block text-sm font-bold text-slate-700 mb-2">
                        Description <span class="text-slate-400">(Optional)</span>
                    </label>
                    <input type="text" id="description" name="description"
                        placeholder="Contoh: Perencanaan TI"
                        class="w-full px-4 py-3 rounded-lg border-2 border-slate-300 focus:border-slate-900 focus:ring-2 focus:ring-slate-200 transition-all">
                    <p class="mt-1 text-xs text-slate-500">Keterangan untuk identifikasi IP</p>
                </div>
            </div>
            <div class="flex items-center justify-between pt-4 border-t-2 border-slate-200">
                <div class="flex items-center gap-2 text-sm text-slate-600">
                    <span>Your IP: <strong class="font-mono text-slate-900"><?= $_SERVER['REMOTE_ADDR'] ?? 'Unknown' ?></strong></span>
                </div>
                <button type="submit"
                    class="flex items-center gap-2 px-6 py-3 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add to Whitelist
                </button>
            </div>
        </form>
    </div>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg">
    <div class="border-b-2 border-slate-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">IP Whitelist</h2>
                <p class="mt-1 text-sm text-slate-600">Semua IP address yang terdaftar dalam sistem</p>
            </div>
            <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold border border-slate-200">
                <?= count($whitelistIPs) ?> IPs
            </span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">IP Address</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Description</th>
                    <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-700">Status</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Added By</th>
                    <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Date</th>
                    <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (!empty($whitelistIPs)): ?>
                    <?php foreach ($whitelistIPs as $ip): ?>
                        <tr class="hover:bg-slate-50 transition-colors <?= $ip['is_active'] ? '' : 'bg-slate-100' ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="bg-slate-900 rounded-lg p-2">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                        </svg>
                                    </div>
                                    <span class="font-mono text-sm font-bold text-slate-900"><?= esc($ip['ip_address']) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-slate-700"><?= esc($ip['description'] ?? '-') ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($ip['is_active']): ?>
                                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-lg bg-slate-900 text-white">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-bold rounded-lg bg-slate-200 text-slate-900 border border-slate-900">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-slate-600"><?= esc($ip['added_by'] ?? 'System') ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">
                                            <?= date('d/m/Y', strtotime($ip['created_at'])) ?>
                                        </p>
                                        <p class="text-xs text-slate-500">
                                            <?= date('H:i', strtotime($ip['created_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if ($ip['is_active']): ?>
                                        <a href="<?= base_url('/admin/whitelist/deactivate/' . $ip['id']) ?>"
                                            class="px-3 py-1.5 bg-slate-200 text-slate-900 rounded-lg hover:bg-slate-300 font-semibold text-sm transition-colors border border-slate-900"
                                            onclick="return confirm('Yakin ingin menonaktifkan IP ini?')">
                                            Deactivate
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= base_url('/admin/whitelist/activate/' . $ip['id']) ?>"
                                            class="px-3 py-1.5 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold text-sm transition-colors">
                                            Activate
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= base_url('/admin/whitelist/delete/' . $ip['id']) ?>"
                                        class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 font-semibold text-sm transition-colors border border-slate-200"
                                        onclick="return confirm('Yakin ingin menghapus IP ini dari whitelist?')">
                                        Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                            <svg class="w-16 h-16 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <p class="text-lg font-semibold">Belum ada IP dalam whitelist</p>
                            <p class="text-sm mt-1">Tambahkan IP pertama untuk mengamankan akses admin panel</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>