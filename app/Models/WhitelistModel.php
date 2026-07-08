<?php

namespace App\Models;

use CodeIgniter\Model;

class WhitelistModel extends Model
{
    protected $DBGroup          = 'admindb';
    protected $table            = 'ip_whitelist';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields    = [
        'ip_address',
        'description',
        'is_active',
        'added_by'
    ];

    public function getActiveIPs(): array
    {
        return $this->where('is_active', 1)->findAll();
    }

    public function isIPWhitelisted(string $ipAddress): bool
    {
        $result = $this->where('ip_address', $ipAddress)
            ->where('is_active', 1)
            ->first();

        return $result !== null;
    }

    public function addIP(string $ipAddress, string $description = '', string $addedBy = 'admin'): bool
    {
        $existing = $this->where('ip_address', $ipAddress)->first();

        if ($existing) {
            return $this->update($existing['id'], [
                'is_active' => 1,
                'description' => $description,
                'added_by' => $addedBy
            ]);
        }

        return $this->insert([
            'ip_address' => $ipAddress,
            'description' => $description,
            'is_active' => 1,
            'added_by' => $addedBy
        ]);
    }

    public function deactivateIP(int $id): bool
    {
        return $this->update($id, ['is_active' => 0]);
    }

    public function activateIP(int $id): bool
    {
        return $this->update($id, ['is_active' => 1]);
    }

    public function removeIP(int $id): bool
    {
        return $this->delete($id);
    }

    public function getAllIPs(): array
    {
        return $this->orderBy('created_at', 'DESC')->findAll();
    }
}
