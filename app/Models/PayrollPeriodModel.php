<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class PayrollPeriodModel extends Model
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_REVIEW   = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID     = 'paid';
    public const STATUS_LOCKED   = 'locked';

    public const STATUSES = [
        self::STATUS_DRAFT    => 'Draft',
        self::STATUS_REVIEW   => 'Review',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_PAID     => 'Dibayar',
        self::STATUS_LOCKED   => 'Terkunci',
    ];

    protected $table         = 'payroll_periods';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'name', 'year', 'month', 'start_date', 'end_date', 'pay_date', 'status', 'workdays',
        'notes', 'rejection_note', 'created_by', 'generated_by', 'generated_at', 'submitted_by',
        'submitted_at', 'approved_by', 'approved_at', 'paid_by', 'paid_at', 'locked_by', 'locked_at',
    ];

    protected array $casts = [
        'year'     => 'int',
        'month'    => 'int',
        'workdays' => '?int',
    ];

    protected $validationRules = [
        'id'         => 'permit_empty|is_natural_no_zero',
        'name'       => 'required|max_length[50]',
        'year'       => 'required|is_natural_no_zero|greater_than[2000]|less_than[2100]',
        'month'      => 'required|is_natural_no_zero|less_than_equal_to[12]',
        'start_date' => 'required|valid_date[Y-m-d]',
        'end_date'   => 'required|valid_date[Y-m-d]',
        'pay_date'   => 'required|valid_date[Y-m-d]',
        'status'     => 'required|in_list[draft,review,approved,paid,locked]',
    ];

    public function isEditable(array $period): bool
    {
        return $period['status'] === self::STATUS_DRAFT;
    }
}
