<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PositionModel extends Model
{
    protected $table         = 'positions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'department_id', 'code', 'name', 'grade', 'base_salary',
        'position_allowance', 'communication_allowance', 'overtime_eligible',
    ];
    protected $useTimestamps = true;

    protected array $casts = [
        'id'                      => 'int',
        'grade'                   => '?int',
        'base_salary'             => 'int',
        'position_allowance'      => 'int',
        'communication_allowance' => 'int',
        'overtime_eligible'       => 'bool',
    ];

    protected $validationRules = [
        'id'                      => 'permit_empty|is_natural_no_zero',
        'department_id'           => ['label' => 'Departemen', 'rules' => 'required|is_natural_no_zero|is_not_unique[departments.id]'],
        'code'                    => ['label' => 'Kode jabatan', 'rules' => 'required|max_length[10]|regex_match[/^[A-Z0-9-]+$/]|is_unique[positions.code,id,{id}]'],
        'name'                    => ['label' => 'Nama jabatan', 'rules' => 'required|max_length[100]'],
        'grade'                   => ['label' => 'Grade', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[20]'],
        'base_salary'             => ['label' => 'Gaji pokok standar', 'rules' => 'required|is_natural'],
        'position_allowance'      => ['label' => 'Tunjangan jabatan', 'rules' => 'permit_empty|is_natural'],
        'communication_allowance' => ['label' => 'Tunjangan komunikasi', 'rules' => 'permit_empty|is_natural'],
        'overtime_eligible'       => ['label' => 'Berhak lembur', 'rules' => 'permit_empty|in_list[0,1]'],
    ];

    protected $validationMessages = [
        'code' => ['is_unique' => 'Kode jabatan sudah dipakai.'],
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function withDepartment(): array
    {
        return $this->select('positions.*, departments.name AS department_name, departments.code AS department_code')
            ->join('departments', 'departments.id = positions.department_id')
            ->orderBy('departments.name')
            ->orderBy('positions.grade', 'DESC')
            ->findAll();
    }
}
