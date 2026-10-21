<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class OvertimeModel extends Model
{
    public const STATUSES = [
        'pending'  => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
    ];

    protected $table         = 'overtimes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['employee_id', 'date', 'start_time', 'end_time', 'hours', 'reason', 'status', 'approved_by', 'approved_at'];

    protected array $casts = [
        'id'          => 'int',
        'employee_id' => 'int',
        'hours'       => 'float',
        'approved_by' => '?int',
    ];

    protected $validationRules = [
        'employee_id' => 'required|is_natural_no_zero',
        'date'        => 'required|valid_date[Y-m-d]',
        'start_time'  => 'required|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]',
        'end_time'    => 'required|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]',
        'hours'       => 'required|decimal|greater_than[0]|less_than_equal_to[12]',
        'reason'      => 'permit_empty|max_length[255]',
        'status'      => 'required|in_list[pending,approved,rejected]',
    ];
}
