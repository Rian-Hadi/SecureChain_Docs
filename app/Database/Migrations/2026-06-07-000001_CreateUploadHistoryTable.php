<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUploadHistoryTable extends Migration
{
    protected $DBGroup = 'admindb';

    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'nama_dokumen' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'nomor_permohonan' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'nomor_dokumen' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'kategori_dokumen' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'Paten',
            ],
            'tanggal_dokumen' => [
                'type' => 'DATE',
            ],
            'tanggal_filing' => [
                'type' => 'DATE',
            ],
            'block_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'file_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],
            'file_size' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Ukuran file dalam bytes',
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['success', 'failed'],
                'default'    => 'success',
            ],
            'keterangan' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'uploaded_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('block_hash');
        $this->forge->addKey('uploaded_at');

        $this->forge->createTable('upload_history', true);
    }

    public function down()
    {
        $this->forge->dropTable('upload_history', true);
    }
}
