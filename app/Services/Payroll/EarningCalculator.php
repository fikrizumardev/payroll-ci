<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Pendapatan: gaji pokok & tunjangan tetap (prorata bila masuk/keluar di tengah periode),
 * tunjangan harian × hari hadir, dan penyesuaian manual bertipe pendapatan.
 */
final class EarningCalculator
{
    /**
     * @return list<PayrollLine>
     */
    public function calculate(EmployeePayrollInput $input): array
    {
        $lines    = [];
        $prorated = $input->isProrated();
        $prorate  = static fn (int $amount): int => $prorated
            ? Money::ratio($amount, $input->activeWorkdays, $input->periodWorkdays)
            : $amount;

        $lines[] = new PayrollLine('BASIC', 'Gaji Pokok', PayrollLine::EARNING, $prorate($input->basicSalary), true, array_filter([
            'monthly'         => $input->basicSalary,
            'prorated'        => $prorated ?: null,
            'active_workdays' => $prorated ? $input->activeWorkdays : null,
            'period_workdays' => $prorated ? $input->periodWorkdays : null,
        ], static fn ($value) => $value !== null));

        foreach ($input->fixedEarnings as $earning) {
            if ($earning['amount'] <= 0) {
                continue;
            }

            $lines[] = new PayrollLine($earning['code'], $earning['name'], PayrollLine::EARNING, $prorate($earning['amount']), $earning['taxable'], array_filter([
                'monthly'  => $earning['amount'],
                'prorated' => $prorated ?: null,
            ], static fn ($value) => $value !== null));
        }

        foreach ($input->dailyEarnings as $daily) {
            if ($daily['rate'] <= 0 || $input->presentDays <= 0) {
                continue;
            }

            $lines[] = new PayrollLine($daily['code'], $daily['name'], PayrollLine::EARNING, $daily['rate'] * $input->presentDays, $daily['taxable'], [
                'rate' => $daily['rate'],
                'qty'  => $input->presentDays,
                'unit' => 'hari',
            ]);
        }

        foreach ($input->adjustments as $adjustment) {
            if ($adjustment['type'] === PayrollLine::EARNING && $adjustment['amount'] > 0) {
                $lines[] = new PayrollLine('ADJ', $adjustment['name'], PayrollLine::EARNING, $adjustment['amount'], $adjustment['taxable'], array_filter([
                    'note' => $adjustment['note'] ?? null,
                ]));
            }
        }

        return $lines;
    }
}
