<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AttendanceModel;
use App\Models\AuditLogModel;
use App\Models\EmployeeModel;
use App\Models\HolidayModel;
use App\Models\LoanModel;
use App\Models\OvertimeModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Dashboard berbasis data nyata: ringkasan payroll, tren biaya, biaya per departemen,
 * kehadiran, dan aktivitas (perusahaan) serta ringkasan pribadi (karyawan).
 */
class DashboardController extends BaseController
{
    /** Jumlah periode payroll terakhir pada grafik tren. */
    private const TREND_PERIODS = 6;

    public function index(): RedirectResponse|string
    {
        $user  = $this->currentUser();
        $today = date('Y-m-d');

        // Karyawan memakai dashboard pribadi (/my)
        if (! $this->auth->hasRole('hr', 'finance') && ! empty($user['employee_id'])) {
            return redirect()->route('my.dashboard');
        }

        $data = [
            'title'    => 'Dashboard',
            'user'     => $user,
            'today'    => $today,
            'holidays' => (new HolidayModel())->where('date >=', $today)->orderBy('date')->findAll(3),
        ];

        if ($this->auth->hasRole('hr', 'finance')) {
            $data += $this->companySummary($today);
            $data['payroll'] = $this->payrollSummary();
            $data['people']  = $this->peopleHighlights($today);
        }

        if (! empty($user['employee_id'])) {
            $data['personal'] = $this->personalSummary((int) $user['employee_id'], $today);
        }

        return view('dashboard/index', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function companySummary(string $today): array
    {
        $employees  = new EmployeeModel();
        $monthStart = date('Y-m-01', strtotime($today));

        $active    = $employees->active()->countAllResults();
        $newHires  = $employees->active()->where('join_date >=', $monthStart)->countAllResults();
        $byType    = array_column(
            $employees->select('employment_type, COUNT(*) AS total')->active()->groupBy('employment_type')->findAll(),
            'total',
            'employment_type',
        );

        // Hari kerja terakhir yang sudah punya data kehadiran
        $attendance = new AttendanceModel();
        $lastDate   = $attendance->selectMax('date')->where('date <=', $today)->first()['date'] ?? null;
        $lastDay    = [];
        $trend      = [];

        if ($lastDate !== null) {
            foreach ($attendance->select('status, COUNT(*) AS total')->where('date', $lastDate)->groupBy('status')->findAll() as $row) {
                $lastDay[$row['status']] = (int) $row['total'];
            }

            // Tingkat kehadiran 10 hari kerja terakhir untuk sparkline
            $rows = $attendance->select("date, SUM(status IN ('present','late')) AS present, COUNT(*) AS total", false)
                ->where('date <=', $lastDate)
                ->groupBy('date')
                ->orderBy('date', 'DESC')
                ->findAll(10);

            foreach (array_reverse($rows) as $row) {
                $trend[] = round(((int) $row['present'] / max((int) $row['total'], 1)) * 100, 1);
            }
        }

        $overtime = (new OvertimeModel())->select('COUNT(*) AS total, COALESCE(SUM(hours), 0) AS total_hours')
            ->where('status', 'pending')->first();

        $loans = (new LoanModel())->select('COUNT(*) AS total, COALESCE(SUM(remaining), 0) AS remaining')
            ->whereIn('status', ['approved', 'active'])->first();

        return [
            'company' => [
                'activeEmployees' => $active,
                'newHires'        => $newHires,
                'byType'          => $byType,
                'lastDate'        => $lastDate,
                'lastDay'         => $lastDay,
                'attendanceTrend' => $trend,
                'pendingOvertime' => ['total' => (int) $overtime['total'], 'hours' => (float) $overtime['total_hours']],
                'activeLoans'     => ['total' => (int) $loans['total'], 'remaining' => (int) $loans['remaining']],
                'pendingLoans'    => (new LoanModel())->where('status', 'pending')->countAllResults(),
            ],
            'activities' => (new AuditLogModel())->recent(6),
        ];
    }

    /**
     * Ringkasan payroll: tren beberapa periode terakhir, KPI periode terbaru
     * beserta perubahan terhadap periode sebelumnya, dan biaya per departemen.
     *
     * @return array<string, mixed>
     */
    private function payrollSummary(): array
    {
        $db = db_connect();

        $periods = array_reverse($db->table('payroll_periods pp')
            ->select('pp.id, pp.name, pp.year, pp.month, pp.status, pp.pay_date, COUNT(p.id) AS employees')
            ->select('COALESCE(SUM(p.take_home_pay), 0) AS thp, COALESCE(SUM(p.pph21), 0) AS pph21')
            ->select('COALESCE(SUM(p.bpjs_employee_total), 0) AS bpjs_employee, COALESCE(SUM(p.bpjs_company_total), 0) AS bpjs_company')
            ->select('COALESCE(SUM(p.total_earning), 0) AS total_earning')
            ->join('payrolls p', 'p.period_id = pp.id', 'left')
            ->groupBy('pp.id')
            ->orderBy('pp.year', 'DESC')->orderBy('pp.month', 'DESC')
            ->limit(self::TREND_PERIODS)
            ->get()->getResultArray());

        foreach ($periods as &$period) {
            foreach (['employees', 'thp', 'pph21', 'bpjs_employee', 'bpjs_company', 'total_earning'] as $key) {
                $period[$key] = (int) $period[$key];
            }

            // Biaya perusahaan = seluruh pendapatan karyawan + iuran BPJS porsi perusahaan
            $period['employer_cost'] = $period['total_earning'] + $period['bpjs_company'];
        }
        unset($period);

        $latest   = $periods === [] ? null : $periods[array_key_last($periods)];
        $previous = count($periods) > 1 ? $periods[count($periods) - 2] : null;
        $delta    = static fn (string $key): ?float => $previous === null || $previous[$key] === 0
            ? null
            : round(($latest[$key] - $previous[$key]) / $previous[$key] * 100, 1);

        $departments = [];

        if ($latest !== null) {
            $departments = $db->table('payrolls')
                ->select("JSON_UNQUOTE(JSON_EXTRACT(snapshot, '$.department')) AS department", false)
                ->select('COUNT(*) AS employees, SUM(total_earning + bpjs_company_total) AS cost, SUM(take_home_pay) AS thp')
                ->where('period_id', $latest['id'])
                ->groupBy('department')
                ->orderBy('cost', 'DESC')
                ->get()->getResultArray();
        }

        return [
            'periods'     => $periods,
            'latest'      => $latest,
            'previous'    => $previous,
            'deltas'      => $latest === null ? [] : [
                'thp'           => $delta('thp'),
                'pph21'         => $delta('pph21'),
                'employer_cost' => $delta('employer_cost'),
            ],
            'departments' => array_map(static fn (array $row): array => [
                'department' => $row['department'],
                'employees'  => (int) $row['employees'],
                'cost'       => (int) $row['cost'],
                'thp'        => (int) $row['thp'],
            ], $departments),
        ];
    }

    /**
     * Karyawan baru & yang berulang tahun bulan ini.
     *
     * @return array{newHires: list<array<string, mixed>>, birthdays: list<array<string, mixed>>}
     */
    private function peopleHighlights(string $today): array
    {
        $month = (int) date('n', strtotime($today));

        return [
            'newHires' => (new EmployeeModel())->withRelations()->active()
                ->where('employees.join_date >=', date('Y-m-d', strtotime($today . ' -90 days')))
                ->orderBy('employees.join_date', 'DESC')
                ->findAll(3),
            'birthdays' => (new EmployeeModel())->withRelations()->active()
                ->where('MONTH(employees.birth_date)', $month, false)
                ->orderBy('DAY(employees.birth_date)', 'ASC', false)
                ->findAll(5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personalSummary(int $employeeId, string $today): array
    {
        $monthStart = date('Y-m-01', strtotime($today));

        // Alias berbeda dari kolom `hours` agar tidak terkena cast model (SUM bisa NULL)
        $overtimeHours = (new OvertimeModel())->select('COALESCE(SUM(hours), 0) AS total_hours')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('date >=', $monthStart)
            ->first()['total_hours'] ?? 0;

        $loan = (new LoanModel())->where('employee_id', $employeeId)
            ->whereIn('status', ['approved', 'active'])
            ->orderBy('id', 'DESC')
            ->first();

        return [
            'employee'   => (new EmployeeModel())->withRelations()->find($employeeId),
            'attendance' => (new AttendanceModel())->summary($employeeId, $monthStart, $today),
            'overtime'   => (float) $overtimeHours,
            'loan'       => $loan,
        ];
    }
}
