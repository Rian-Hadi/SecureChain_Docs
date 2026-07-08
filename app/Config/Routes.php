<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ========================================
// Rute Publik (Tanpa Filter)
// ========================================

// Auth Routes (Dilindungi dari Brute Force/DDoS)
$routes->group('auth', ['filter' => 'rate_limit'], static function ($routes) {
    $routes->get('login', 'Auth::login');
    $routes->post('process-login', 'Auth::processLogin');
    $routes->get('logout', 'Auth::logout');
    $routes->get('access-denied', 'Auth::accessDenied');
});

// API Auth Routes (Dilindungi dari Brute Force/DDoS)
$routes->group('api/auth', ['filter' => 'rate_limit'], static function ($routes) {
    $routes->post('validate-token', 'Auth::validateToken');
    $routes->post('refresh-token', 'Auth::refreshToken');
});

// Halaman Access Denied (IP Whitelist)
$routes->get('access-denied', 'Auth::accessDenied');

// ========================================
// Rute User (Halaman Utama - Dilindungi Authentication)
// ========================================

$routes->group('', ['filter' => 'auth:user,admin'], static function ($routes) {
    // Rute utama (halaman home yang menampilkan form dan daftar)
    $routes->get('/', 'Document::index');

    // Rute halaman upload dokumen
    $routes->get('upload', 'Document::upload');

    // Rute halaman daftar dokumen
    $routes->get('dokumen', 'Document::dokumen');

    // Rute halaman riwayat upload
    $routes->get('riwayat', 'Document::riwayat');

    // Rute untuk mengunduh file berdasarkan hash blok
    $routes->get('download/(:any)', 'Document::download/$1');

    // Rute untuk memproses data dari form upload (dengan rate limiting)
    $routes->group('', ['filter' => 'rate_limit'], static function ($routes) {
        $routes->post('create', 'Document::create');
        $routes->post('document/create', 'Document::create');
    });
});

// ========================================
// Rute Admin Panel (Dilindungi Auth + IP Whitelist)
// ========================================

$routes->group('admin', ['filter' => ['auth:admin', 'ip_whitelist']], static function ($routes) {

    // Dashboard Admin
    $routes->get('/', 'Admin::dashboard');
    $routes->get('dashboard', 'Admin::dashboard');

    // Upload History
    $routes->get('history', 'Admin::history');

    // Blockchain Explorer (Read-only monitoring)
    $routes->get('explorer', 'Admin::explorer');

    // System Monitoring
    $routes->get('monitoring', 'Admin::monitoring');

    // Manajemen Backup
    $routes->get('backups', 'Admin::backups');
    $routes->get('backup/create', 'Admin::createBackup');

    // Manajemen IP Whitelist
    $routes->get('whitelist', 'Admin::whitelist');
    $routes->post('whitelist/add', 'Admin::addWhitelist');
    $routes->get('whitelist/activate/(:num)', 'Admin::activateIP/$1');
    $routes->get('whitelist/deactivate/(:num)', 'Admin::deactivateIP/$1');
    $routes->get('whitelist/delete/(:num)', 'Admin::deleteWhitelist/$1');

    // Recovery Manual
    $routes->get('recover/(:num)', 'Admin::manualRecover/$1');

    // Consensus Recovery (3-Database Majority Voting)
    $routes->get('consensus/check', 'Admin::consensusCheck');
    $routes->post('consensus/recover', 'Admin::consensusRecover');
    $routes->get('consensus/history', 'Admin::recoveryHistory');
    $routes->post('consensus/rollback/(:num)', 'Admin::consensusRollback/$1');
    $routes->get('consensus/quick-check', 'Admin::quickConsensusCheck'); // AJAX
    $routes->post('consensus/delete-anomaly', 'Admin::deleteAnomaly'); // AJAX Hapus Anomaly

    // Integrity Check (Validasi Dua Lapis — Lapis 1: Re-Hash + Lapis 2: Consensus 2/3)
    $routes->get('integrity/check', 'Admin::integrityCheck');          // Halaman hasil integrity check
    $routes->post('integrity/run', 'Admin::runIntegrityCheck');        // Trigger integrity check manual

    // User Management
    $routes->get('users', 'Admin::users');
    $routes->post('users/add', 'Admin::addUser');
    $routes->get('users/edit/(:num)', 'Admin::editUser/$1');
    $routes->post('users/update/(:num)', 'Admin::updateUser/$1');
    $routes->get('users/delete/(:num)', 'Admin::deleteUser/$1');
    $routes->get('users/toggle/(:num)', 'Admin::toggleUserStatus/$1');
});

// ========================================
// API RESTful Endpoints
// ========================================

$routes->group('api', ['filter' => 'rate_limit'], static function ($routes) {

    // Blocks
    $routes->get('blocks', 'Api::blocks');
    $routes->get('blocks/(:num)', 'Api::block/$1');
    $routes->get('blocks/hash/(:any)', 'Api::blockByHash/$1');

    // Chain Validation
    $routes->get('chain/validate', 'Api::validateChain');

    // Backups
    $routes->get('backups', 'Api::backups');

    // Whitelist (Protected)
    $routes->get('whitelist', 'Api::whitelist');
    $routes->post('whitelist', 'Api::addWhitelist');
    $routes->put('whitelist/(:num)/activate', 'Api::activateWhitelist/$1');
    $routes->put('whitelist/(:num)/deactivate', 'Api::deactivateWhitelist/$1');
    $routes->delete('whitelist/(:num)', 'Api::deleteWhitelist/$1');

    // Statistics
    $routes->get('stats', 'Api::stats');

    // Recovery
    $routes->post('recovery/(:num)', 'Api::recovery/$1');
    $routes->post('check-integrity', 'Api::checkIntegrity');
    $routes->post('auto-recovery', 'Api::autoRecovery');
    $routes->get('recovery/status', 'Api::recoveryStatus');
    $routes->post('recovery/countdown/reset', 'Api::recoveryCountdownReset');

    // Consensus & 2/3 Majority Recovery
    $routes->get('consensus/check', 'Api::consensusCheck');
    $routes->post('consensus/recover', 'Api::consensusRecover');
    $routes->get('consensus/health', 'Api::consensusHealth');
    $routes->get('consensus/dashboard', 'Api::consensusDashboard');
    $routes->get('consensus/alerts', 'Api::consensusAlerts');
    $routes->post('consensus/monitor', 'Api::consensusMonitor');
    $routes->post('consensus/rollback/(:num)', 'Api::consensusRollback/$1');

    // Integrity Check (Validasi Dua Lapis)
    $routes->get('integrity/check', 'Api::integrityCheck');
    $routes->post('integrity/run', 'Api::runIntegrityCheck');

    // Activity Logs
    $routes->get('activity-logs', 'Api::activityLogs');
});
