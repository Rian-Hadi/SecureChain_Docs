<?php
$current_url = current_url(true);
$current_path = $current_url->getPath();

if (strlen($current_path) > 1) {
    $current_path = ltrim($current_path, '/');
}

function isActive($path, $current)
{
    return strpos($current, $path) !== false;
}
?>

<aside class="fixed left-0 top-0 h-screen w-64 bg-gradient-to-b from-[#7a7a52] via-[#4a5568] to-[#2d3e50] text-white shadow-2xl z-50 flex flex-col">
    <div class="p-6 border-b border-black/10">
        <div class="flex items-center gap-3">
            <div class="bg-white rounded-lg p-2 shadow-md">
                <svg class="w-8 h-8 text-[#2C3E50]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-white drop-shadow-md">User Panel</h1>
            </div>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-6 px-3">
        <div class="space-y-1">
            <a href="<?= base_url('/upload') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('upload', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span class="font-medium">Upload</span>
            </a>

            <a href="<?= base_url('/dokumen') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('dokumen', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="font-medium">Dokumen <?= session()->get('divisi') !== 'Admin' ? '(' . session()->get('divisi') . ')' : '' ?></span>
            </a>

            <a href="<?= base_url('/riwayat') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('riwayat', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">Riwayat Upload</span>
            </a>
        </div>
    </nav>

    <div class="border-t border-white/10 p-4 space-y-3">
        <div class="px-3 py-2 bg-black/20 rounded-lg backdrop-blur-sm">
            <p class="text-xs text-white/80">User</p>
            <p class="text-sm font-semibold text-white"><?= esc(session()->get('full_name') ?: session()->get('username') ?: 'Admin') ?></p>
        </div>
        <div class="px-3 py-2 bg-black/20 rounded-lg backdrop-blur-sm">
            <p class="text-xs text-white/80">Your IP</p>
            <p class="text-sm font-mono font-semibold text-white"><?= $_SERVER['REMOTE_ADDR'] ?? 'Unknown' ?></p>
        </div>

        <a href="<?= base_url('/auth/logout') ?>" class="block w-full rounded-lg bg-black/20 px-4 py-3 text-center text-sm font-semibold text-white transition hover:bg-black/30 backdrop-blur-sm border border-white/10">
            Logout
        </a>
    </div>
</aside>
