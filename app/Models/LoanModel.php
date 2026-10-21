<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class LoanModel extends Model
{
    public const STATUSES = [
        'pending'  => 'Menunggu Persetujuan',
        'approved' => 'Disetujui',
        'active'   => 'Berjalan',
        'paid_off' => 'Lunas',
        'rejected' => 'Ditolak',
    ];

    protected $table         = 'loans';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'loan_no', 'employee_id', 'amount', 'tenor_months', 'installment', 'start_period',
        'remaining', 'status', 'purpose', 'notes', 'approved_by', 'approved_at',
    ];

    protected array $casts = [
        'id'           => 'int',
        'employee_id'  => 'int',
        'amount'       => 'int',
        'tenor_months' => 'int',
        'installment'  => 'int',
        'remaining'    => 'int',
        'approved_by'  => '?int',
    ];

    protected $validationRules = [
        'id'           => 'permit_empty|is_natural_no_zero',
        'loan_no'      => 'required|max_length[20]|is_unique[loans.loan_no,id,{id}]',
        'employee_id'  => 'required|is_natural_no_zero',
        'amount'       => 'required|is_natural_no_zero',
        'tenor_months' => 'required|is_natural_no_zero|less_than_equal_to[36]',
        'installment'  => 'required|is_natural_no_zero',
        'start_period' => 'required|regex_match[/^\d{4}-(0[1-9]|1[0-2])$/]',
        'remaining'    => 'required|is_natural',
        'status'       => 'required|in_list[pending,approved,active,paid_off,rejected]',
    ];

    public function nextLoanNo(int $year): string
    {
        $prefix = sprintf('PJM-%d-', $year);
        $last   = $this->like('loan_no', $prefix, 'after')->selectMax('loan_no')->first();
        $seq    = $last['loan_no'] === null ? 1 : ((int) substr($last['loan_no'], -4)) + 1;

        return $prefix . sprintf('%04d', $seq);
    }
}
