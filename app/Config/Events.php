<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

/*
 * --------------------------------------------------------------------
 * Auto Backup Events
 * --------------------------------------------------------------------
 * Event untuk membuat backup otomatis setiap kali ada perubahan data
 * pada blockchain (insert, update)
 */

// Event: Setelah insert data baru ke blockchain
Events::on('afterInsertBlock', static function (array $blockData): void {
    $backupModel = model(\App\Models\BackupModel::class);
    
    if ($backupModel->createBackup($blockData, 'auto')) {
        log_message('info', "[EVENT-BACKUP] Auto-backup berhasil dibuat untuk Nomor Permohonan: {$blockData['nomor_permohonan']}");
    } else {
        log_message('error', "[EVENT-BACKUP] Gagal membuat auto-backup untuk Nomor Permohonan: {$blockData['nomor_permohonan']}");
    }
});

// Event: Setelah update data di blockchain (untuk sinkronisasi backup)
Events::on('afterUpdateBlock', static function (array $blockData): void {
    $backupModel = model(\App\Models\BackupModel::class);
    
    // Cek apakah sudah ada backup untuk data ini
    $existingBackup = $backupModel->getBackupByIdentifier(
        $blockData['nomor_permohonan'],
        $blockData['tanggal_dokumen']
    );
    
    // Jika belum ada backup, buat baru (untuk kasus update manual)
    if (!$existingBackup) {
        if ($backupModel->createBackup($blockData, 'auto_sync')) {
            log_message('info', "[EVENT-SYNC] Backup sinkronisasi dibuat setelah update untuk Nomor Permohonan: {$blockData['nomor_permohonan']}");
        }
    } else {
        log_message('info', "[EVENT-SYNC] Backup sudah ada untuk Nomor Permohonan: {$blockData['nomor_permohonan']}, skip create");
    }
});
