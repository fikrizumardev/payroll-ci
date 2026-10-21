<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Autentikasi memakai username + password. Sengaja TIDAK ada kolom email.
 */
class CreateUsersTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'                   => $this->id(),
            'username'             => $this->string(30),
            'password_hash'        => $this->string(255),
            'name'                 => $this->string(150),
            'role'                 => $this->string(20, false, 'employee'), // superadmin | hr | finance | employee
            'employee_id'          => $this->foreignId(true),
            'is_active'            => $this->flag(true),
            'must_change_password' => $this->flag(),
            'failed_attempts'      => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'locked_until'         => ['type' => 'DATETIME', 'null' => true],
            'last_login_at'        => ['type' => 'DATETIME', 'null' => true],
            'last_login_ip'        => $this->string(45, true),
            'password_changed_at'  => ['type' => 'DATETIME', 'null' => true],
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('username');
        $this->forge->addUniqueKey('employee_id');
        $this->forge->addKey('role');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'SET NULL', 'fk_users_employee');
        $this->forge->createTable('users', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
