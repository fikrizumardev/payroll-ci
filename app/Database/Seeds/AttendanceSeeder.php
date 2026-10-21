<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Kehadiran harian dan lembur Agustus–Oktober 2026.
 *
 * - Hanya hari kerja (Senin–Jumat, bukan tanggal di tabel holidays).
 * - Tidak ada data sebelum tanggal masuk atau sesudah tanggal resign.
 * - Oktober hanya sampai H-1 tanggal seeding, agar tidak ada kehadiran "masa depan".
 * - Distribusi: ±92% hadir, 4% terlambat, 2% sakit, 0,8% cuti, 0,7% izin, 0,5% alpha.
 */
class AttendanceSeeder extends Seeder
{
    public const START_DATE = '2026-08-01';
    public const END_DATE   = '2026-10-31';

    private const SEED = 20261021;

    /** Skenario yang sengaja ditanam (PRD §B.3). */
    private const FORCED = [
        'EMP-0015|2026-09-10' => ['absent', 'Tidak masuk tanpa keterangan'],
    ];

    private const NOTES = [
        'sick'   => ['Sakit, ada surat dokter', 'Demam, istirahat di rumah', 'Kontrol ke dokter'],
        'leave'  => ['Cuti tahunan', 'Cuti keperluan keluarga'],
        'permit' => ['Izin keperluan keluarga', 'Izin mengurus dokumen', 'Izin acara keluarga'],
        'absent' => ['Tidak masuk tanpa keterangan'],
    ];

    private const OVERTIME_REASONS = [
        'ENG' => ['Deploy rilis aplikasi klien', 'Perbaikan bug produksi', 'Regression test sebelum rilis', 'Migrasi server & database'],
        'FIN' => ['Closing laporan keuangan bulanan', 'Persiapan audit eksternal', 'Rekonsiliasi rekening bank'],
        'HRD' => ['Rekap data kehadiran & payroll', 'Wawancara kandidat', 'Persiapan onboarding karyawan baru'],
        'SLS' => ['Presentasi & demo ke klien', 'Persiapan dokumen tender', 'Mendampingi event klien'],
        'OPS' => ['Antar-jemput tamu perusahaan', 'Pengiriman dokumen klien', 'Persiapan rapat direksi', 'Inventarisasi aset kantor'],
        'DIR' => ['Rapat direksi'],
    ];

    /** Lembur dalam 7 hari terakhir dibiarkan "pending" untuk demo persetujuan. */
    private const PENDING_WINDOW_DAYS = 7;

    private const MAX_OVERTIME_HOURS_PER_MONTH = 12;

    private Randomizer $random;

    public function run(): void
    {
        $this->random = new Randomizer(new Mt19937(self::SEED));

        $endDate = min(self::END_DATE, date('Y-m-d', strtotime('-1 day')));

        if ($endDate < self::START_DATE) {
            return;
        }

        $workdays  = $this->workdays(self::START_DATE, $endDate);
        $employees = $this->db->table('employees')
            ->select('employees.id, employees.nip, employees.join_date, employees.resign_date')
            ->select('positions.overtime_eligible, departments.code AS department_code')
            ->join('positions', 'positions.id = employees.position_id')
            ->join('departments', 'departments.id = employees.department_id')
            ->orderBy('employees.nip')
            ->get()->getResultArray();

        $workStart = $this->setting('attendance.work_start', '08:00');
        $tolerance = (int) $this->setting('attendance.late_tolerance_minutes', '15');
        $now       = date('Y-m-d H:i:s');

        $attendances = [];

        foreach ($employees as $employee) {
            foreach ($workdays as $date) {
                if ($date < $employee['join_date'] || ($employee['resign_date'] !== null && $date > $employee['resign_date'])) {
                    continue;
                }

                [$status, $note] = self::FORCED[$employee['nip'] . '|' . $date] ?? $this->rollStatus();

                // Bulan yang punya skenario tertanam tidak boleh mendapat alpha acak tambahan
                if ($status === 'absent' && ! isset(self::FORCED[$employee['nip'] . '|' . $date])
                    && $this->hasForcedScenario($employee['nip'], substr($date, 0, 7))) {
                    [$status, $note] = ['present', null];
                }

                $attendances[$employee['id']][$date] = [
                    'employee_id'  => $employee['id'],
                    'date'         => $date,
                    'status'       => $status,
                    'note'         => $note,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ] + $this->clockTimes($status, $workStart, $tolerance);
            }
        }

        $overtimes = $this->overtimes($employees, $attendances, $endDate, $now);

        $rows = array_merge(...array_map('array_values', array_values($attendances)));

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->db->table('attendances')->insertBatch($chunk);
        }

        if ($overtimes !== []) {
            $this->db->table('overtimes')->insertBatch($overtimes);
        }
    }

    private function hasForcedScenario(string $nip, string $month): bool
    {
        foreach (array_keys(self::FORCED) as $key) {
            if (str_starts_with($key, $nip . '|' . $month)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{string, string|null}
     */
    private function rollStatus(): array
    {
        $roll   = $this->random->getInt(1, 1000);
        $status = match (true) {
            $roll <= 920 => 'present',
            $roll <= 960 => 'late',
            $roll <= 980 => 'sick',
            $roll <= 988 => 'leave',
            $roll <= 995 => 'permit',
            default      => 'absent',
        };

        return [$status, isset(self::NOTES[$status]) ? $this->pick(self::NOTES[$status]) : null];
    }

    /**
     * @return array{check_in: string|null, check_out: string|null, late_minutes: int}
     */
    private function clockTimes(string $status, string $workStart, int $tolerance): array
    {
        if (! in_array($status, ['present', 'late'], true)) {
            return ['check_in' => null, 'check_out' => null, 'late_minutes' => 0];
        }

        $start = new DateTimeImmutable('2000-01-01 ' . $workStart);

        if ($status === 'present') {
            // Datang 30 menit sebelum jam masuk s.d. batas toleransi
            $checkIn     = $start->modify(sprintf('%+d minutes', $this->random->getInt(-30, $tolerance - 1)));
            $lateMinutes = 0;
        } else {
            $lateMinutes = $this->random->getInt($tolerance + 1, 80);
            $checkIn     = $start->modify("+{$lateMinutes} minutes");
        }

        $checkOut = new DateTimeImmutable('2000-01-01 17:00');
        $checkOut = $checkOut->modify('+' . $this->random->getInt(0, 50) . ' minutes');

        return [
            'check_in'     => $checkIn->format('H:i:s'),
            'check_out'    => $checkOut->format('H:i:s'),
            'late_minutes' => $lateMinutes,
        ];
    }

    /**
     * Lembur 0–3 kali per bulan (maks. 12 jam) untuk jabatan yang berhak lembur.
     * Jam pulang di data kehadiran disesuaikan dengan jam selesai lembur.
     *
     * @param list<array<string, mixed>>                       $employees
     * @param array<int, array<string, array<string, mixed>>>  $attendances
     *
     * @return list<array<string, mixed>>
     */
    private function overtimes(array $employees, array &$attendances, string $endDate, string $now): array
    {
        $approver     = $this->db->table('users')->select('id')->where('username', 'sinta.maharani')->get()->getRowArray();
        $approverId   = $approver['id'] ?? null;
        $pendingSince = date('Y-m-d', strtotime($endDate . ' -' . (self::PENDING_WINDOW_DAYS - 1) . ' days'));
        $rows         = [];

        foreach ($employees as $employee) {
            if (! (int) $employee['overtime_eligible'] || ! isset($attendances[$employee['id']])) {
                continue;
            }

            $presentDaysByMonth = [];

            foreach ($attendances[$employee['id']] as $date => $attendance) {
                if (in_array($attendance['status'], ['present', 'late'], true)) {
                    $presentDaysByMonth[substr($date, 0, 7)][] = $date;
                }
            }

            foreach ($presentDaysByMonth as $days) {
                $sessions = min($this->random->getInt(0, 3), count($days));

                if ($sessions === 0) {
                    continue;
                }

                $picked = $this->random->pickArrayKeys($days, $sessions);
                $budget = self::MAX_OVERTIME_HOURS_PER_MONTH;

                foreach ($picked as $key) {
                    $date  = $days[$key];
                    $hours = min($this->random->getInt(1, 4), $budget);

                    if ($hours <= 0) {
                        break;
                    }

                    $budget -= $hours;
                    $end     = sprintf('%02d:00:00', 17 + $hours);
                    $pending = $date >= $pendingSince;

                    $rows[] = [
                        'employee_id' => $employee['id'],
                        'date'        => $date,
                        'start_time'  => '17:00:00',
                        'end_time'    => $end,
                        'hours'       => number_format($hours, 2, '.', ''),
                        'reason'      => $this->pick(self::OVERTIME_REASONS[$employee['department_code']] ?? ['Pekerjaan tambahan']),
                        'status'      => $pending ? 'pending' : 'approved',
                        'approved_by' => $pending ? null : $approverId,
                        'approved_at' => $pending ? null : $this->nextWorkingMorning($date),
                        'created_at'  => $date . ' 16:30:00',
                        'updated_at'  => $now,
                    ];

                    $attendances[$employee['id']][$date]['check_out'] = sprintf('%02d:%02d:00', 17 + $hours, $this->random->getInt(2, 15));
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$a['date'], $a['employee_id']] <=> [$b['date'], $b['employee_id']]);

        return $rows;
    }

    /**
     * @return list<string> tanggal hari kerja (Y-m-d)
     */
    private function workdays(string $start, string $end): array
    {
        $holidays = array_flip(array_column(
            $this->db->table('holidays')->select('date')->where('date >=', $start)->where('date <=', $end)->get()->getResultArray(),
            'date',
        ));

        $days   = [];
        $period = new DatePeriod(new DateTimeImmutable($start), new DateInterval('P1D'), (new DateTimeImmutable($end))->modify('+1 day'));

        foreach ($period as $day) {
            $date = $day->format('Y-m-d');

            if ((int) $day->format('N') <= 5 && ! isset($holidays[$date])) {
                $days[] = $date;
            }
        }

        return $days;
    }

    private function nextWorkingMorning(string $date): string
    {
        $next = new DateTimeImmutable($date . ' +1 day');

        while ((int) $next->format('N') > 5) {
            $next = $next->modify('+1 day');
        }

        return $next->format('Y-m-d') . sprintf(' 09:%02d:00', $this->random->getInt(5, 55));
    }

    private function setting(string $key, string $default): string
    {
        $row = $this->db->table('settings')->select('value')->where('key', $key)->get()->getRowArray();

        return $row['value'] ?? $default;
    }

    /**
     * @param list<string> $items
     */
    private function pick(array $items): string
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }
}
