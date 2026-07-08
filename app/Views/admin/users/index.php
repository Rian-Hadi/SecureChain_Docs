<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="mb-6 rounded-lg bg-green-50 border-l-4 border-green-500 p-4">
        <p class="text-green-800 font-medium"><?= session()->getFlashdata('success') ?></p>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="mb-6 rounded-lg bg-red-50 border-l-4 border-red-500 p-4">
        <p class="text-red-800 font-medium"><?= session()->getFlashdata('error') ?></p>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('errors')): ?>
    <div class="mb-6 rounded-lg bg-red-50 border-l-4 border-red-500 p-4">
        <p class="text-red-800 font-medium">Kesalahan Validasi:</p>
        <ul class="mt-2 ml-5 list-disc">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li class="text-red-700"><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-900">Manajemen Pengguna</h1>
    <p class="mt-2 text-slate-600">Kelola akun pengguna sistem</p>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg p-6 mb-8">
    <h2 class="text-xl font-bold text-slate-900 mb-4">Tambah User Baru</h2>

    <form action="<?= base_url('/admin/users/add') ?>" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?= csrf_field() ?>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Nama Pengguna</label>
            <input type="text" name="username" value="<?= old('username') ?>" required
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none"
                placeholder="Minimal 4 karakter">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Kata Sandi</label>
            <input type="password" name="password" required
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none"
                placeholder="Minimal 6 karakter">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Nama Lengkap</label>
            <input type="text" name="full_name" value="<?= old('full_name') ?>" required
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none"
                placeholder="Nama Lengkap">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Role</label>
            <select name="role" required
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Divisi</label>
            <select name="divisi" required
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none">
                <?php
                $divisiList = ['Paten', 'Merek', 'Hak Cipta', 'Desain Industri', 'Admin'];
                foreach ($divisiList as $divisi):
                    $selected = old('divisi') === $divisi ? 'selected' : '';
                ?>
                    <option value="<?= esc($divisi) ?>" <?= $selected ?>><?= esc($divisi) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end">
            <button type="submit"
                class="w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
                Tambahkan Pengguna
            </button>
        </div>
    </form>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg">
    <div class="border-b-2 border-slate-200 p-6">
        <h2 class="text-xl font-bold text-slate-900">Daftar Pengguna</h2>
        <p class="mt-1 text-sm text-slate-600">Total: <?= count($users) ?> pengguna</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Nama Pengguna</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Nama Lengkap</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Peran</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Login Terakhir</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm font-semibold text-slate-900">#<?= $user['id'] ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-medium text-slate-900"><?= esc($user['username']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-slate-600"><?= esc($user['full_name']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-semibold rounded <?= $user['role'] === 'admin' ? 'bg-slate-900 text-white' : 'bg-slate-500 text-white' ?>">
                                    <?= strtoupper($user['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded <?= $user['is_active'] == 1 ? 'bg-green-600 text-white' : 'bg-red-600 text-white' ?>">
                                    <?= $user['is_active'] == 1 ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <?php if ($user['id'] != session()->get('user_id')): ?>
                                        <a href="<?= base_url('/admin/users/edit/' . $user['id']) ?>"
                                            class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded transition-colors">
                                            Edit
                                        </a>

                                        <a href="<?= base_url('/admin/users/toggle/' . $user['id']) ?>"
                                            class="px-3 py-1 <?= $user['is_active'] == 1 ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-600 hover:bg-green-700' ?> text-white text-xs font-semibold rounded transition-colors"
                                            onclick="return confirm('Ubah status user ini?')">
                                            <?= $user['is_active'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </a>

                                        <a href="<?= base_url('/admin/users/delete/' . $user['id']) ?>"
                                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded transition-colors"
                                            onclick="return confirm('Yakin ingin menghapus user ini?\n\nUsername: <?= esc($user['username']) ?>\nNama: <?= esc($user['full_name']) ?>')">
                                            Hapus
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= base_url('/admin/users/edit/' . $user['id']) ?>"
                                            class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded transition-colors">
                                            Edit Profile
                                        </a>

                                        <a href="<?= base_url('/admin/users/toggle/' . $user['id']) ?>"
                                            class="px-3 py-1 <?= $user['is_active'] == 1 ? 'bg-amber-600 hover:bg-amber-700' : 'bg-green-600 hover:bg-green-700' ?> text-white text-xs font-semibold rounded transition-colors"
                                            onclick="return confirm('Ubah status user ini?')">
                                            <?= $user['is_active'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </a>

                                        <a href="<?= base_url('/admin/users/delete/' . $user['id']) ?>"
                                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded transition-colors"
                                            onclick="return confirm('⚠️ PERINGATAN: Anda akan menghapus akun Anda sendiri!\n\nUsername: <?= esc($user['username']) ?>\nNama: <?= esc($user['full_name']) ?>\n\nSetelah dihapus, Anda akan logout otomatis dan tidak bisa login lagi.\n\nYakin ingin melanjutkan?')">
                                            Hapus Akun
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                            Belum ada pengguna
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>