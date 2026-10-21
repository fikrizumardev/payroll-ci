<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PayrollItemModel extends Model
{
    protected $table         = 'payroll_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['payroll_id', 'salary_component_id', 'code', 'name', 'type', 'amount', 'taxable', 'meta', 'sort_order'];

    protected array $casts = [
        'id'                  => 'int',
        'payroll_id'          => 'int',
        'salary_component_id' => '?int',
        'amount'              => 'int',
        'taxable'             => 'bool',
        'meta'                => '?json-array',
        'sort_order'          => 'int',
    ];

    /**
     * @return array<string, list<array<string, mixed>>> item dikelompokkan per tipe
     */
    public function groupedForPayroll(int $payrollId): array
    {
        $groups = array_fill_keys(array_keys(SalaryComponentModel::TYPES), []);

        foreach ($this->where('payroll_id', $payrollId)->orderBy('sort_order')->findAll() as $item) {
            $groups[$item['type']][] = $item;
        }

        return $groups;
    }
}
