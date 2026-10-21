<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PayrollAdjustmentModel extends Model
{
    protected $table         = 'payroll_adjustments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['period_id', 'employee_id', 'name', 'type', 'amount', 'taxable', 'note', 'created_by'];

    protected array $casts = [
        'period_id'   => 'int',
        'employee_id' => 'int',
        'amount'      => 'int',
        'taxable'     => 'bool',
        'created_by'  => '?int',
    ];

    protected $validationRules = [
        'period_id'   => 'required|is_natural_no_zero',
        'employee_id' => 'required|is_natural_no_zero',
        'name'        => 'required|max_length[100]',
        'type'        => 'required|in_list[earning,deduction]',
        'amount'      => 'required|is_natural_no_zero',
        'note'        => 'permit_empty|max_length[255]',
    ];
}
