<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class EmployeeComponentModel extends Model
{
    protected $table         = 'employee_components';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['employee_id', 'salary_component_id', 'amount'];

    protected array $casts = [
        'id'                  => 'int',
        'employee_id'         => 'int',
        'salary_component_id' => 'int',
        'amount'              => 'int',
    ];

    protected $validationRules = [
        'employee_id'         => 'required|is_natural_no_zero',
        'salary_component_id' => 'required|is_natural_no_zero',
        'amount'              => 'required|is_natural',
    ];

    /**
     * @return list<array<string, mixed>> komponen milik karyawan beserta info komponennya
     */
    public function forEmployee(int $employeeId): array
    {
        return $this->select('employee_components.*, salary_components.code, salary_components.name')
            ->select('salary_components.type, salary_components.calc_type, salary_components.taxable, salary_components.is_bpjs_base')
            ->join('salary_components', 'salary_components.id = employee_components.salary_component_id')
            ->where('employee_components.employee_id', $employeeId)
            ->orderBy('salary_components.sort_order')
            ->findAll();
    }
}
