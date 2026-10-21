<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\AttendanceModel;
use App\Models\EmployeeModel;
use App\Models\PayrollPeriodModel;
use App\Models\SalaryComponentModel;
use App\Services\AuditLogger;
use App\Services\SettingService;
use CodeIgniter\Database\BaseConnection;

/**
 * Pembuatan periode & generate payroll dari data database.
 * Logika hitung ada di PayrollCalculator (murni); kelas ini memuat data secara
 * massal, menyusun EmployeePayrollInput, lalu menyimpan payrolls + payroll_items.
 */
class PayrollService
{
    use ChecksRole;

    private ?PayrollRates $rates = null;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly SettingService $settings,
        private readonly AuditLogger $audit,
    ) {
    }

    public static function make(): self
    {
        return new self(db_connect(), service('appSettings'), service('auditLogger'));
    }

    public function rates(): PayrollRates
    {
        return $this->rates ??= PayrollRates::fromDatabase($this->settings);
    }

    // ------------------------------------------------------------------
    // Periode
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $actor
     */
    public function createPeriod(int $year, int $month, array $actor, ?string $notes = null): int
    {
        self::ensureRole($actor, 'buat periode payroll', 'hr');

        if ($month < 1 || $month > 12 || $year < 2000) {
            throw new PayrollException('Bulan atau tahun periode tidak valid.');
        }

        $periods = new PayrollPeriodModel();

        if ($periods->where(['year' => $year, 'month' => $month])->countAllResults() > 0) {
            throw new PayrollException('Periode ' . periode_id($year, $month) . ' sudah ada.');
        }

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        $id = (int) $periods->insert([
            'name'       => periode_id($year, $month),
            'year'       => $year,
            'month'      => $month,
            'start_date' => $start,
            'end_date'   => $end,
            'pay_date'   => $this->payDate($year, $month),
            'status'     => PayrollPeriodModel::STATUS_DRAFT,
            'workdays'   => $this->calendar($start, $end)->count($start, $end),
            'notes'      => $notes,
            'created_by' => $actor['id'] ?? null,
        ]);

        $this->audit->log('payroll.create', 'payroll_period', $id, 'Membuat periode payroll ' . periode_id($year, $month), actor: $actor);

        return $id;
    }

    /**
     * Tanggal gajian: setting `payroll.pay_day` (0 = hari kerja terakhir bulan itu),
     * dimajukan ke hari kerja sebelumnya bila jatuh pada akhir pekan/libur.
     */
    public function payDate(int $year, int $month): string
    {
        $start  = sprintf('%04d-%02d-01', $year, $month);
        $end    = date('Y-m-t', strtotime($start));
        $payDay = $this->settings->int('payroll.pay_day', 0);
        $target = $payDay <= 0 ? $end : sprintf('%04d-%02d-%02d', $year, $month, min($payDay, (int) date('t', strtotime($start))));

        return $this->calendar($start, $end)->previousWorkday($target);
    }

    // ------------------------------------------------------------------
    // Generate
    // ------------------------------------------------------------------

    /**
     * Hitung ulang payroll seluruh karyawan untuk periode berstatus draft.
     *
     * @param array<string, mixed> $actor
     *
     * @return array{employees: int, total_earning: int, total_deduction: int, take_home_pay: int, pph21: int, company_contribution: int, warnings: array<string, list<string>>, duration_ms: int}
     */
    public function generate(int $periodId, array $actor): array
    {
        self::ensureRole($actor, 'generate payroll', 'hr');

        $startedAt = microtime(true);
        $period    = (new PayrollPeriodModel())->find($periodId) ?? throw new PayrollException('Periode payroll tidak ditemukan.');

        if ($period['status'] !== PayrollPeriodModel::STATUS_DRAFT) {
            throw new PayrollException('Payroll hanya dapat di-generate saat periode berstatus Draft.');
        }

        $calendar       = $this->calendar($period['start_date'], $period['end_date']);
        $periodWorkdays = $calendar->count($period['start_date'], $period['end_date']);

        if ($periodWorkdays === 0) {
            throw new PayrollException('Periode tidak memiliki hari kerja.');
        }

        $employees = (new EmployeeModel())->withRelations()
            ->employedBetween($period['start_date'], $period['end_date'])
            ->orderBy('employees.nip')
            ->findAll();

        $ids          = array_map(static fn (array $e): int => (int) $e['id'], $employees);
        $profiles     = $this->componentProfiles($ids);
        $attendance   = $this->attendanceSummary($ids, $period['start_date'], $period['end_date']);
        $overtime     = $this->approvedOvertime($ids, $period['start_date'], $period['end_date']);
        $adjustments  = $this->adjustments($periodId);
        $loans        = $this->loanInstallments($period);
        $yearToDate   = $this->yearToDate($ids, $period['year'], $period['month']);
        $componentIds = (new SalaryComponentModel())->idsByCode();
        $sortOrder    = array_map('intval', array_column((new SalaryComponentModel())->findAll(), 'sort_order', 'code'));
        $calculator   = new PayrollCalculator($this->rates());

        $summary = [
            'employees'            => 0,
            'total_earning'        => 0,
            'total_deduction'      => 0,
            'take_home_pay'        => 0,
            'pph21'                => 0,
            'company_contribution' => 0,
            'warnings'             => [],
        ];

        $this->db->transException(true)->transStart();

        // Hasil lama dihapus; payroll_items ikut terhapus (ON DELETE CASCADE)
        $this->db->table('payrolls')->where('period_id', $periodId)->delete();

        foreach ($employees as $employee) {
            $id       = (int) $employee['id'];
            $active   = $calendar->activeWorkdays($period['start_date'], $period['end_date'], $employee['join_date'], $employee['resign_date']);
            $days     = $attendance[$id] ?? array_fill_keys(array_keys(AttendanceModel::STATUSES), 0);
            $profile  = $profiles[$id] ?? ['fixedEarnings' => [], 'dailyEarnings' => $profiles[0]['dailyEarnings'] ?? [], 'fixedDeductions' => []];
            $isFinal  = (int) $period['month'] === 12
                || ($employee['resign_date'] !== null && $employee['resign_date'] <= $period['end_date']);
            $ytd      = $yearToDate[$id] ?? ['months' => 0, 'gross' => 0, 'pension' => 0, 'pph21' => 0];
            $warnings = [];

            if ($active === 0) {
                continue;
            }

            $input = new EmployeePayrollInput(
                employeeId: $id,
                ptkpStatus: $employee['ptkp_status'],
                basicSalary: (int) $employee['basic_salary'],
                periodWorkdays: $periodWorkdays,
                activeWorkdays: $active,
                month: (int) $period['month'],
                fixedEarnings: $profile['fixedEarnings'],
                dailyEarnings: $profile['dailyEarnings'],
                fixedDeductions: $profile['fixedDeductions'],
                presentDays: $days['present'] + $days['late'],
                lateDays: $days['late'],
                absentDays: $days['absent'],
                overtimeEligible: (bool) $employee['overtime_eligible'],
                overtimeHours: $overtime[$id] ?? [],
                adjustments: $adjustments[$id] ?? [],
                loanInstallments: $loans[$id] ?? [],
                bpjsKesEnrolled: (bool) $employee['bpjs_kes_enrolled'],
                bpjsTkEnrolled: (bool) $employee['bpjs_tk_enrolled'],
                isFinalTaxMonth: $isFinal,
                yearToDate: $ytd,
            );

            $result = $calculator->calculate($input);

            $recordedDays = array_sum($days) - $days['holiday'];

            if ($recordedDays < $active) {
                $warnings[] = "Data kehadiran baru {$recordedDays} dari {$active} hari kerja.";
            }

            if (empty($employee['bank_account_no'])) {
                $warnings[] = 'Nomor rekening bank belum diisi.';
            }

            if ($isFinal && $ytd['months'] + 1 < $this->monthsEmployedInYear($employee, (int) $period['year'], (int) $period['month'])) {
                $warnings[] = 'Riwayat PPh 21 tahun berjalan belum lengkap — hasil perhitungan setahun perlu diperiksa.';
            }

            $warnings = [...$result->warnings, ...$warnings];

            $this->storeResult($periodId, $employee, $input, $result, $days, $overtime[$id] ?? [], $warnings, $componentIds, $sortOrder);

            $summary['employees']++;
            $summary['total_earning'] += $result->totalEarning();
            $summary['total_deduction'] += $result->totalDeduction();
            $summary['take_home_pay'] += $result->takeHomePay();
            $summary['pph21'] += $result->pph21;
            $summary['company_contribution'] += $result->companyContribution();

            if ($warnings !== []) {
                $summary['warnings'][$employee['nip'] . ' · ' . $employee['full_name']] = $warnings;
            }
        }

        $this->db->table('payroll_periods')->where('id', $periodId)->update([
            'workdays'       => $periodWorkdays,
            'generated_by'   => $actor['id'] ?? null,
            'generated_at'   => date('Y-m-d H:i:s'),
            'rejection_note' => null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->audit->log('payroll.generate', 'payroll_period', $periodId, sprintf(
            'Generate payroll %s: %d karyawan, total THP %s',
            $period['name'],
            $summary['employees'],
            rupiah($summary['take_home_pay']),
        ), after: array_diff_key($summary, ['warnings' => true]), actor: $actor);

        $this->db->transComplete();

        $summary['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $summary;
    }

    /**
     * Komponen tetap & harian per karyawan. Kunci 0 berisi tarif harian default
     * (untuk karyawan tanpa override).
     *
     * @param list<int> $employeeIds
     *
     * @return array<int, array{fixedEarnings: list<array<string, mixed>>, dailyEarnings: list<array<string, mixed>>, fixedDeductions: list<array<string, mixed>>}>
     */
    public function componentProfiles(array $employeeIds): array
    {
        $components = $this->db->table('salary_components')->where('is_active', 1)->orderBy('sort_order')->get()->getResultArray();
        $daily      = array_values(array_filter($components, static fn (array $c): bool => $c['type'] === 'earning' && $c['calc_type'] === 'daily'));
        $overrides  = [];

        if ($employeeIds !== []) {
            $rows = $this->db->table('employee_components ec')
                ->select('ec.employee_id, ec.amount, sc.code, sc.name, sc.type, sc.calc_type, sc.taxable, sc.is_bpjs_base')
                ->join('salary_components sc', 'sc.id = ec.salary_component_id')
                ->where('sc.is_active', 1)
                ->whereIn('ec.employee_id', $employeeIds)
                ->orderBy('sc.sort_order')
                ->get()->getResultArray();

            foreach ($rows as $row) {
                $overrides[(int) $row['employee_id']][$row['code']] = $row;
            }
        }

        $dailyFor = static function (array $employeeOverrides) use ($daily): array {
            $result = [];

            foreach ($daily as $component) {
                $rate     = (int) ($employeeOverrides[$component['code']]['amount'] ?? $component['default_amount']);
                $result[] = ['code' => $component['code'], 'name' => $component['name'], 'rate' => $rate, 'taxable' => (bool) $component['taxable']];
            }

            return $result;
        };

        $profiles = [0 => ['fixedEarnings' => [], 'dailyEarnings' => $dailyFor([]), 'fixedDeductions' => []]];

        foreach ($employeeIds as $employeeId) {
            $fixedEarnings   = [];
            $fixedDeductions = [];

            foreach ($overrides[$employeeId] ?? [] as $row) {
                if ($row['calc_type'] !== 'fixed') {
                    continue;
                }

                if ($row['type'] === 'earning') {
                    $fixedEarnings[] = [
                        'code'     => $row['code'],
                        'name'     => $row['name'],
                        'amount'   => (int) $row['amount'],
                        'taxable'  => (bool) $row['taxable'],
                        'bpjsBase' => (bool) $row['is_bpjs_base'],
                    ];
                } elseif ($row['type'] === 'deduction') {
                    $fixedDeductions[] = ['code' => $row['code'], 'name' => $row['name'], 'amount' => (int) $row['amount']];
                }
            }

            $profiles[$employeeId] = [
                'fixedEarnings'   => $fixedEarnings,
                'dailyEarnings'   => $dailyFor($overrides[$employeeId] ?? []),
                'fixedDeductions' => $fixedDeductions,
            ];
        }

        return $profiles;
    }

    public function calendar(string $start, string $end): WorkdayCalendar
    {
        $holidays = array_column(
            $this->db->table('holidays')->select('date')->where('date >=', $start)->where('date <=', $end)->get()->getResultArray(),
            'date',
        );

        return new WorkdayCalendar($holidays);
    }

    // ------------------------------------------------------------------
    // Pemuatan data massal
    // ------------------------------------------------------------------

    /**
     * @param list<int> $employeeIds
     *
     * @return array<int, array<string, int>>
     */
    private function attendanceSummary(array $employeeIds, string $start, string $end): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $rows = $this->db->table('attendances')
            ->select('employee_id, status, COUNT(*) AS total')
            ->whereIn('employee_id', $employeeIds)
            ->where('date >=', $start)
            ->where('date <=', $end)
            ->groupBy(['employee_id', 'status'])
            ->get()->getResultArray();

        $summary = [];

        foreach ($rows as $row) {
            $id = (int) $row['employee_id'];
            $summary[$id] ??= array_fill_keys(array_keys(AttendanceModel::STATUSES), 0);
            $summary[$id][$row['status']] = (int) $row['total'];
        }

        return $summary;
    }

    /**
     * @param list<int> $employeeIds
     *
     * @return array<int, list<float>> jam lembur per hari
     */
    private function approvedOvertime(array $employeeIds, string $start, string $end): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $rows = $this->db->table('overtimes')
            ->select('employee_id, date, SUM(hours) AS hours')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->where('date >=', $start)
            ->where('date <=', $end)
            ->groupBy(['employee_id', 'date'])
            ->orderBy('date')
            ->get()->getResultArray();

        $hours = [];

        foreach ($rows as $row) {
            $hours[(int) $row['employee_id']][] = (float) $row['hours'];
        }

        return $hours;
    }

    /**
     * @return array<int, list<array{name: string, type: string, amount: int, taxable: bool, note: string|null}>>
     */
    private function adjustments(int $periodId): array
    {
        $rows = $this->db->table('payroll_adjustments')->where('period_id', $periodId)->orderBy('id')->get()->getResultArray();
        $map  = [];

        foreach ($rows as $row) {
            $map[(int) $row['employee_id']][] = [
                'name'    => $row['name'],
                'type'    => $row['type'],
                'amount'  => (int) $row['amount'],
                'taxable' => (bool) $row['taxable'],
                'note'    => $row['note'],
            ];
        }

        return $map;
    }

    /**
     * Cicilan pinjaman yang jatuh tempo di periode ini, dikurangi potongan yang
     * sudah ada di periode lain yang belum dikunci (agar sisa tidak terpotong dua kali).
     *
     * @param array<string, mixed> $period
     *
     * @return array<int, list<array{loanId: int, loanNo: string, amount: int}>>
     */
    private function loanInstallments(array $period): array
    {
        $periodKey = sprintf('%04d-%02d', $period['year'], $period['month']);

        $loans = $this->db->table('loans')
            ->whereIn('status', ['approved', 'active'])
            ->where('start_period <=', $periodKey)
            ->where('remaining >', 0)
            ->get()->getResultArray();

        if ($loans === []) {
            return [];
        }

        $loanIds = array_map(static fn (array $l): int => (int) $l['id'], $loans);

        $alreadyPaid = array_flip(array_column(
            $this->db->table('loan_installments')->select('loan_id')->whereIn('loan_id', $loanIds)->where('period', $periodKey)->get()->getResultArray(),
            'loan_id',
        ));

        $pendingRows = $this->db->table('payroll_items pi')
            ->select("JSON_UNQUOTE(JSON_EXTRACT(pi.meta, '$.loan_id')) AS loan_id, SUM(pi.amount) AS amount", false)
            ->join('payrolls p', 'p.id = pi.payroll_id')
            ->join('payroll_periods pp', 'pp.id = p.period_id')
            ->where('pi.code', 'LOAN')
            ->where('pp.status !=', PayrollPeriodModel::STATUS_LOCKED)
            ->where('pp.id !=', (int) $period['id'])
            ->groupBy('loan_id')
            ->get()->getResultArray();

        $pending = array_map('intval', array_column($pendingRows, 'amount', 'loan_id'));
        $map     = [];

        foreach ($loans as $loan) {
            $id = (int) $loan['id'];

            if (isset($alreadyPaid[$id])) {
                continue;
            }

            $amount = min((int) $loan['installment'], (int) $loan['remaining'] - ($pending[$id] ?? 0));

            if ($amount > 0) {
                $map[(int) $loan['employee_id']][] = ['loanId' => $id, 'loanNo' => $loan['loan_no'], 'amount' => $amount];
            }
        }

        return $map;
    }

    /**
     * Akumulasi tahun pajak berjalan sebelum bulan periode: saldo awal + payroll bulan-bulan sebelumnya.
     *
     * @param list<int> $employeeIds
     *
     * @return array<int, array{months: int, gross: int, pension: int, pph21: int}>
     */
    private function yearToDate(array $employeeIds, int $year, int $month): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $ytd = [];

        foreach ($this->db->table('tax_ytd_balances')->where('year', $year)->whereIn('employee_id', $employeeIds)->get()->getResultArray() as $row) {
            $ytd[(int) $row['employee_id']] = [
                'months'  => (int) $row['months'],
                'gross'   => (int) $row['gross_income'],
                'pension' => (int) $row['pension'],
                'pph21'   => (int) $row['pph21'],
            ];
        }

        $rows = $this->db->table('payrolls p')
            ->select('p.employee_id, COUNT(*) AS months, SUM(p.bruto_pph) AS gross, SUM(p.pph21) AS pph21')
            ->select("(SELECT COALESCE(SUM(pi.amount), 0) FROM payroll_items pi JOIN payrolls p2 ON p2.id = pi.payroll_id JOIN payroll_periods pp2 ON pp2.id = p2.period_id WHERE p2.employee_id = p.employee_id AND pp2.year = {$year} AND pp2.month < {$month} AND pi.code IN ('JHT_EE','JP_EE')) AS pension", false)
            ->join('payroll_periods pp', 'pp.id = p.period_id')
            ->where('pp.year', $year)
            ->where('pp.month <', $month)
            ->whereIn('p.employee_id', $employeeIds)
            ->groupBy('p.employee_id')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $id        = (int) $row['employee_id'];
            $ytd[$id] ??= ['months' => 0, 'gross' => 0, 'pension' => 0, 'pph21' => 0];
            $ytd[$id]['months'] += (int) $row['months'];
            $ytd[$id]['gross'] += (int) $row['gross'];
            $ytd[$id]['pension'] += (int) $row['pension'];
            $ytd[$id]['pph21'] += (int) $row['pph21'];
        }

        return $ytd;
    }

    /**
     * @param array<string, mixed> $employee
     */
    private function monthsEmployedInYear(array $employee, int $year, int $month): int
    {
        $joinYear  = (int) substr($employee['join_date'], 0, 4);
        $joinMonth = (int) substr($employee['join_date'], 5, 2);
        $firstMonth = $joinYear < $year ? 1 : $joinMonth;

        return max(0, $month - $firstMonth + 1);
    }

    // ------------------------------------------------------------------
    // Penyimpanan
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $employee
     * @param array<string, int>   $days
     * @param list<float>          $overtimeHours
     * @param list<string>         $warnings
     * @param array<string, int>   $componentIds
     * @param array<string, int>   $sortOrder
     */
    private function storeResult(int $periodId, array $employee, EmployeePayrollInput $input, PayrollResult $result, array $days, array $overtimeHours, array $warnings, array $componentIds, array $sortOrder): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('payrolls')->insert([
            'period_id'           => $periodId,
            'employee_id'         => $employee['id'],
            'snapshot'            => json_encode([
                'nip'               => $employee['nip'],
                'full_name'         => $employee['full_name'],
                'nik'               => $employee['nik'],
                'npwp'              => $employee['npwp'],
                'position'          => $employee['position_name'],
                'department'        => $employee['department_name'],
                'department_id'     => (int) $employee['department_id'],
                'employment_type'   => $employee['employment_type'],
                'join_date'         => $employee['join_date'],
                'resign_date'       => $employee['resign_date'],
                'ptkp_status'       => $employee['ptkp_status'],
                'basic_salary'      => (int) $employee['basic_salary'],
                'bank_name'         => $employee['bank_name'],
                'bank_account_no'   => $employee['bank_account_no'],
                'bank_account_name' => $employee['bank_account_name'],
                'prorated'          => $input->isProrated(),
                'annualized'        => $result->annualized,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'workdays'            => $input->periodWorkdays,
            'active_workdays'     => $input->activeWorkdays,
            'present_days'        => $days['present'] + $days['late'],
            'late_days'           => $days['late'],
            'sick_days'           => $days['sick'],
            'leave_days'          => $days['leave'],
            'permit_days'         => $days['permit'],
            'absent_days'         => $days['absent'],
            'overtime_hours'      => number_format(array_sum($overtimeHours), 2, '.', ''),
            'bpjs_base'           => $result->bpjsBase,
            'total_earning'       => $result->totalEarning(),
            'bruto_pph'           => $result->brutoPph,
            'total_deduction'     => $result->totalDeduction(),
            'pph21'               => $result->pph21,
            'ter_category'        => $result->terCategory,
            'ter_rate'            => $result->terRate === null ? null : number_format($result->terRate, 3, '.', ''),
            'bpjs_company_total'  => $result->companyContribution(),
            'bpjs_employee_total' => $result->bpjsEmployeeTotal(),
            'take_home_pay'       => $result->takeHomePay(),
            'warnings'            => $warnings === [] ? null : json_encode($warnings, JSON_UNESCAPED_UNICODE),
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $payrollId = (int) $this->db->insertID();
        $items     = [];

        foreach ($result->lines as $index => $line) {
            $items[] = [
                'payroll_id'          => $payrollId,
                'salary_component_id' => $componentIds[$line->code] ?? null,
                'code'                => $line->code,
                'name'                => $line->name,
                'type'                => $line->type,
                'amount'              => $line->amount,
                'taxable'             => (int) $line->taxable,
                'meta'                => $line->meta === [] ? null : json_encode($line->meta, JSON_UNESCAPED_UNICODE),
                'sort_order'          => ($sortOrder[$line->code] ?? 900) * 10 + $index % 10,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        if ($items !== []) {
            $this->db->table('payroll_items')->insertBatch($items);
        }
    }
}
