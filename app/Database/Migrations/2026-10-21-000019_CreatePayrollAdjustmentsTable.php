<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Penyesuaian manual per periode: bonus, THR, koreksi.
 */
class CreatePayrollAdjustmentsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'          => $this->id(),
            'period_id'   => $this->foreignId(),
            'employee_id' => $this->foreignId(),
            'name'        => $this->string(100),
            'type'        => $this->string(20), // earning | deduction
            'amount'      => $this->money(),
            'taxable'     => $this->flag(true),
            'note'        => $this->string(255, true),
            'created_by'  => $this->foreignId(true),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['period_id', 'employee_id']);
        $this->forge->addKey('employee_id');
        $this->forge->addForeignKey('period_id', 'payroll_periods', 'id', 'CASCADE', 'CASCADE', 'fk_payroll_adjustments_period');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE', 'fk_payroll_adjustments_employee');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'SET NULL', 'fk_payroll_adjustments_creator');
        $this->forge->createTable('payroll_adjustments', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('payroll_adjustments', true);
    }
}
