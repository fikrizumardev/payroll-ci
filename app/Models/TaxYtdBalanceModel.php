<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class TaxYtdBalanceModel extends Model
{
    protected $table         = 'tax_ytd_balances';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['employee_id', 'year', 'through_month', 'months', 'gross_income', 'pension', 'pph21', 'note'];

    protected array $casts = [
        'id'            => 'int',
        'employee_id'   => 'int',
        'year'          => 'int',
        'through_month' => 'int',
        'months'        => 'int',
        'gross_income'  => 'int',
        'pension'       => 'int',
        'pph21'         => 'int',
    ];

    protected $validationRules = [
        'employee_id'   => 'required|is_natural_no_zero',
        'year'          => 'required|is_natural_no_zero',
        'through_month' => 'required|is_natural_no_zero|less_than_equal_to[12]',
        'months'        => 'required|is_natural|less_than_equal_to[12]',
        'gross_income'  => 'required|is_natural',
        'pension'       => 'required|is_natural',
        'pph21'         => 'required|is_natural',
    ];
}
