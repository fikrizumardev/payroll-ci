<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class OvertimeModel extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING  => 'Menunggu',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    /** Batas jam lembur per pengajuan (per hari). */
    public const MAX_HOURS = 12;

    protected $table         = 'overtimes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['employee_id', 'date', 'start_time', 'end_time', 'hours', 'reason', 'status', 'approved_by', 'approved_at', 'review_note'];

    protected array $casts = [
        'id'          => 'int',
        'employee_id' => 'int',
        'hours'       => 'float',
        'approved_by' => '?int',
    ];

    protected $validationRules = [
        'employee_id' => ['label' => 'Karyawan', 'rules' => 'required|is_natural_no_zero'],
        'date'        => ['label' => 'Tanggal', 'rules' => 'required|valid_date[Y-m-d]'],
        'start_time'  => ['label' => 'Jam mulai', 'rules' => 'required|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]'],
        'end_time'    => ['label' => 'Jam selesai', 'rules' => 'required|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]'],
        'hours'       => ['label' => 'Durasi', 'rules' => 'required|decimal|greater_than[0]|less_than_equal_to[12]'],
        'reason'      => ['label' => 'Alasan', 'rules' => 'required|min_length[5]|max_length[255]'],
        'status'      => ['label' => 'Status', 'rules' => 'required|in_list[pending,approved,rejected]'],
        'review_note' => ['label' => 'Catatan', 'rules' => 'permit_empty|max_length[255]'],
    ];

    protected $validationMessages = [
        'hours' => [
            'greater_than'       => 'Jam selesai harus setelah jam mulai.',
            'less_than_equal_to' => 'Durasi lembur maksimal 12 jam per hari.',
        ],
    ];

    /**
     * Durasi lembur dalam jam (2 desimal). Jam selesai ≤ jam mulai dianggap melewati tengah malam.
     */
    public static function hoursBetween(string $start, string $end): float
    {
        $from = strtotime('2000-01-01 ' . $start);
        $to   = strtotime('2000-01-01 ' . $end);

        if ($from === false || $to === false) {
            return 0.0;
        }

        if ($to <= $from) {
            $to += 86_400;
        }

        return round(($to - $from) / 3600, 2);
    }

    /**
     * Query lembur lengkap dengan data karyawan, departemen, dan penyetuju.
     */
    public function withRelations(): static
    {
        return $this->select('overtimes.*, employees.nip, employees.full_name, employees.department_id, employees.basic_salary')
            ->select('departments.name AS department_name, positions.name AS position_name, positions.overtime_eligible')
            ->select('approver.name AS approver_name')
            ->join('employees', 'employees.id = overtimes.employee_id')
            ->join('departments', 'departments.id = employees.department_id', 'left')
            ->join('positions', 'positions.id = employees.position_id', 'left')
            ->join('users approver', 'approver.id = overtimes.approved_by', 'left');
    }
}
