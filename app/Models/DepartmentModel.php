<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class DepartmentModel extends Model
{
    protected $table         = 'departments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['code', 'name', 'description', 'head_employee_id'];
    protected $useTimestamps = true;

    protected array $casts = [
        'id'               => 'int',
        'head_employee_id' => '?int',
    ];

    protected $validationRules = [
        'id'               => 'permit_empty|is_natural_no_zero',
        'code'             => ['label' => 'Kode', 'rules' => 'required|alpha_numeric|max_length[10]|is_unique[departments.code,id,{id}]'],
        'name'             => ['label' => 'Nama departemen', 'rules' => 'required|max_length[100]'],
        'description'      => ['label' => 'Deskripsi', 'rules' => 'permit_empty|max_length[255]'],
        'head_employee_id' => ['label' => 'Kepala departemen', 'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[employees.id]'],
    ];

    protected $validationMessages = [
        'code' => ['is_unique' => 'Kode departemen sudah dipakai.'],
    ];

    /**
     * Departemen beserta nama kepala departemen dan jumlah karyawan aktif.
     *
     * @return list<array<string, mixed>>
     */
    public function withSummary(): array
    {
        return $this->select('departments.*, head.full_name AS head_name')
            ->select('(SELECT COUNT(*) FROM employees e WHERE e.department_id = departments.id'
                . " AND e.status = 'active' AND e.deleted_at IS NULL) AS employee_count", false)
            ->join('employees head', 'head.id = departments.head_employee_id', 'left')
            ->orderBy('departments.name')
            ->findAll();
    }

    /**
     * @return array<int, string> id => nama, untuk dropdown
     */
    public function options(): array
    {
        return array_column($this->orderBy('name')->findAll(), 'name', 'id');
    }
}
