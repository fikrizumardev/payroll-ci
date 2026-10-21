<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => $this->foreignId(true),
            'username'    => $this->string(30, true), // salinan, tetap terbaca walau user dihapus
            'action'      => $this->string(50),       // mis. auth.login, payroll.approve
            'entity'      => $this->string(50, true),
            'entity_id'   => $this->string(50, true),
            'description' => $this->string(255, true),
            'before'      => $this->json(),
            'after'       => $this->json(),
            'ip_address'  => $this->string(45, true),
            'user_agent'  => $this->string(255, true),
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addKey('action');
        $this->forge->addKey(['entity', 'entity_id']);
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL', 'fk_audit_logs_user');
        $this->forge->createTable('audit_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
