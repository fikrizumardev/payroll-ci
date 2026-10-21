<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class LoanModel extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_PAID_OFF = 'paid_off';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING  => 'Menunggu Persetujuan',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_ACTIVE   => 'Berjalan',
        self::STATUS_PAID_OFF => 'Lunas',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    /** Status pinjaman yang masih berjalan (dipotong saat generate payroll). */
    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_ACTIVE];

    /** Batas cicilan per bulan terhadap gaji pokok. */
    public const MAX_INSTALLMENT_RATIO = 0.5;

    protected $table         = 'loans';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'loan_no', 'employee_id', 'amount', 'tenor_months', 'installment', 'start_period',
        'remaining', 'status', 'purpose', 'notes', 'approved_by', 'approved_at', 'review_note',
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
        'loan_no'      => ['label' => 'No. pinjaman', 'rules' => 'required|max_length[20]|is_unique[loans.loan_no,id,{id}]'],
        'employee_id'  => ['label' => 'Karyawan', 'rules' => 'required|is_natural_no_zero'],
        'amount'       => ['label' => 'Nominal pinjaman', 'rules' => 'required|is_natural_no_zero|greater_than_equal_to[100000]|less_than_equal_to[1000000000]'],
        'tenor_months' => ['label' => 'Tenor', 'rules' => 'required|is_natural_no_zero|less_than_equal_to[36]'],
        'installment'  => ['label' => 'Cicilan', 'rules' => 'required|is_natural_no_zero'],
        'start_period' => ['label' => 'Mulai potong', 'rules' => 'required|regex_match[/^\d{4}-(0[1-9]|1[0-2])$/]'],
        'remaining'    => ['label' => 'Sisa pinjaman', 'rules' => 'required|is_natural'],
        'status'       => ['label' => 'Status', 'rules' => 'required|in_list[pending,approved,active,paid_off,rejected]'],
        'purpose'      => ['label' => 'Keperluan', 'rules' => 'required|min_length[3]|max_length[255]'],
        'notes'        => ['label' => 'Catatan', 'rules' => 'permit_empty|max_length[255]'],
        'review_note'  => ['label' => 'Catatan persetujuan', 'rules' => 'permit_empty|max_length[255]'],
    ];

    protected $validationMessages = [
        'amount' => ['greater_than_equal_to' => 'Nominal pinjaman minimal Rp 100.000.'],
    ];

    public function nextLoanNo(int $year): string
    {
        $prefix = sprintf('PJM-%d-', $year);
        $last   = $this->like('loan_no', $prefix, 'after')->selectMax('loan_no')->first();
        $seq    = $last['loan_no'] === null ? 1 : ((int) substr($last['loan_no'], -4)) + 1;

        return $prefix . sprintf('%04d', $seq);
    }

    /**
     * Cicilan per bulan: dibulatkan ke atas agar pinjaman lunas tepat di akhir tenor
     * (cicilan terakhir otomatis lebih kecil karena dibatasi sisa pinjaman).
     */
    public static function installmentFor(int $amount, int $tenor): int
    {
        return $tenor > 0 ? intdiv($amount + $tenor - 1, $tenor) : $amount;
    }

    /**
     * Query pinjaman lengkap dengan data karyawan & penyetuju.
     */
    public function withRelations(): static
    {
        return $this->select('loans.*, employees.nip, employees.full_name, employees.basic_salary, employees.status AS employee_status')
            ->select('departments.name AS department_name, positions.name AS position_name, approver.name AS approver_name')
            ->join('employees', 'employees.id = loans.employee_id')
            ->join('departments', 'departments.id = employees.department_id', 'left')
            ->join('positions', 'positions.id = employees.position_id', 'left')
            ->join('users approver', 'approver.id = loans.approved_by', 'left');
    }
}
