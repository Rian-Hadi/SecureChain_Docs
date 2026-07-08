<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3" title="Total Blocks">
                <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7l9-4 9 4v8l-9 4-9-4V7z" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1"><?= $stats['total_blocks'] ?? 0 ?></p>
        <p class="text-sm text-slate-600">Total Data Diamankan</p>
        <div class="mt-3 pt-3 border-t border-slate-200">
            <p class="text-xs text-slate-500">24 jam terakhir: +<?= $stats['blocks_24h'] ?? 0 ?></p>
        </div>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3" title="Total Backups">
                <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1"><?= $stats['total_backups'] ?? 0 ?></p>
        <p class="text-sm text-slate-600">Total Cadangan</p>
        <div class="mt-3 pt-3 border-t border-slate-200">
            <p class="text-xs text-slate-500">Cadangan terakhir: <?= $stats['last_backup_time'] ?? 'N/A' ?></p>
        </div>
    </div>

    <div class="bg-white border-2 border-slate-200 rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="bg-slate-900 rounded-lg p-3" title="Active IPs">
                <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2a10 10 0 100 20 10 10 0 000-20zM2 12h20M12 2v20" />
                </svg>
            </div>
        </div>
        <p class="text-3xl font-bold text-slate-900 mb-1"><?= $stats['active_ips'] ?? 0 ?></p>
        <p class="text-sm text-slate-600">IP Aktif</p>
        <div class="mt-3 pt-3 border-t border-slate-200">
            <p class="text-xs text-slate-500">Total: <?= $stats['total_ips'] ?? 0 ?> IP</p>
        </div>
    </div>

    <?php
    $totalIssues = $stats['total_issues'] ?? 0;
    $hasManipulation = $totalIssues > 0;
    ?>
    <div class="<?= $hasManipulation ? 'bg-red-600 border-2 border-red-800 text-white' : 'bg-white border-2 border-slate-200 text-slate-900' ?> rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <div class="<?= $hasManipulation ? 'bg-white' : 'bg-slate-900' ?> rounded-lg p-3" title="Data Issues Detected">
                <?php if ($hasManipulation): ?>
                    <svg class="w-6 h-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                <?php else: ?>
                    <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-3xl font-bold mb-1"><?= $totalIssues ?></p>
        <p class="text-sm <?= $hasManipulation ? 'text-white/90' : 'text-slate-600' ?>">Isu Data Terdeteksi</p>
        <div class="mt-3 pt-3 border-t <?= $hasManipulation ? 'border-red-700' : 'border-slate-200' ?>">
            <p class="text-xs <?= $hasManipulation ? 'text-white' : 'text-slate-500' ?>">
                <?php if ($hasManipulation): ?>
                    <strong>PERINGATAN!</strong> Sistem mendeteksi <strong><?= $totalIssues ?></strong> isu pada integritas data. Periksa panel di bawah untuk detail.
                <?php else: ?>
                    Tidak ada isu terdeteksi
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg mb-8 overflow-hidden">
    <div class="border-b-2 border-slate-200 p-6 bg-slate-50">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    Kesehatan Sinkronisasi Database
                </h2>
                <p class="mt-1 text-sm text-slate-600">Memastikan data konsisten di 3 database (Admin, User, Konsensus).</p>
            </div>
            <div class="flex gap-2">
                <button onclick="runQuickCheck()" id="quickCheckBtn"
                    class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 font-semibold text-sm transition-colors">
                    Pemeriksaan Cepat
                </button>
                <a href="<?= base_url('/admin/consensus/check') ?>"
                    class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-semibold text-sm transition-colors">
                    Pemeriksaan Lengkap
                </a>
            </div>
        </div>
    </div>

    <div class="p-6">
        <div id="consensusHealthPanel" class="w-full">
            <div class="mb-8 flex flex-col md:flex-row gap-6 animate-pulse">
                <div class="flex-grow bg-slate-100 rounded-2xl h-40"></div>
                <div class="w-full md:w-64 bg-slate-100 rounded-2xl h-40"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-pulse">
                <div class="bg-slate-100 rounded-2xl h-48"></div>
                <div class="bg-slate-100 rounded-2xl h-48"></div>
                <div class="bg-slate-100 rounded-2xl h-48"></div>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <div class="text-sm text-slate-600">
                <span id="lastCheckTime">Memeriksa...</span>
            </div>
            <div class="flex gap-2">
                <form action="<?= base_url('/admin/consensus/recover') ?>" method="POST" onsubmit="return confirm('Anda yakin ingin memulihkan data yang tidak sinkron secara otomatis? Sistem akan menggunakan data dari mayoritas database yang cocok.')">
                    <?= csrf_field() ?>
                    <button type="submit"
                        class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-semibold text-sm transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Pulihkan Blok
                    </button>
                </form>
                <a href="<?= base_url('/admin/consensus/history') ?>"
                    class="px-4 py-2 bg-slate-600 text-white rounded-lg hover:bg-slate-700 font-semibold text-sm transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Riwayat
                </a>
            </div>
        </div>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        runQuickCheck();
        // Polling setiap 10 detik untuk real-time update
        setInterval(runQuickCheck, 10000);
    });

    function runQuickCheck() {
        const btn = document.getElementById('quickCheckBtn');
        const panel = document.getElementById('consensusHealthPanel');
        const lastCheckTime = document.getElementById('lastCheckTime');

        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg> Memeriksa...';

        fetch('<?= base_url('/admin/consensus/quick-check') ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.data;
                    
                    const isHealthy = stats.is_truly_healthy;
                    const syncScore = stats.health_percentage;

                    // Colors based on health
                    const themeColor = isHealthy ? 'emerald' : (syncScore >= 90 ? 'amber' : 'red');
                    const themeBar = `bg-${themeColor}-500`;

                    const minorityIsAlert = (parseInt(stats.minority_corrupt) || 0) > 0;

                    const renderNodeCard = (title, dbName, anomalyCount, iconSvg) => {
                        const hasAnomaly = anomalyCount > 0;
                        const nodeColor = hasAnomaly ? 'red' : 'emerald';
                        
                        return `
                            <div class="relative overflow-hidden bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all duration-300 group">
                                <div class="absolute top-0 left-0 w-full h-1 bg-${nodeColor}-500"></div>
                                <div class="p-5">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="p-3 bg-slate-50 rounded-xl text-slate-600 group-hover:scale-110 transition-transform duration-300">
                                            ${iconSvg}
                                        </div>
                                        <div class="flex items-center gap-2 bg-${nodeColor}-50 px-3 py-1 rounded-full border border-${nodeColor}-100">
                                            <span class="relative flex h-2.5 w-2.5">
                                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-${nodeColor}-400 opacity-75"></span>
                                              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-${nodeColor}-500"></span>
                                            </span>
                                            <span class="text-xs font-semibold text-${nodeColor}-700 uppercase tracking-wider">${hasAnomaly ? 'Anomali' : 'Online'}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-slate-800 mb-1">${title}</h3>
                                        <p class="text-xs font-mono text-slate-500 mb-4">${dbName}</p>
                                    </div>
                                    <div class="mt-4 pt-4 border-t border-slate-100 flex justify-between items-center">
                                        <span class="text-sm font-medium text-slate-600">Status:</span>
                                        ${hasAnomaly 
                                            ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-red-100 text-red-700 font-bold text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> ${anomalyCount} Isu</span>`
                                            : `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-700 font-bold text-sm"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Sinkron</span>`
                                        }
                                    </div>
                                </div>
                            </div>
                        `;
                    };

                    const userIcon = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>`;
                    const adminIcon = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>`;
                    const konsensusIcon = `<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>`;

                    panel.innerHTML = `
                        <!-- Top Dashboard Header -->
                        <div class="mb-8 flex flex-col md:flex-row gap-6">
                            <!-- Main Sync Score Card -->
                            <div class="flex-grow bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col justify-center relative overflow-hidden">
                                <div class="absolute -right-10 -bottom-10 opacity-5">
                                    <svg class="w-48 h-48 text-${themeColor}-900" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
                                </div>
                                
                                <div class="flex justify-between items-end mb-4 relative z-10">
                                    <div>
                                        <h2 class="text-sm font-bold uppercase tracking-widest text-slate-500 mb-1">Skor Sinkronisasi Keseluruhan</h2>
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-5xl font-extrabold text-slate-900 tracking-tight">${syncScore}%</span>
                                            <span class="text-sm font-medium text-slate-500">Kesehatan Data</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-${themeColor}-100 text-${themeColor}-800 font-bold text-xs uppercase tracking-wider border border-${themeColor}-200">
                                            ${isHealthy ? 'Optimal' : (syncScore >= 90 ? 'Warning' : 'Kritis')}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="w-full bg-slate-100 rounded-full h-3 mb-2 relative z-10 overflow-hidden">
                                    <div class="${themeBar} h-3 rounded-full transition-all duration-1000 ease-out relative" style="width: ${syncScore}%">
                                        <div class="absolute top-0 left-0 w-full h-full bg-white/20 animate-pulse"></div>
                                    </div>
                                </div>
                                <div class="flex justify-between text-xs font-semibold text-slate-500 relative z-10">
                                    <span>0%</span>
                                    <span>${stats.healthy} dari ${stats.total_checked} block sinkron</span>
                                    <span>100%</span>
                                </div>
                            </div>

                            <!-- Anomaly Summary Card -->
                            <div class="w-full md:w-64 flex-shrink-0 bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-6 shadow-md text-white flex flex-col justify-center relative overflow-hidden">
                                 <!-- Decorative -->
                                 <div class="absolute top-0 right-0 p-4 opacity-10">
                                    <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                 </div>
                                 
                                 <div class="relative z-10">
                                    <p class="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">Anomali 2/3 Terdeteksi</p>
                                    <div class="flex items-baseline gap-2 mb-1">
                                        <span class="text-5xl font-extrabold ${minorityIsAlert ? 'text-red-400' : 'text-emerald-400'} drop-shadow-md">${stats.minority_corrupt}</span>
                                        <span class="text-sm font-medium text-slate-400">Kasus</span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-3 border-t border-slate-700 pt-3 leading-relaxed">
                                        ${minorityIsAlert ? 'Ketidakkonsistenan data (2/3 vs 1/3) ditemukan pada jaringan.' : 'Seluruh data sinkron. Tidak ada anomali 2/3 vs 1/3 yang terdeteksi.'}
                                    </p>
                                 </div>
                            </div>
                        </div>

                        <!-- Node Status Grid -->
                        <div class="flex items-center justify-between mb-4 px-1">
                            <h3 class="text-sm font-bold uppercase tracking-widest text-slate-500">Status Node Individu</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            ${renderNodeCard('Node User', 'userdb (Primary)', stats.anomalies_by_node?.userdb || 0, userIcon)}
                            ${renderNodeCard('Node Admin', 'admindb (Backup)', stats.anomalies_by_node?.admindb || 0, adminIcon)}
                            ${renderNodeCard('Node Konsensus', 'konsensus (Verifier)', stats.anomalies_by_node?.konsensus || 0, konsensusIcon)}
                        </div>
                    `;

                    // Tambahkan tabel Anomali 1/3 Manual jika ada
                    let anomalyHtml = '';
                    if (stats.anomaly_details && Object.keys(stats.anomaly_details).length > 0) {
                        anomalyHtml = `
                            <div class="mt-8 border-t border-slate-200 pt-6">
                                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    Penanganan Anomali 1/3 Manual
                                </h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200 border border-slate-200 rounded-lg overflow-hidden">
                                        <thead class="bg-slate-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Block Hash</th>
                                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Identifier</th>
                                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase">Corrupt Node</th>
                                                <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-slate-200">
                        `;
                        
                        Object.values(stats.anomaly_details).forEach(anomaly => {
                            if (anomaly.deletable_dbs && anomaly.deletable_dbs.length > 0) {
                                anomaly.deletable_dbs.forEach(db => {
                                    anomalyHtml += `
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-slate-900" title="${anomaly.record_key}">${anomaly.record_key.substring(0, 16)}...</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">${anomaly.identifier || '-'}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-md text-xs font-bold uppercase tracking-wider">${db}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <button onclick="deleteAnomalyJS('${anomaly.record_key}', '${db}')" class="px-3 py-1.5 bg-red-600 text-white rounded-md hover:bg-red-700 font-semibold text-xs transition-colors flex items-center gap-1 ml-auto">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                    Hapus Blok
                                                </button>
                                            </td>
                                        </tr>
                                    `;
                                });
                            }
                        });

                        anomalyHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                        
                        panel.innerHTML += anomalyHtml;
                    }

                    lastCheckTime.textContent = `Last check: ${new Date().toLocaleString('id-ID')} | Execution: ${stats.execution_time}s`;
                } else {
                    panel.innerHTML = '<div class="col-span-1 md:col-span-2 lg:col-span-4 text-center text-red-600 bg-red-50 border border-red-200 rounded-lg p-4">Error: ' + data.error + '</div>';
                }
            })
            .catch(error => {
                panel.innerHTML = '<div class="col-span-1 md:col-span-2 lg:col-span-4 text-center text-red-600 bg-red-50 border border-red-200 rounded-lg p-4">Failed to load consensus health</div>';
                console.error('Consensus check error:', error);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = 'Quick Check';
            });
    }

    function deleteAnomalyJS(hash, targetDb) {
        if (confirm('Apakah Anda yakin ingin menghapus blok anomali ini secara manual dari node ' + targetDb + '?\n\nPERINGATAN: Tindakan ini akan membypass trigger database dan menghapus data secara permanen dari node tersebut.')) {
            const formData = new FormData();
            formData.append('block_hash', hash);
            formData.append('target_db', targetDb);

            fetch('<?= base_url('/admin/consensus/delete-anomaly') ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    runQuickCheck(); // Refresh tampilan
                } else {
                    alert('Gagal: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan jaringan saat mencoba menghapus.');
            });
        }
    }
</script>

<div class="bg-white border-2 border-slate-200 rounded-lg mb-8">
    <div class="border-b-2 border-slate-200 p-6">
        <h2 class="text-xl font-bold text-slate-900">Recent Activity</h2>
        <p class="mt-1 text-sm text-slate-600">Aktivitas terbaru dalam sistem</p>
    </div>
    <div class="p-6">
        <div class="space-y-4">
            <?php if (!empty($latestBlocks)): ?>
                <?php foreach (array_slice($latestBlocks, 0, 5) as $block): ?>
                    <div class="flex items-start gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200 hover:bg-slate-100 transition-colors">
                        <div class="bg-slate-900 rounded-lg p-2 mt-1">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="flex-grow">
                            <p class="text-sm font-semibold text-slate-900">Block #<?= $block['id'] ?> Created</p>
                            <p class="text-xs text-slate-600 mt-1"><?= esc($block['nomor_permohonan']) ?></p>
                            <p class="text-xs text-slate-500 mt-1"><?= date('d/m/Y H:i:s', strtotime($block['timestamp'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-sm text-slate-500 text-center py-8">Belum ada aktivitas</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="bg-white border-2 border-slate-200 rounded-lg">
    <div class="border-b-2 border-slate-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">Latest Backups</h2>
                <p class="mt-1 text-sm text-slate-600">5 backup terbaru dalam sistem</p>
            </div>
            <a href="<?= base_url('/admin/backups') ?>"
                class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 font-semibold text-sm transition-colors">
                View All
            </a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Nomor Permohonan</th>
                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-700">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-700">Timestamp</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (!empty($latestBackups)): ?>
                    <?php foreach ($latestBackups as $backup): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-mono text-sm font-bold text-slate-900">#<?= $backup['id'] ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-semibold text-slate-900"><?= esc($backup['nomor_permohonan']) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($backup['backup_type'] === 'auto'): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-slate-900 text-white">Auto</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg bg-slate-200 text-slate-900 border border-slate-900">Manual</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                <?= date('d/m/Y H:i:s', strtotime($backup['backup_timestamp'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                            Belum ada backup
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>