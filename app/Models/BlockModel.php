<?php

namespace App\Models;

use CodeIgniter\Model;

class BlockModel extends Model
{
    protected $DBGroup          = 'userdb';
    protected $table            = 'blockchain';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true; // Pastikan ini true jika ID auto increment
    protected $returnType       = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'timestamp';
    protected $updatedField  = ''; // Kosongkan jika tidak ada kolom updated_at

    protected $allowedFields = [
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'kategori_dokumen',
        'dokumen_base64',
        'ip_address',
        'block_hash',
        'previous_hash'
    ];

    public function getLatestBlock(): ?array
    {
        return $this->orderBy('id', 'DESC')->first();
    }

    public function getAllBlocks(): array
    {
        return $this->orderBy('id', 'ASC')->findAll();
    }

    /**
     * FIX: Get latest block by timestamp (not by ID)
     * This ensures we get the block with the most recent timestamp, not just the highest ID
     */
    public function getLatestBlockByTimestamp(): ?array
    {
        return $this->orderBy('timestamp', 'DESC')->first();
    }

    public function getBlockByHash(string $blockHash): ?array
    {
        return $this->where('block_hash', $blockHash)->first();
    }

    /**
     * Memperbaiki pencarian agar tidak menyebabkan Syntax Error #1064
     * dan tetap aman dari SQL Injection.
     */
    public function search(string $keyword, ?string $kategori = null)
    {
        // Gunakan groupStart() dan groupEnd() agar logika OR terkurung
        // dan tidak mengacaukan query lain (seperti soft deletes jika ada)
        $query = $this->select('id, nama_dokumen, nomor_permohonan, nomor_dokumen, tanggal_dokumen, tanggal_filing, kategori_dokumen, block_hash, previous_hash, timestamp')
                    ->groupStart()
                        ->like('nomor_permohonan', $keyword)
                        ->orLike('nomor_dokumen', $keyword)
                        ->orLike('nama_dokumen', $keyword)
                    ->groupEnd();

        // Filter by kategori if provided (and not Admin)
        if ($kategori && $kategori !== 'Admin') {
            $query->where('kategori_dokumen', $kategori);
        }

        return $query->orderBy('id', 'DESC')->paginate(10);
    }

    /**
     * Get documents filtered by user division
     */
    public function getByDivision(?string $kategori = null)
    {
        $fields = 'id, nama_dokumen, nomor_permohonan, nomor_dokumen, tanggal_dokumen, tanggal_filing, kategori_dokumen, block_hash, previous_hash, timestamp';
        
        // If user is Admin, show all documents
        if ($kategori === 'Admin' || $kategori === null) {
            return $this->select($fields)->orderBy('id', 'DESC')->paginate(10);
        }

        // Filter by user's division
        return $this->select($fields)
                    ->where('kategori_dokumen', $kategori)
                    ->orderBy('id', 'DESC')
                    ->paginate(10);
    }
}
