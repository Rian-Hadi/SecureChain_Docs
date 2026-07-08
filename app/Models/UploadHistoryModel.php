<?php

namespace App\Models;

use CodeIgniter\Model;

class UploadHistoryModel extends Model
{
    protected $DBGroup          = 'admindb';
    protected $table            = 'upload_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'user_id',
        'username',
        'nama_dokumen',
        'nomor_permohonan',
        'nomor_dokumen',
        'kategori_dokumen',
        'tanggal_dokumen',
        'tanggal_filing',
        'block_hash',
        'file_type',
        'file_size',
        'ip_address',
        'status',
        'keterangan',
        'uploaded_at',
    ];

    protected $useTimestamps = false;

    /**
     * Simpan riwayat upload baru
     */
    public function saveHistory(array $data): bool
    {
        return $this->insert($data) !== false;
    }

    /**
     * Ambil riwayat upload berdasarkan user_id (terbaru di atas)
     */
    public function getByUser(int $userId, ?string $tanggal = null, int $perPage = 10)
    {
        $query = $this->where('user_id', $userId);
        if ($tanggal) {
            $query->where('DATE(uploaded_at)', $tanggal);
        }
        return $query->orderBy('uploaded_at', 'DESC')
                     ->paginate($perPage);
    }

    /**
     * Ambil riwayat upload berdasarkan user_id dengan pencarian
     */
    public function searchByUser(int $userId, string $keyword, ?string $tanggal = null, int $perPage = 10)
    {
        $query = $this->where('user_id', $userId)
                      ->groupStart()
                          ->like('nama_dokumen', $keyword)
                          ->orLike('nomor_permohonan', $keyword)
                          ->orLike('nomor_dokumen', $keyword)
                      ->groupEnd();
                      
        if ($tanggal) {
            $query->where('DATE(uploaded_at)', $tanggal);
        }
        
        return $query->orderBy('uploaded_at', 'DESC')
                     ->paginate($perPage);
    }

    /**
     * Hitung total upload oleh user
     */
    public function countByUser(int $userId, ?string $tanggal = null): int
    {
        $query = $this->where('user_id', $userId);
        if ($tanggal) {
            $query->where('DATE(uploaded_at)', $tanggal);
        }
        return $query->countAllResults();
    }

    /**
     * Hitung upload sukses oleh user
     */
    public function countSuccessByUser(int $userId, ?string $tanggal = null): int
    {
        $query = $this->where('user_id', $userId)
                      ->where('status', 'success');
        if ($tanggal) {
            $query->where('DATE(uploaded_at)', $tanggal);
        }
        return $query->countAllResults(false);
    }

    /**
     * Hitung upload gagal oleh user
     */
    public function countFailedByUser(int $userId, ?string $tanggal = null): int
    {
        $query = $this->where('user_id', $userId)
                      ->where('status', 'failed');
        if ($tanggal) {
            $query->where('DATE(uploaded_at)', $tanggal);
        }
        return $query->countAllResults(false);
    }

    /**
     * Ambil semua riwayat upload untuk admin
     */
    public function getAllHistory(?string $tanggal = null, ?string $kategori = null, int $perPage = 10)
    {
        if ($tanggal) {
            $this->where('DATE(uploaded_at)', $tanggal);
        }
        
        if ($kategori) {
            $this->where('kategori_dokumen', $kategori);
        }
        
        return $this->orderBy('uploaded_at', 'DESC')
                    ->paginate($perPage);
    }

    /**
     * Ambil statistik harian berdasarkan kategori dokumen
     */
    public function getDailyStatistics(?string $tanggal = null): array
    {
        $builder = $this->builder();
        $builder->select('kategori_dokumen, COUNT(id) as total_upload, SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as total_success, SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as total_failed');
        $builder->groupBy('kategori_dokumen');
        
        if ($tanggal) {
            $builder->where('DATE(uploaded_at)', $tanggal);
        } else {
            $builder->where('DATE(uploaded_at)', date('Y-m-d'));
        }
        
        $result = $builder->get()->getResultArray();
        
        $stats = [];
        foreach ($result as $row) {
            $kategori = $row['kategori_dokumen'] ?: 'Tidak Diketahui';
            $stats[$kategori] = [
                'total' => (int)$row['total_upload'],
                'success' => (int)$row['total_success'],
                'failed' => (int)$row['total_failed']
            ];
        }
        
        return $stats;
    }
}
