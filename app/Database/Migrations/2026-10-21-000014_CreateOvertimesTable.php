<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateOvertimesTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'          => $this->id(),
            'employee_id' => $this->foreignId(),
            'date'        => ['type' => 'DATE'],
            'start_time'  => ['type' => 'TIME'],
            'end_time'    => ['type' => 'TIME'],
            'hours'       => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'],
            'reason'      => $this->string(255, true),
            'status'      => $this->string(20, false, 'pending'), // pending | approved | rejected
            'approved_by' => $this->foreignId(true),
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['employee_id', 'date']);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE', 'fk_overtimes_employee');
        $this->forge->addForeignKey('approved_by', 'users', 'id', 'CASCADE', 'SET NULL', 'fk_overtimes_approver');
        $this->forge->createTable('overtimes', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('overtimes', true);
    }
}
