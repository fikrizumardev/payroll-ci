<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PayrollModel extends Model
{
    protected $table         = 'payrolls';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'period_id', 'employee_id', 'snapshot', 'workdays', 'active_workdays', 'present_days',
        'late_days', 'sick_days', 'leave_days', 'permit_days', 'absent_days', 'overtime_hours',
        'bpjs_base', 'total_earning', 'bruto_pph', 'total_deduction', 'pph21', 'ter_category',
        'ter_rate', 'bpjs_company_total', 'bpjs_employee_total', 'take_home_pay', 'warnings',
    ];

    protected array $casts = [
        'id'                  => 'int',
        'period_id'           => 'int',
        'employee_id'         => 'int',
        'snapshot'            => '?json-array',
        'warnings'            => '?json-array',
        'workdays'            => 'int',
        'active_workdays'     => 'int',
        'present_days'        => 'int',
        'late_days'           => 'int',
        'sick_days'           => 'int',
        'leave_days'          => 'int',
        'permit_days'         => 'int',
        'absent_days'         => 'int',
        'overtime_hours'      => 'float',
        'bpjs_base'           => 'int',
        'total_earning'       => 'int',
        'bruto_pph'           => 'int',
        'total_deduction'     => 'int',
        'pph21'               => 'int',
        'ter_rate'            => '?float',
        'bpjs_company_total'  => 'int',
        'bpjs_employee_total' => 'int',
        'take_home_pay'       => 'int',
    ];

    /**
     * Query builder slip gaji beserta data karyawan & periode.
     */
    public function withEmployee(): static
    {
        return $this->select('payrolls.*, employees.nip, employees.full_name, employees.department_id')
            ->select('payroll_periods.name AS period_name, payroll_periods.status AS period_status')
            ->join('employees', 'employees.id = payrolls.employee_id')
            ->join('payroll_periods', 'payroll_periods.id = payrolls.period_id');
    }
}
