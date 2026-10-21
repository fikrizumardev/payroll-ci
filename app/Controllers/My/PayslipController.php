<?php

declare(strict_types=1);

namespace App\Controllers\My;

use App\Controllers\BaseController;
use App\Services\Payslip\PayslipService;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Daftar slip gaji milik karyawan yang login (hanya periode dibayar/terkunci).
 */
class PayslipController extends BaseController
{
    public function index(): RedirectResponse|string
    {
        $employeeId = (int) ($this->currentUser()['employee_id'] ?? 0);

        if ($employeeId === 0) {
            return redirect()->route('dashboard')->with('warning', 'Akun Anda tidak terhubung dengan data karyawan.');
        }

        $all   = PayslipService::make()->listForEmployee($employeeId);
        $years = array_values(array_unique(array_column($all, 'year')));
        $year  = (int) ($this->request->getGet('year') ?: ($years[0] ?? date('Y')));
        $slips = array_values(array_filter($all, static fn (array $s): bool => $s['year'] === $year));

        return view('my/payslips', [
            'title'       => 'Slip Gaji Saya',
            'breadcrumbs' => [['label' => 'Slip Gaji Saya']],
            'slips'       => $slips,
            'years'       => $years,
            'year'        => $year,
            'totals'      => [
                'thp'      => array_sum(array_column($slips, 'take_home_pay')),
                'earning'  => array_sum(array_column($slips, 'total_earning')),
                'pph21'    => array_sum(array_column($slips, 'pph21')),
                'months'   => count($slips),
            ],
        ]);
    }
}
