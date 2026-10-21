<?php

declare(strict_types=1);

namespace App\Controllers\My;

use App\Controllers\BaseController;
use App\Models\AttendanceModel;
use App\Models\EmployeeModel;
use App\Models\HolidayModel;
use App\Models\LoanModel;
use App\Models\OvertimeModel;
use App\Services\Payroll\WorkdayCalendar;
use App\Services\Payslip\PayslipService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Dashboard pribadi karyawan: take home pay terakhir, kehadiran bulan ini,
 * lembur, sisa pinjaman, dan slip gaji terbaru. Hanya data milik sendiri.
 */
class DashboardController extends BaseController
{
    public function index(): RedirectResponse|string
    {
        $user       = $this->currentUser();
        $employeeId = (int) ($user['employee_id'] ?? 0);

        if ($employeeId === 0) {
            return redirect()->route('dashboard')->with('warning', 'Akun Anda tidak terhubung dengan data karyawan.');
        }

        $today      = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');
        $slips      = PayslipService::make()->listForEmployee($employeeId);
        $yearSlips  = array_filter($slips, static fn (array $s): bool => $s['year'] === (int) date('Y'));

        $holidays = (new HolidayModel())->datesBetween($monthStart, $monthEnd);
        $calendar = new WorkdayCalendar($holidays);
        $employee = (new EmployeeModel())->withRelations()->find($employeeId);

        $overtime = (new OvertimeModel())
            ->select('status, COUNT(*) AS total, COALESCE(SUM(hours), 0) AS total_hours')
            ->where('employee_id', $employeeId)
            ->where('date >=', $monthStart)->where('date <=', $monthEnd)
            ->groupBy('status')
            ->findAll();

        return view('my/dashboard', [
            'title'       => 'Dashboard Saya',
            'user'        => $user,
            'employee'    => $employee,
            'today'       => $today,
            'latest'      => $slips[0] ?? null,
            'previous'    => $slips[1] ?? null,
            'recent'      => array_slice($slips, 0, 3),
            'ytd'         => [
                'thp'    => array_sum(array_column($yearSlips, 'take_home_pay')),
                'pph21'  => array_sum(array_column($yearSlips, 'pph21')),
                'months' => count($yearSlips),
            ],
            'attendance'  => (new AttendanceModel())->where('employee_id', $employeeId)->where('date >=', $monthStart)->where('date <=', $monthEnd)->findAll(),
            'summary'     => (new AttendanceModel())->summary($employeeId, $monthStart, $today),
            'workdaysDue' => $calendar->activeWorkdays($monthStart, $today, $employee['join_date'], $employee['resign_date']),
            'holidays'    => array_column((new HolidayModel())->where('date >=', $monthStart)->where('date <=', $monthEnd)->findAll(), 'name', 'date'),
            'upcoming'    => (new HolidayModel())->where('date >=', $today)->orderBy('date')->findAll(3),
            'overtime'    => array_column($overtime, null, 'status'),
            'loans'       => (new LoanModel())->where('employee_id', $employeeId)->whereIn('status', LoanModel::OPEN_STATUSES)->orderBy('id', 'DESC')->findAll(),
        ]);
    }
}
