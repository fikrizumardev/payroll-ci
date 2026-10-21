<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Hasil payroll per karyawan per periode (header slip gaji).
 */
class CreatePayrollsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $days = ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0];

        $this->forge->addField([
            'id'                  => $this->id(),
            'period_id'           => $this->foreignId(),
            'employee_id'         => $this->foreignId(),
            // Salinan jabatan, departemen, PTKP & rekening saat payroll dihitung
            'snapshot'            => $this->json(),
            'workdays'            => $days,
            'active_workdays'     => $days,
            'present_days'        => $days,
            'late_days'           => $days,
            'sick_days'           => $days,
            'leave_days'          => $days,
            'permit_days'         => $days,
            'absent_days'         => $days,
            'overtime_hours'      => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => '0.00'],
            'bpjs_base'           => $this->money(),
            'total_earning'       => $this->money(),
            'bruto_pph'           => $this->money(),
            'total_deduction'     => $this->money(),
            'pph21'               => $this->money(),
            'ter_category'        => ['type' => 'CHAR', 'constraint' => 1, 'null' => true],
            'ter_rate'            => $this->percent(),
            'bpjs_company_total'  => $this->money(),
            'bpjs_employee_total' => $this->money(),
            'take_home_pay'       => $this->money(),
            'warnings'            => $this->json(),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['period_id', 'employee_id']);
        $this->forge->addKey('employee_id');
        $this->forge->addForeignKey('period_id', 'payroll_periods', 'id', 'CASCADE', 'CASCADE', 'fk_payrolls_period');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'RESTRICT', 'fk_payrolls_employee');
        $this->forge->createTable('payrolls', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('payrolls', true);
    }
}
