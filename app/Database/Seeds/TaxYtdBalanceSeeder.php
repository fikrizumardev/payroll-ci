<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Services\Payroll\EmployeePayrollInput;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollService;
use App\Services\SettingService;
use CodeIgniter\Database\Seeder;

/**
 * Saldo awal PPh 21 Januari–Juli 2026 (periode sebelum PayrollKu dipakai).
 *
 * Disimulasikan dengan engine payroll yang sama: hadir penuh setiap hari kerja,
 * tanpa lembur/potongan. Dibutuhkan agar perhitungan setahun pada masa pajak
 * terakhir (resign / Desember) memakai bruto dan PPh setahun penuh.
 */
class TaxYtdBalanceSeeder extends Seeder
{
    public const YEAR          = 2026;
    public const THROUGH_MONTH = 7;

    public function run(): void
    {
        $service    = new PayrollService($this->db, new SettingService(), service('auditLogger'));
        $calculator = new PayrollCalculator($service->rates());
        $firstDay   = sprintf('%04d-%02d-01', self::YEAR, self::THROUGH_MONTH + 1);

        $employees = $this->db->table('employees')->where('join_date <', $firstDay)->orderBy('nip')->get()->getResultArray();
        $profiles  = $service->componentProfiles(array_map(static fn (array $e): int => (int) $e['id'], $employees));
        $now       = date('Y-m-d H:i:s');
        $rows      = [];

        foreach ($employees as $employee) {
            $totals = ['months' => 0, 'gross' => 0, 'pension' => 0, 'pph21' => 0];

            for ($month = 1; $month <= self::THROUGH_MONTH; $month++) {
                $start = sprintf('%04d-%02d-01', self::YEAR, $month);
                $end   = date('Y-m-t', strtotime($start));

                if ($employee['join_date'] > $end) {
                    continue;
                }

                $calendar = $service->calendar($start, $end);
                $workdays = $calendar->count($start, $end);
                $active   = $calendar->activeWorkdays($start, $end, $employee['join_date'], null);
                $profile  = $profiles[(int) $employee['id']];

                $result = $calculator->calculate(new EmployeePayrollInput(
                    employeeId: (int) $employee['id'],
                    ptkpStatus: $employee['ptkp_status'],
                    basicSalary: (int) $employee['basic_salary'],
                    periodWorkdays: $workdays,
                    activeWorkdays: $active,
                    month: $month,
                    fixedEarnings: $profile['fixedEarnings'],
                    dailyEarnings: $profile['dailyEarnings'],
                    fixedDeductions: $profile['fixedDeductions'],
                    presentDays: $active,
                    bpjsKesEnrolled: (bool) $employee['bpjs_kes_enrolled'],
                    bpjsTkEnrolled: (bool) $employee['bpjs_tk_enrolled'],
                ));

                $totals['months']++;
                $totals['gross'] += $result->brutoPph;
                $totals['pension'] += $result->pensionContribution();
                $totals['pph21'] += $result->pph21;
            }

            if ($totals['months'] === 0) {
                continue;
            }

            $rows[] = [
                'employee_id'   => $employee['id'],
                'year'          => self::YEAR,
                'through_month' => self::THROUGH_MONTH,
                'months'        => $totals['months'],
                'gross_income'  => $totals['gross'],
                'pension'       => $totals['pension'],
                'pph21'         => $totals['pph21'],
                'note'          => 'Saldo awal migrasi Januari–Juli 2026',
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if ($rows !== []) {
            $this->db->table('tax_ytd_balances')->insertBatch($rows);
        }
    }
}
