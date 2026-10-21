<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Token "Ingat saya" dengan pola selector + validator.
 * Yang disimpan hanya hash SHA-256 dari validator.
 */
class CreateUserTokensTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'           => $this->id(),
            'user_id'      => $this->foreignId(),
            'selector'     => $this->string(32),
            'token_hash'   => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at'   => ['type' => 'DATETIME'],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'user_agent'   => $this->string(255, true),
            'ip_address'   => $this->string(45, true),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('selector');
        $this->forge->addKey('user_id');
        $this->forge->addKey('expires_at');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE', 'fk_user_tokens_user');
        $this->forge->createTable('user_tokens', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('user_tokens', true);
    }
}
