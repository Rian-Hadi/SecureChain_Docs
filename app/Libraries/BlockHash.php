<?php

namespace App\Libraries;

/**
 * Satu sumber kebenaran untuk perhitungan block_hash dan field sinkronisasi.
 */
class BlockHash
{
    /** Field yang di-concatenate untuk SHA-256 block_hash (format terbaru). */
    public const HASH_FIELDS = [
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'kategori_dokumen',
        'dokumen_base64',
    ];

    /** Format hash sebelum penambahan kategori_dokumen. */
    public const LEGACY_HASH_FIELDS = [
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'dokumen_base64',
    ];

    /** Field yang disinkronkan ke ketiga database. */
    public const SYNC_FIELDS = [
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'kategori_dokumen',
        'dokumen_base64',
        'ip_address',
        'block_hash',
        'previous_hash',
        'timestamp',
    ];

    /** Field payload untuk voting konsensus 2/3 (tanpa block_hash). */
    public const CONSENSUS_PAYLOAD_FIELDS = [
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'kategori_dokumen',
        'dokumen_base64',
    ];

    /** Field untuk membandingkan konsistensi antar database. */
    public const CONSENSUS_COMPARE_FIELDS = [
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'kategori_dokumen',
        'block_hash',
        'previous_hash',
    ];

    /** Field yang boleh di-update/insert saat recovery konsensus. */
    public const RECOVERABLE_FIELDS = [
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'kategori_dokumen',
        'dokumen_base64',
        'ip_address',
        'block_hash',
        'previous_hash',
        'timestamp',
    ];

    public static function calculate(array $data): string
    {
        return self::hashFromFields($data, self::HASH_FIELDS);
    }

    public static function calculateLegacy(array $data): string
    {
        return self::hashFromFields($data, self::LEGACY_HASH_FIELDS);
    }

    public static function calculatePayloadChecksum(array $data): string
    {
        return self::hashFromFields($data, self::CONSENSUS_PAYLOAD_FIELDS);
    }

    /**
     * @return 'current'|'legacy'|null
     */
    public static function getHashMatchType(array $record): ?string
    {
        $stored = (string) ($record['block_hash'] ?? '');
        if ($stored === '') {
            return null;
        }

        if ($stored === self::calculate($record)) {
            return 'current';
        }

        if ($stored === self::calculateLegacy($record)) {
            return 'legacy';
        }

        return null;
    }

    public static function isStoredHashValid(array $record): bool
    {
        return self::getHashMatchType($record) !== null;
    }

    public static function needsHashMigration(array $record): bool
    {
        return self::getHashMatchType($record) === 'legacy';
    }

    public static function buildCanonicalRecord(array $record): array
    {
        $record['kategori_dokumen'] = $record['kategori_dokumen'] ?? 'Paten';
        $record['block_hash'] = self::calculate($record);

        return $record;
    }

    public static function extractSyncData(array $block): array
    {
        $sync = [];
        foreach (self::SYNC_FIELDS as $field) {
            if (array_key_exists($field, $block)) {
                $sync[$field] = $block[$field];
            }
        }

        return $sync;
    }

    public static function extractRecoverableData(array $block): array
    {
        $data = [];
        foreach (self::RECOVERABLE_FIELDS as $field) {
            if (array_key_exists($field, $block)) {
                $data[$field] = $block[$field];
            }
        }

        return $data;
    }

    private static function hashFromFields(array $data, array $fields): string
    {
        $concat = '';
        foreach ($fields as $field) {
            $concat .= (string) ($data[$field] ?? '');
        }

        return hash('sha256', $concat);
    }
}
