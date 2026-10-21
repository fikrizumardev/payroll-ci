<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\AttendanceModel;
use App\Services\PayrollPeriodGuard;
use App\Services\SettingService;
use CodeIgniter\Database\BaseConnection;

/**
 * Aturan & penyimpanan kehadiran harian. Dipakai oleh input harian dan import CSV
 * agar validasi, perhitungan menit terlambat, dan guard periode selalu sama.
 */
class AttendanceService
{
    /** Status yang boleh dicatat untuk tanggal yang belum terjadi (rencana cuti/izin). */
    public const FUTURE_STATUSES = [
        AttendanceModel::STATUS_SICK,
        AttendanceModel::STATUS_LEAVE,
        AttendanceModel::STATUS_PERMIT,
        AttendanceModel::STATUS_HOLIDAY,
    ];

    /** Kolom yang dibandingkan saat upsert untuk menentukan ada perubahan atau tidak. */
    private const COMPARED = ['status', 'check_in', 'check_out', 'late_minutes', 'note'];

    private readonly string $workStart;
    private readonly int $tolerance;

    public function __construct(
        private readonly BaseConnection $db,
        SettingService $settings,
        private readonly PayrollPeriodGuard $guard,
    ) {
        $this->workStart = self::parseTime((string) $settings->get('attendance.work_start', '08:00')) ?: '08:00:00';
        $this->tolerance = $settings->int('attendance.late_tolerance_minutes', 15);
    }

    public static function make(): self
    {
        return new self(db_connect(), service('appSettings'), PayrollPeriodGuard::make());
    }

    public function guard(): PayrollPeriodGuard
    {
        return $this->guard;
    }

    public function workStart(): string
    {
        return substr($this->workStart, 0, 5);
    }

    public function tolerance(): int
    {
        return $this->tolerance;
    }

    /**
     * "8:05", "08.05", "08:05:00" → "08:05:00". String kosong → null, format salah → false.
     */
    public static function parseTime(?string $value): string|false|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?$/', $value, $m) || (int) $m[1] > 23 || (int) $m[2] > 59 || (int) ($m[3] ?? 0) > 59) {
            return false;
        }

        return sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3] ?? 0);
    }

    /**
     * Normalisasi & validasi satu catatan kehadiran.
     *
     * - Jam masuk/pulang hanya disimpan untuk status Hadir/Terlambat.
     * - Status Hadir dengan jam masuk melewati toleransi otomatis dicatat Terlambat.
     * - Menit terlambat dihitung dari jam masuk kerja (setting `attendance.work_start`).
     * - Tanggal yang belum terjadi hanya boleh berstatus sakit/cuti/izin/libur.
     *
     * @param array{status?: mixed, check_in?: mixed, check_out?: mixed, note?: mixed} $input
     *
     * @return array{data: array<string, mixed>|null, errors: array<string, string>}
     */
    public function normalize(int $employeeId, string $date, array $input, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $errors  = [];
        $status  = trim((string) ($input['status'] ?? ''));
        $note    = trim(preg_replace('/\s+/', ' ', (string) ($input['note'] ?? '')));
        $checkIn = self::parseTime((string) ($input['check_in'] ?? ''));
        $checkOut = self::parseTime((string) ($input['check_out'] ?? ''));

        if (! array_key_exists($status, AttendanceModel::STATUSES)) {
            $errors['status'] = 'Status kehadiran tidak valid.';
        }

        if ($checkIn === false) {
            $errors['check_in'] = 'Format jam masuk harus JJ:MM.';
        }

        if ($checkOut === false) {
            $errors['check_out'] = 'Format jam pulang harus JJ:MM.';
        }

        if (mb_strlen($note) > 255) {
            $errors['note'] = 'Keterangan maksimal 255 karakter.';
        }

        if (($guardError = $this->guard->errorFor($date)) !== null) {
            $errors['date'] = $guardError;
        } elseif ($date > $today && isset(AttendanceModel::STATUSES[$status]) && ! in_array($status, self::FUTURE_STATUSES, true)) {
            $errors['status'] = 'Tanggal belum terjadi: hanya sakit, cuti, izin, atau libur yang boleh dicatat lebih awal.';
        }

        $clocked = in_array($status, AttendanceModel::PRESENT_STATUSES, true);

        if (! $clocked) {
            $checkIn  = null;
            $checkOut = null;
        }

        if (is_string($checkIn) && is_string($checkOut) && $checkOut <= $checkIn) {
            $errors['check_out'] = 'Jam pulang harus setelah jam masuk.';
        }

        if ($errors !== []) {
            return ['data' => null, 'errors' => $errors];
        }

        $lateMinutes = is_string($checkIn) ? max(0, intdiv(strtotime('2000-01-01 ' . $checkIn) - strtotime('2000-01-01 ' . $this->workStart), 60)) : 0;

        if ($status === AttendanceModel::STATUS_PRESENT && $lateMinutes > $this->tolerance) {
            $status = AttendanceModel::STATUS_LATE;
        }

        return [
            'data' => [
                'employee_id'  => $employeeId,
                'date'         => $date,
                'status'       => $status,
                'check_in'     => $checkIn,
                'check_out'    => $checkOut,
                'late_minutes' => $status === AttendanceModel::STATUS_LATE ? $lateMinutes : 0,
                'note'         => $note === '' ? null : $note,
            ],
            'errors' => [],
        ];
    }

    /**
     * Catatan yang sudah ada untuk kombinasi karyawan × tanggal.
     *
     * @param list<int>    $employeeIds
     * @param list<string> $dates
     *
     * @return array<string, array<string, mixed>> kunci "employeeId|date"
     */
    public function existing(array $employeeIds, array $dates): array
    {
        if ($employeeIds === [] || $dates === []) {
            return [];
        }

        $rows = $this->db->table('attendances')
            ->whereIn('employee_id', array_values(array_unique($employeeIds)))
            ->whereIn('date', array_values(array_unique($dates)))
            ->get()->getResultArray();

        $map = [];

        foreach ($rows as $row) {
            $map[$row['employee_id'] . '|' . $row['date']] = $row;
        }

        return $map;
    }

    /**
     * @param array<string, mixed>      $data     hasil normalize()
     * @param array<string, mixed>|null $existing
     */
    public static function changeType(array $data, ?array $existing): string
    {
        if ($existing === null) {
            return 'insert';
        }

        foreach (self::COMPARED as $column) {
            if ((string) ($existing[$column] ?? '') !== (string) ($data[$column] ?? '')) {
                return 'update';
            }
        }

        return 'same';
    }

    /**
     * Simpan massal (insert baru, update yang berubah) dalam satu transaksi.
     *
     * @param list<array<string, mixed>> $rows hasil normalize()
     *
     * @return array{inserted: int, updated: int, unchanged: int}
     */
    public function upsert(array $rows): array
    {
        $existing = $this->existing(array_column($rows, 'employee_id'), array_column($rows, 'date'));
        $now      = date('Y-m-d H:i:s');
        $inserts  = [];
        $updates  = [];
        $same     = 0;

        foreach ($rows as $row) {
            $key     = $row['employee_id'] . '|' . $row['date'];
            $current = $existing[$key] ?? null;

            switch (self::changeType($row, $current)) {
                case 'insert':
                    $inserts[$key] = $row + ['created_at' => $now, 'updated_at' => $now];
                    break;

                case 'update':
                    $updates[$key] = ['id' => (int) $current['id']] + array_intersect_key($row, array_flip(self::COMPARED)) + ['updated_at' => $now];
                    break;

                default:
                    $same++;
            }
        }

        $this->db->transException(true)->transStart();

        if ($inserts !== []) {
            $this->db->table('attendances')->insertBatch(array_values($inserts));
        }

        if ($updates !== []) {
            $this->db->table('attendances')->updateBatch(array_values($updates), 'id');
        }

        $this->db->transComplete();

        return ['inserted' => count($inserts), 'updated' => count($updates), 'unchanged' => $same];
    }
}
