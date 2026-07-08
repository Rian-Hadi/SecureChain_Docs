<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="mb-6 rounded-lg bg-red-50 border-l-4 border-red-500 p-4">
        <p class="text-red-800 font-medium"><?= session()->getFlashdata('error') ?></p>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('errors')): ?>
    <div class="mb-6 rounded-lg bg-red-50 border-l-4 border-red-500 p-4">
        <p class="text-red-800 font-medium">Validation Errors:</p>
        <ul class="mt-2 ml-5 list-disc">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li class="text-red-700"><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">
                <?= $user['id'] == session()->get('user_id') ? 'Edit Profile' : 'Edit User' ?>
            </h1>
            <p class="mt-2 text-slate-600">
                <?= $user['id'] == session()->get('user_id') ? 'Update informasi profile Anda' : 'Update informasi user' ?>
            </p>
            <?php if ($user['id'] == session()->get('user_id')): ?>
                <div class="mt-2 px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded inline-block">
                    <i class="fas fa-user-circle mr-1"></i>Ini adalah profile Anda
                </div>
            <?php endif; ?>
        </div>
        <a href="<?= base_url('/admin/users') ?>"
            class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-semibold rounded-lg transition-colors">
            ← Kembali
        </a>
    </div>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg p-6">
    <form action="<?= base_url('/admin/users/update/' . $user['id']) ?>" method="POST" class="space-y-6">
        <?= csrf_field() ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Username <span class="text-red-500">*</span>
                </label>
                <input type="text" name="username" value="<?= old('username', $user['username']) ?>" required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none"
                    placeholder="Username">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Nama Lengkap <span class="text-red-500">*</span>
                </label>
                <input type="text" name="full_name" value="<?= old('full_name', $user['full_name']) ?>" required
                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none"
                    placeholder="Nama Lengkap">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">
                Password <span class="text-slate-500">(Kosongkan jika tidak ingin ubah)</span>
            </label>
            <input type="password" name="password"
                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none"
                placeholder="Masukkan password baru">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Role <span class="text-red-500">*</span>
                </label>
                <?php if ($user['id'] == session()->get('user_id')): ?>
                    <select name="role" required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-500"
                        disabled>
                        <option value="<?= $user['role'] ?>" selected>
                            <?= strtoupper($user['role']) ?>
                        </option>
                    </select>
                    <input type="hidden" name="role" value="<?= $user['role'] ?>">
                    <p class="text-xs text-slate-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>Role tidak dapat diubah untuk diri sendiri
                    </p>
                <?php else: ?>
                    <select name="role" required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none">
                        <option value="user" <?= old('role', $user['role']) === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= old('role', $user['role']) === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">
                    Divisi <span class="text-red-500">*</span>
                </label>
                <?php if ($user['id'] == session()->get('user_id') && $user['role'] === 'admin'): ?>
                    <select name="divisi" required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none">
                        <?php
                        $divisiList = ['Paten', 'Merek', 'Hak Cipta', 'Desain Industri', 'Admin'];
                        foreach ($divisiList as $divisi):
                            $selected = old('divisi', $user['divisi'] ?? 'Paten') === $divisi ? 'selected' : '';
                        ?>
                            <option value="<?= esc($divisi) ?>" <?= $selected ?>><?= esc($divisi) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($user['id'] == session()->get('user_id')): ?>
                    <select name="divisi" required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-500"
                        disabled>
                        <option value="<?= $user['divisi'] ?? 'Paten' ?>" selected>
                            <?= $user['divisi'] ?? 'Paten' ?>
                        </option>
                    </select>
                    <input type="hidden" name="divisi" value="<?= $user['divisi'] ?? 'Paten' ?>">
                    <p class="text-xs text-slate-500 mt-1">
                        <i class="fas fa-info-circle mr-1"></i>Divisi tidak dapat diubah untuk diri sendiri
                    </p>
                <?php else: ?>
                    <select name="divisi" required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:border-slate-500 focus:outline-none">
                        <?php
                        $divisiList = ['Paten', 'Merek', 'Hak Cipta', 'Desain Industri', 'Admin'];
                        foreach ($divisiList as $divisi):
                            $selected = old('divisi', $user['divisi'] ?? 'Paten') === $divisi ? 'selected' : '';
                        ?>
                            <option value="<?= esc($divisi) ?>" <?= $selected ?>><?= esc($divisi) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
            <p class="text-sm text-slate-600">
                <strong>User ID:</strong> #<?= $user['id'] ?>
            </p>
            <p class="text-sm text-slate-600 mt-1">
                <strong>Created:</strong> <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>
            </p>
            <?php if ($user['last_login']): ?>
                <p class="text-sm text-slate-600 mt-1">
                    <strong>Last Login:</strong> <?= date('d/m/Y H:i', strtotime($user['last_login'])) ?>
                    <?php if ($user['last_login_ip']): ?>
                        from IP <?= esc($user['last_login_ip']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="flex gap-3">
            <button type="submit"
                class="px-6 py-2 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-lg transition-colors">
                Update User
            </button>
            <a href="<?= base_url('/admin/users') ?>"
                class="px-6 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-semibold rounded-lg transition-colors">
                Batal
            </a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>