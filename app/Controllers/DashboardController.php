<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AttendanceModel;
use App\Models\AuditLogModel;
use App\Models\EmployeeModel;
use App\Models\HolidayModel;
use App\Models\LoanModel;
use App\Models\OvertimeModel;

/**
 * Dashboard ringkas berbasis data nyata. Grafik & ringkasan payroll
 * ditambahkan bersama modul payroll (TASKS T-701).
 */
class DashboardController extends BaseController
{
    public function index(): string
    {
        $user  = $this->currentUser();
        $today = date('Y-m-d');

        $data = [
            'title'    => 'Dashboard',
            'user'     => $user,
            'today'    => $today,
            'holidays' => (new HolidayModel())->where('date >=', $today)->orderBy('date')->findAll(3),
        ];

        if ($this->auth->hasRole('hr', 'finance')) {
            $data += $this->companySummary($today);
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
            ],
            'activities' => (new AuditLogModel())->recent(6),
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
