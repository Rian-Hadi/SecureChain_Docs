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
                <h1 class="text-lg font-bold text-white drop-shadow-md">Admin Panel</h1>
            </div>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-6 px-3">
        <div class="space-y-1">
            <a href="<?= base_url('/admin/dashboard') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('admin/dashboard', $current_path) || $current_path === 'admin' ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="font-medium">Dashboard</span>
            </a>

            <a href="<?= base_url('/admin/history') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('admin/history', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-medium">Upload History</span>
            </a>

            <a href="<?= base_url('/admin/explorer') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('admin/explorer', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span class="font-medium">Explorer</span>
            </a>

            <a href="<?= base_url('/admin/monitoring') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('admin/monitoring', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span class="font-medium">Monitoring</span>
            </a>

            <a href="<?= base_url('/admin/backups') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('admin/backups', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                </svg>
                <span class="font-medium">Backups</span>
            </a>

            <a href="<?= base_url('/admin/whitelist') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('admin/whitelist', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span class="font-medium">IP Whitelist</span>
            </a>

            <a href="<?= base_url('/admin/users') ?>"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all <?= isActive('admin/users', $current_path) ? 'bg-white text-[#2d3e50] shadow-lg' : 'text-white/90 hover:bg-white/10 hover:text-white' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span class="font-medium">Users</span>
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
