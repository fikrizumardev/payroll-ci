<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AttendanceModel extends Model
{
    public const STATUS_PRESENT = 'present';
    public const STATUS_LATE    = 'late';
    public const STATUS_SICK    = 'sick';
    public const STATUS_LEAVE   = 'leave';
    public const STATUS_PERMIT  = 'permit';
    public const STATUS_ABSENT  = 'absent';
    public const STATUS_HOLIDAY = 'holiday';

    public const STATUSES = [
        self::STATUS_PRESENT => 'Hadir',
        self::STATUS_LATE    => 'Terlambat',
        self::STATUS_SICK    => 'Sakit',
        self::STATUS_LEAVE   => 'Cuti',
        self::STATUS_PERMIT  => 'Izin',
        self::STATUS_ABSENT  => 'Alpha',
        self::STATUS_HOLIDAY => 'Libur',
    ];

    /** Status yang dihitung sebagai hari hadir (dapat tunjangan harian). */
    public const PRESENT_STATUSES = [self::STATUS_PRESENT, self::STATUS_LATE];

    protected $table         = 'attendances';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['employee_id', 'date', 'status', 'check_in', 'check_out', 'late_minutes', 'note'];

    protected array $casts = [
        'id'           => 'int',
        'employee_id'  => 'int',
        'late_minutes' => 'int',
    ];

    protected $validationRules = [
        'employee_id'  => 'required|is_natural_no_zero',
        'date'         => 'required|valid_date[Y-m-d]',
        'status'       => 'required|in_list[present,late,sick,leave,permit,absent,holiday]',
        'check_in'     => 'permit_empty|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]',
        'check_out'    => 'permit_empty|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]',
        'late_minutes' => 'permit_empty|is_natural',
        'note'         => 'permit_empty|max_length[255]',
    ];

    /**
     * Rekap jumlah hari per status untuk satu karyawan di rentang tanggal.
     *
     * @return array<string, int>
     */
    public function summary(int $employeeId, string $start, string $end): array
    {
        $rows = $this->select('status, COUNT(*) AS total')
            ->where('employee_id', $employeeId)
            ->where('date >=', $start)
            ->where('date <=', $end)
            ->groupBy('status')
            ->findAll();

        $summary = array_fill_keys(array_keys(self::STATUSES), 0);

        foreach ($rows as $row) {
            $summary[$row['status']] = (int) $row['total'];
        }

        return $summary;
    }
}
