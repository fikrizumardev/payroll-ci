<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class LoanInstallmentModel extends Model
{
    protected $table         = 'loan_installments';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['loan_id', 'payroll_id', 'installment_no', 'period', 'amount', 'paid_at'];

    protected array $casts = [
        'loan_id'        => 'int',
        'payroll_id'     => '?int',
        'installment_no' => 'int',
        'amount'         => 'int',
    ];
}
