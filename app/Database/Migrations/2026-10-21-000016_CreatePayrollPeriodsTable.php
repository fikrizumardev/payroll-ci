<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreatePayrollPeriodsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'             => $this->id(),
            'name'           => $this->string(50),
            'year'           => ['type' => 'SMALLINT', 'unsigned' => true],
            'month'          => ['type' => 'TINYINT', 'unsigned' => true],
            'start_date'     => ['type' => 'DATE'],
            'end_date'       => ['type' => 'DATE'],
            'pay_date'       => ['type' => 'DATE'],
            // draft | review | approved | paid | locked
            'status'         => $this->string(20, false, 'draft'),
            'workdays'       => ['type' => 'TINYINT', 'unsigned' => true, 'null' => true],
            'notes'          => ['type' => 'TEXT', 'null' => true],
            'rejection_note' => ['type' => 'TEXT', 'null' => true],
            'created_by'     => $this->foreignId(true),
            'generated_by'   => $this->foreignId(true),
            'generated_at'   => ['type' => 'DATETIME', 'null' => true],
            'submitted_by'   => $this->foreignId(true),
            'submitted_at'   => ['type' => 'DATETIME', 'null' => true],
            'approved_by'    => $this->foreignId(true),
            'approved_at'    => ['type' => 'DATETIME', 'null' => true],
            'paid_by'        => $this->foreignId(true),
            'paid_at'        => ['type' => 'DATETIME', 'null' => true],
            'locked_by'      => $this->foreignId(true),
            'locked_at'      => ['type' => 'DATETIME', 'null' => true],
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['year', 'month']);
        $this->forge->addKey('status');

        foreach (['created', 'generated', 'submitted', 'approved', 'paid', 'locked'] as $actor) {
            $this->forge->addForeignKey("{$actor}_by", 'users', 'id', 'CASCADE', 'SET NULL', "fk_payroll_periods_{$actor}_by");
        }

        $this->forge->createTable('payroll_periods', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('payroll_periods', true);
    }
}
