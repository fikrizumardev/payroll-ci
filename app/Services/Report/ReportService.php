<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\AttendanceModel;
use App\Models\HolidayModel;
use App\Models\PayrollModel;
use App\Models\PayrollPeriodModel;
use App\Services\Payroll\WorkdayCalendar;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;

/**
 * Sumber data seluruh laporan (rekap gaji, BPJS, PPh 21, kehadiran, tren biaya).
 *
 * Angka payroll diambil dari hasil generate (`payrolls` + `payroll_items`) dan data
 * karyawan dari snapshot payroll — sama persis dengan slip gaji, walau data master
 * sudah berubah sesudahnya. Semua nominal integer rupiah.
 */
class ReportService
{
    /** Status periode yang angkanya sudah final (tidak bisa di-generate ulang). */
    public const FINAL_STATUSES = [PayrollPeriodModel::STATUS_APPROVED, PayrollPeriodModel::STATUS_PAID, PayrollPeriodModel::STATUS_LOCKED];

    /** Kolom angka rekap gaji (urutan = urutan kolom ekspor). */
    public const RECAP_COLUMNS = [
        'basic'           => 'Gaji Pokok',
        'allowance'       => 'Tunjangan Tetap',
        'daily'           => 'Tunjangan Harian',
        'overtime'        => 'Lembur',
        'other_earning'   => 'Bonus & Lainnya',
        'gross'           => 'Total Pendapatan',
        'bpjs_employee'   => 'BPJS Karyawan',
        'pph21'           => 'PPh 21',
        'other_deduction' => 'Potongan Lain',
        'deduction'       => 'Total Potongan',
        'thp'             => 'Take Home Pay',
        'bpjs_company'    => 'BPJS Perusahaan',
        'employer_cost'   => 'Biaya Perusahaan',
    ];

    /**
     * Program BPJS → kode item porsi perusahaan (ER) & karyawan (EE).
     *
     * @var array<string, array{label: string, agency: string, er: string|null, ee: string|null}>
     */
    public const BPJS_PROGRAMS = [
        'kes' => ['label' => 'Jaminan Kesehatan', 'agency' => 'BPJS Kesehatan', 'er' => 'BPJS_KES_ER', 'ee' => 'BPJS_KES_EE'],
        'jht' => ['label' => 'Jaminan Hari Tua (JHT)', 'agency' => 'BPJS Ketenagakerjaan', 'er' => 'JHT_ER', 'ee' => 'JHT_EE'],
        'jkk' => ['label' => 'Jaminan Kecelakaan Kerja (JKK)', 'agency' => 'BPJS Ketenagakerjaan', 'er' => 'JKK_ER', 'ee' => null],
        'jkm' => ['label' => 'Jaminan Kematian (JKM)', 'agency' => 'BPJS Ketenagakerjaan', 'er' => 'JKM_ER', 'ee' => null],
        'jp'  => ['label' => 'Jaminan Pensiun (JP)', 'agency' => 'BPJS Ketenagakerjaan', 'er' => 'JP_ER', 'ee' => 'JP_EE'],
    ];

    /** Kode objek pajak e-Bupot: penghasilan pegawai tetap (termasuk kontrak yang bekerja penuh). */
    public const TAX_OBJECT_CODE = '21-100-01';

    /** Komponen pendapatan per kolom rekap; sisanya masuk "Bonus & Lainnya". */
    private const EARNING_GROUPS = [
        'basic'     => ['BASIC'],
        'allowance' => ['POS_ALW', 'COMM_ALW'],
        'daily'     => ['TRANSPORT', 'MEAL'],
        'overtime'  => ['OVERTIME'],
    ];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public static function make(): self
    {
        return new self(db_connect());
    }

    // ------------------------------------------------------------------
    // Periode
    // ------------------------------------------------------------------

    /**
     * Periode yang sudah memiliki hasil payroll, terbaru lebih dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function periods(): array
    {
        return array_map(static function (array $row): array {
            $row['id']        = (int) $row['id'];
            $row['year']      = (int) $row['year'];
            $row['month']     = (int) $row['month'];
            $row['employees'] = (int) $row['employees'];

            return $row;
        }, $this->db->table('payroll_periods pp')
            ->select('pp.id, pp.name, pp.year, pp.month, pp.status, pp.pay_date, COUNT(p.id) AS employees')
            ->join('payrolls p', 'p.period_id = pp.id')
            ->groupBy('pp.id')
            ->orderBy('pp.year', 'DESC')->orderBy('pp.month', 'DESC')
            ->get()->getResultArray());
    }

    public static function isFinal(array $period): bool
    {
        return in_array($period['status'], self::FINAL_STATUSES, true);
    }

    // ------------------------------------------------------------------
    // Rekap gaji
    // ------------------------------------------------------------------

    /**
     * Rekap gaji satu periode, dikelompokkan per departemen dengan subtotal & grand total.
     *
     * @return array{groups: list<array{department: string, rows: list<array<string, mixed>>, subtotal: array<string, int>}>, total: array<string, int>, employees: int}
     */
    public function payrollRecap(int $periodId): array
    {
        $items  = $this->itemsByPayroll($periodId);
        $groups = [];

        foreach ($this->payrolls($periodId) as $payroll) {
            $snapshot = $payroll['snapshot'] ?? [];
            $codes    = $items[$payroll['id']] ?? [];
            $amounts  = [];

            foreach (self::EARNING_GROUPS as $key => $groupCodes) {
                $amounts[$key] = array_sum(array_map(static fn (string $code): int => $codes[$code]['amount'] ?? 0, $groupCodes));
            }

            // Sisa pendapatan (bonus, THR, pengembalian PPh, komponen tambahan) agar baris selalu rekonsiliasi dengan total
            $amounts['other_earning']   = $payroll['total_earning'] - array_sum($amounts);
            $amounts['gross']           = $payroll['total_earning'];
            $amounts['bpjs_employee'] = $payroll['bpjs_employee_total'];
            // payrolls.pph21 bertanda (negatif = lebih potong yang dikembalikan sebagai pendapatan
            // PPH21_REFUND); kolom potongan memakai item PPH21 yang benar-benar dipotong.
            $amounts['pph21']           = $codes['PPH21']['amount'] ?? 0;
            $amounts['other_deduction'] = $payroll['total_deduction'] - $payroll['bpjs_employee_total'] - $amounts['pph21'];
            $amounts['deduction']       = $payroll['total_deduction'];
            $amounts['thp']             = $payroll['take_home_pay'];
            $amounts['bpjs_company']    = $payroll['bpjs_company_total'];
            $amounts['employer_cost']   = $payroll['total_earning'] + $payroll['bpjs_company_total'];

            $department = (string) ($snapshot['department'] ?? 'Tanpa Departemen');

            $groups[$department][] = [
                'payroll_id'      => $payroll['id'],
                'employee_id'     => $payroll['employee_id'],
                'nip'             => (string) ($snapshot['nip'] ?? ''),
                'name'            => (string) ($snapshot['full_name'] ?? ''),
                'position'        => (string) ($snapshot['position'] ?? ''),
                'department'      => $department,
                'employment_type' => (string) ($snapshot['employment_type'] ?? ''),
                'ptkp_status'     => (string) ($snapshot['ptkp_status'] ?? ''),
                'workdays'        => $payroll['active_workdays'],
                'present_days'    => $payroll['present_days'],
                'absent_days'     => $payroll['absent_days'],
                'prorated'        => (bool) ($snapshot['prorated'] ?? false),
                'amounts'         => array_merge(array_fill_keys(array_keys(self::RECAP_COLUMNS), 0), $amounts),
            ];
        }

        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        $result = [];
        $total  = array_fill_keys(array_keys(self::RECAP_COLUMNS), 0);
        $count  = 0;

        foreach ($groups as $department => $rows) {
            usort($rows, static fn (array $a, array $b): int => strcmp($a['nip'], $b['nip']));

            $subtotal = array_fill_keys(array_keys(self::RECAP_COLUMNS), 0);

            foreach ($rows as $row) {
                foreach ($row['amounts'] as $key => $amount) {
                    $subtotal[$key] += $amount;
                    $total[$key] += $amount;
                }
            }

            $count += count($rows);
            $result[] = ['department' => $department, 'rows' => $rows, 'subtotal' => $subtotal];
        }

        return ['groups' => $result, 'total' => $total, 'employees' => $count];
    }

    // ------------------------------------------------------------------
    // BPJS
    // ------------------------------------------------------------------

    /**
     * Iuran BPJS per program (porsi perusahaan vs karyawan) dan rincian per karyawan.
     *
     * @return array{programs: array<string, array<string, mixed>>, rows: list<array<string, mixed>>, total: array<string, int>}
     */
    public function bpjs(int $periodId): array
    {
        $items    = $this->itemsByPayroll($periodId);
        $programs = [];

        foreach (self::BPJS_PROGRAMS as $key => $program) {
            $programs[$key] = $program + ['participants' => 0, 'company' => 0, 'employee' => 0, 'total' => 0, 'er_rates' => [], 'ee_rates' => []];
        }

        $rows  = [];
        $total = ['company' => 0, 'employee' => 0, 'total' => 0];

        foreach ($this->payrolls($periodId) as $payroll) {
            $codes = $items[$payroll['id']] ?? [];
            $row   = [
                'nip'      => (string) ($payroll['snapshot']['nip'] ?? ''),
                'name'     => (string) ($payroll['snapshot']['full_name'] ?? ''),
                'nik'      => (string) ($payroll['snapshot']['nik'] ?? ''),
                'wage'     => $payroll['bpjs_base'],
                'programs' => [],
                'company'  => 0,
                'employee' => 0,
            ];

            foreach (self::BPJS_PROGRAMS as $key => $program) {
                $er = $program['er'] === null ? null : ($codes[$program['er']] ?? null);
                $ee = $program['ee'] === null ? null : ($codes[$program['ee']] ?? null);

                $row['programs'][$key] = [
                    'base'     => (int) ($er['meta']['basis'] ?? $ee['meta']['basis'] ?? 0),
                    'company'  => $er['amount'] ?? 0,
                    'employee' => $ee['amount'] ?? 0,
                ];

                if ($er === null && $ee === null) {
                    continue;
                }

                $programs[$key]['participants']++;
                $programs[$key]['company'] += $er['amount'] ?? 0;
                $programs[$key]['employee'] += $ee['amount'] ?? 0;

                if (isset($er['meta']['rate'])) {
                    $programs[$key]['er_rates'][(string) $er['meta']['rate']] = true;
                }

                if (isset($ee['meta']['rate'])) {
                    $programs[$key]['ee_rates'][(string) $ee['meta']['rate']] = true;
                }

                $row['company'] += $er['amount'] ?? 0;
                $row['employee'] += $ee['amount'] ?? 0;
            }

            $total['company'] += $row['company'];
            $total['employee'] += $row['employee'];
            $rows[] = $row;
        }

        foreach ($programs as &$program) {
            $program['total'] = $program['company'] + $program['employee'];
            // Tarif seragam ditampilkan; bila berubah di tengah periode (beda tarif), tampil null
            $program['er_rate'] = count($program['er_rates']) === 1 ? (float) array_key_first($program['er_rates']) : null;
            $program['ee_rate'] = count($program['ee_rates']) === 1 ? (float) array_key_first($program['ee_rates']) : null;
            unset($program['er_rates'], $program['ee_rates']);
        }
        unset($program);

        usort($rows, static fn (array $a, array $b): int => strcmp($a['nip'], $b['nip']));
        $total['total'] = $total['company'] + $total['employee'];

        return ['programs' => $programs, 'rows' => $rows, 'total' => $total];
    }

    // ------------------------------------------------------------------
    // PPh 21
    // ------------------------------------------------------------------

    /**
     * Daftar pemotongan PPh 21 bulanan (format siap pindah ke e-Bupot 21).
     *
     * `pph21` = PPh yang dipotong (≥ 0); `refund` = lebih potong di masa pajak terakhir yang
     * dikembalikan ke karyawan; `net` = jumlah yang disetor (dipotong − dikembalikan).
     *
     * @return array{rows: list<array<string, mixed>>, total: array{bruto: int, pph21: int, refund: int, net: int}, categories: array<string, array{employees: int, bruto: int, pph21: int}>}
     */
    public function pph21(int $periodId): array
    {
        $items      = $this->itemsByPayroll($periodId);
        $rows       = [];
        $total      = ['bruto' => 0, 'pph21' => 0, 'refund' => 0, 'net' => 0];
        $categories = [];

        foreach ($this->payrolls($periodId) as $payroll) {
            $snapshot = $payroll['snapshot'] ?? [];
            $withheld = $items[$payroll['id']]['PPH21']['amount'] ?? 0;
            $refund   = $items[$payroll['id']]['PPH21_REFUND']['amount'] ?? 0;
            $method   = $payroll['ter_rate'] === null ? 'annual' : 'ter';
            $category = $method === 'ter' ? (string) $payroll['ter_category'] : 'Setahun';

            $rows[] = [
                'nip'             => (string) ($snapshot['nip'] ?? ''),
                'name'            => (string) ($snapshot['full_name'] ?? ''),
                'nik'             => (string) ($snapshot['nik'] ?? ''),
                // NPWP 16 digit = NIK (PMK 112/2022); bila kosong dipakai NIK
                'npwp'            => (string) (($snapshot['npwp'] ?? '') !== '' ? $snapshot['npwp'] : ($snapshot['nik'] ?? '')),
                'ptkp_status'     => (string) ($snapshot['ptkp_status'] ?? ''),
                'employment_type' => (string) ($snapshot['employment_type'] ?? ''),
                'position'        => (string) ($snapshot['position'] ?? ''),
                'bruto'           => $payroll['bruto_pph'],
                'method'          => $method,
                'ter_category'    => $payroll['ter_category'],
                'ter_rate'        => $payroll['ter_rate'],
                'pph21'           => $withheld,
                'refund'          => $refund,
            ];

            $total['bruto'] += $payroll['bruto_pph'];
            $total['pph21'] += $withheld;
            $total['refund'] += $refund;

            $categories[$category] ??= ['employees' => 0, 'bruto' => 0, 'pph21' => 0];
            $categories[$category]['employees']++;
            $categories[$category]['bruto'] += $payroll['bruto_pph'];
            $categories[$category]['pph21'] += $withheld - $refund;
        }

        $total['net'] = $total['pph21'] - $total['refund'];

        usort($rows, static fn (array $a, array $b): int => strcmp($a['nip'], $b['nip']));
        ksort($categories);

        return ['rows' => $rows, 'total' => $total, 'categories' => $categories];
    }

    // ------------------------------------------------------------------
    // Kehadiran
    // ------------------------------------------------------------------

    /**
     * Rekap kehadiran bulanan per karyawan (termasuk menit terlambat & lembur disetujui).
     * Hari kerja dihitung s.d. akhir bulan atau hari ini (mana yang lebih dulu).
     *
     * @return array{rows: list<array<string, mixed>>, total: array<string, int|float>, workdays: int, until: string, start: string, end: string}
     */
    public function attendance(int $year, int $month, ?int $departmentId = null, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $start   = sprintf('%04d-%02d-01', $year, $month);
        $end     = (new DateTimeImmutable($start))->format('Y-m-t');
        $until   = min($end, $today);

        $calendar = new WorkdayCalendar((new HolidayModel())->datesBetween($start, $end));

        $builder = $this->db->table('employees e')
            ->select('e.id, e.nip, e.full_name, e.join_date, e.resign_date, e.status, d.name AS department, p.name AS position')
            ->join('departments d', 'd.id = e.department_id')
            ->join('positions p', 'p.id = e.position_id')
            ->where('e.deleted_at', null)
            ->where('e.join_date <=', $end)
            ->groupStart()->where('e.resign_date', null)->orWhere('e.resign_date >=', $start)->groupEnd()
            ->orderBy('d.name')->orderBy('e.nip');

        if ($departmentId !== null && $departmentId > 0) {
            $builder->where('e.department_id', $departmentId);
        }

        $employees = $builder->get()->getResultArray();
        $ids       = array_map('intval', array_column($employees, 'id'));
        $counts    = [];
        $overtime  = [];

        if ($ids !== []) {
            $rows = $this->db->table('attendances')
                ->select('employee_id, status, COUNT(*) AS days, COALESCE(SUM(late_minutes), 0) AS late_minutes')
                ->whereIn('employee_id', $ids)
                ->where('date >=', $start)->where('date <=', $end)
                ->groupBy(['employee_id', 'status'])
                ->get()->getResultArray();

            foreach ($rows as $row) {
                $counts[(int) $row['employee_id']][$row['status']] = (int) $row['days'];

                if ($row['status'] === AttendanceModel::STATUS_LATE) {
                    $counts[(int) $row['employee_id']]['late_minutes'] = (int) $row['late_minutes'];
                }
            }

            $overtime = array_map('floatval', array_column($this->db->table('overtimes')
                ->select('employee_id, SUM(hours) AS hours')
                ->whereIn('employee_id', $ids)
                ->where('status', 'approved')
                ->where('date >=', $start)->where('date <=', $end)
                ->groupBy('employee_id')
                ->get()->getResultArray(), 'hours', 'employee_id'));
        }

        $statuses = array_keys(AttendanceModel::STATUSES);
        $result   = [];
        $total    = array_fill_keys([...$statuses, 'late_minutes', 'workdays', 'unrecorded'], 0) + ['overtime' => 0.0];

        foreach ($employees as $employee) {
            $id       = (int) $employee['id'];
            $days     = array_merge(array_fill_keys([...$statuses, 'late_minutes'], 0), $counts[$id] ?? []);
            $workdays = $until < $start ? 0 : $calendar->activeWorkdays($start, $until, $employee['join_date'], $employee['resign_date']);
            $recorded = array_sum(array_intersect_key($days, array_flip($statuses))) - $days['holiday'];
            $attended = $days['present'] + $days['late'];

            $row = [
                'employee_id' => $id,
                'nip'         => $employee['nip'],
                'name'        => $employee['full_name'],
                'department'  => $employee['department'],
                'position'    => $employee['position'],
                'status'      => $employee['status'],
                'join_date'   => $employee['join_date'],
                'resign_date' => $employee['resign_date'],
                'workdays'    => $workdays,
                'days'        => $days,
                'attended'    => $attended,
                'unrecorded'  => max(0, $workdays - $recorded),
                'overtime'    => $overtime[$id] ?? 0.0,
                'rate'        => $workdays > 0 ? min(100.0, $attended / $workdays * 100) : null,
            ];

            foreach ($statuses as $status) {
                $total[$status] += $days[$status];
            }

            $total['late_minutes'] += $days['late_minutes'];
            $total['workdays'] += $workdays;
            $total['unrecorded'] += $row['unrecorded'];
            $total['overtime'] += $row['overtime'];
            $result[] = $row;
        }

        $total['rate'] = $total['workdays'] > 0 ? ($total['present'] + $total['late']) / $total['workdays'] * 100 : null;

        return ['rows' => $result, 'total' => $total, 'workdays' => $until < $start ? 0 : $calendar->count($start, $until), 'until' => $until, 'start' => $start, 'end' => $end];
    }

    // ------------------------------------------------------------------
    // Tren biaya
    // ------------------------------------------------------------------

    /**
     * Biaya perusahaan (pendapatan + BPJS perusahaan) per departemen selama `$months`
     * bulan yang berakhir di bulan tertentu. Bulan tanpa periode payroll bernilai null.
     *
     * @return array{months: list<array<string, mixed>>, departments: list<array{key: string, name: string, costs: list<int|null>, total: int}>, totals: list<int|null>, grand: int}
     */
    public function costTrend(int $endYear, int $endMonth, int $months = 12): array
    {
        $end   = new DateTimeImmutable(sprintf('%04d-%02d-01', $endYear, $endMonth));
        $start = $end->modify('-' . ($months - 1) . ' month');

        $slots = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->modify("+{$i} month");
            $slots[$month->format('Y-m')] = [
                'key'       => $month->format('Y-m'),
                'year'      => (int) $month->format('Y'),
                'month'     => (int) $month->format('n'),
                'period_id' => null,
                'status'    => null,
                'employees' => 0,
                'cost'      => null,
            ];
        }

        $startIndex = (int) $start->format('Y') * 12 + (int) $start->format('n');
        $endIndex   = $endYear * 12 + $endMonth;

        $rows = $this->db->table('payrolls p')
            ->select('pp.id AS period_id, pp.year, pp.month, pp.status')
            ->select("JSON_UNQUOTE(JSON_EXTRACT(p.snapshot, '$.department_id')) AS department_id", false)
            ->select("JSON_UNQUOTE(JSON_EXTRACT(p.snapshot, '$.department')) AS department", false)
            ->select('COUNT(*) AS employees, SUM(p.total_earning + p.bpjs_company_total) AS cost')
            ->join('payroll_periods pp', 'pp.id = p.period_id')
            ->where('(pp.year * 12 + pp.month) >=', $startIndex, false)
            ->where('(pp.year * 12 + pp.month) <=', $endIndex, false)
            ->groupBy(['pp.id', 'department_id', 'department'])
            ->get()->getResultArray();

        // Urutan departemen tetap (berdasarkan ID) agar warna mengikuti departemen, bukan peringkat
        $names = array_column($this->db->table('departments')->select('id, name')->orderBy('id')->get()->getResultArray(), 'name', 'id');
        $costs = [];

        foreach ($rows as $row) {
            $key  = sprintf('%04d-%02d', $row['year'], $row['month']);
            $dept = (string) ($row['department_id'] ?? '0');

            $names[$dept] ??= (string) ($row['department'] ?? 'Tanpa Departemen');
            $costs[$dept][$key] = ($costs[$dept][$key] ?? 0) + (int) $row['cost'];

            $slots[$key]['period_id'] = (int) $row['period_id'];
            $slots[$key]['status']    = $row['status'];
            $slots[$key]['employees'] += (int) $row['employees'];
            $slots[$key]['cost']      = ($slots[$key]['cost'] ?? 0) + (int) $row['cost'];
        }

        $departments = [];

        foreach ($names as $id => $name) {
            if (! isset($costs[(string) $id])) {
                continue;
            }

            $series = [];

            foreach ($slots as $key => $slot) {
                $series[] = $slot['period_id'] === null ? null : ($costs[(string) $id][$key] ?? 0);
            }

            $departments[] = ['key' => (string) $id, 'name' => $name, 'costs' => $series, 'total' => (int) array_sum($series)];
        }

        $totals = array_column(array_values($slots), 'cost');

        return [
            'months'      => array_values($slots),
            'departments' => $departments,
            'totals'      => $totals,
            'grand'       => (int) array_sum($totals),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function payrolls(int $periodId): array
    {
        return (new PayrollModel())->where('period_id', $periodId)->orderBy('id')->findAll();
    }

    /**
     * Item payroll satu periode: payroll_id → kode → {amount, meta} (nominal kode yang sama dijumlah).
     *
     * @return array<int, array<string, array{amount: int, type: string, meta: array<string, mixed>}>>
     */
    private function itemsByPayroll(int $periodId): array
    {
        $rows = $this->db->table('payroll_items pi')
            ->select('pi.payroll_id, pi.code, pi.type, pi.amount, pi.meta')
            ->join('payrolls p', 'p.id = pi.payroll_id')
            ->where('p.period_id', $periodId)
            ->get()->getResultArray();

        $items = [];

        foreach ($rows as $row) {
            $payrollId = (int) $row['payroll_id'];
            $code      = (string) $row['code'];

            if (isset($items[$payrollId][$code])) {
                $items[$payrollId][$code]['amount'] += (int) $row['amount'];

                continue;
            }

            $items[$payrollId][$code] = [
                'amount' => (int) $row['amount'],
                'type'   => (string) $row['type'],
                'meta'   => $row['meta'] === null ? [] : (json_decode((string) $row['meta'], true) ?: []),
            ];
        }

        return $items;
    }
}
