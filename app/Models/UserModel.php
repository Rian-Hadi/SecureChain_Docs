<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $DBGroup = "admindb"; // Use admin database
    protected $table = "users";
    protected $primaryKey = "id";
    protected $useAutoIncrement = true;
    protected $returnType = "array";
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        "username",
        "password",
        "full_name",
        "role",
        "divisi",
        "is_active",
        "last_login",
        "last_login_ip",
    ];

    protected $useTimestamps = true;
    protected $dateFormat = "datetime";
    protected $createdField = "created_at";
    protected $updatedField = "updated_at";
    protected $deletedField = "deleted_at";

    protected $validationRules = [
        "username" =>
            "required|alpha_numeric|min_length[4]|max_length[100]|is_unique[admindb.users.username]",
        "password" => "required|min_length[6]",
        "full_name" => "required|min_length[3]",
        "role" => "required|in_list[admin,user]",
    ];
    protected $validationMessages = [
        "username" => [
            "required" => "Username wajib diisi",
            "alpha_numeric" => "Username hanya boleh berisi huruf dan angka",
            "min_length" => "Username minimal 4 karakter",
            "is_unique" => "Username sudah digunakan",
        ],
        "password" => [
            "required" => "Password wajib diisi",
            "min_length" => "Password minimal 6 karakter",
        ],
    ];

    /**
     * Rules khusus untuk operasi update (password opsional, is_unique skip ID sendiri)
     */
    public function getUpdateRules(int $id): array
    {
        return [
            "username" => "required|alpha_numeric|min_length[4]|max_length[100]|is_unique[admindb.users.username,id,{$id}]",
            "full_name" => "required|min_length[3]",
            "role" => "required|in_list[admin,user]",
            "divisi" => "required|in_list[Paten,Merek,Hak Cipta,Desain Industri,Admin]",
            "password" => "permit_empty|min_length[6]",
        ];
    }
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ["hashPassword"];
    protected $beforeUpdate = ["hashPassword"];

    protected function hashPassword(array $data)
    {
        if (isset($data["data"]["password"])) {
            $data["data"]["password"] = password_hash(
                $data["data"]["password"],
                PASSWORD_DEFAULT,
            );
        }
        return $data;
    }

    public function getUserByUsername($username)
    {
        return $this->where("username", $username)->first();
    }

    public function verifyCredentials($usernameOrEmail, $password)
    {
        $user = $this->where("username", $usernameOrEmail)
            ->orWhere("email", $usernameOrEmail)
            ->first();

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user["password"])) {
            return false;
        }

        return $user;
    }

    public function updateLastLogin($userId, $ipAddress)
    {
        return $this->update($userId, [
            "last_login" => date("Y-m-d H:i:s"),
            "last_login_ip" => $ipAddress,
        ]);
    }

    public function isActive($userId)
    {
        $user = $this->find($userId);
        return $user && $user["is_active"] == 1;
    }
}
