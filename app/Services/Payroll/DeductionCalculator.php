<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Potongan di luar BPJS & PPh 21: alpha, keterlambatan, potongan tetap,
 * cicilan pinjaman, dan penyesuaian manual bertipe potongan.
 */
final class DeductionCalculator
{
    public function __construct(private readonly PayrollRates $rates)
    {
    }

    /**
     * @return list<PayrollLine>
     */
    public function calculate(EmployeePayrollInput $input): array
    {
        $lines = [];

        if ($input->absentDays > 0 && $input->periodWorkdays > 0) {
            // Potongan alpha mengurangi upah yang diterima → mengurangi bruto PPh 21
            $lines[] = new PayrollLine('ABSENT_DED', 'Potongan Alpha', PayrollLine::DEDUCTION,
                Money::ratio($input->basicSalary, $input->absentDays, $input->periodWorkdays), true, [
                    'qty'   => $input->absentDays,
                    'unit'  => 'hari',
                    'basis' => $input->basicSalary,
                    'rate'  => Money::ratio($input->basicSalary, 1, $input->periodWorkdays),
                ]);
        }

        if ($input->lateDays > 0 && $this->rates->latePenalty > 0) {
            $lines[] = new PayrollLine('LATE_PEN', 'Denda Keterlambatan', PayrollLine::DEDUCTION,
                $input->lateDays * $this->rates->latePenalty, true, [
                    'qty'  => $input->lateDays,
                    'unit' => 'hari',
                    'rate' => $this->rates->latePenalty,
                ]);
        }

        foreach ($input->fixedDeductions as $deduction) {
            if ($deduction['amount'] > 0) {
                $lines[] = new PayrollLine($deduction['code'], $deduction['name'], PayrollLine::DEDUCTION, $deduction['amount']);
            }
        }

        foreach ($input->loanInstallments as $loan) {
            if ($loan['amount'] > 0) {
                $lines[] = new PayrollLine('LOAN', 'Cicilan Pinjaman ' . $loan['loanNo'], PayrollLine::DEDUCTION, $loan['amount'], false, [
                    'loan_id' => $loan['loanId'],
                    'loan_no' => $loan['loanNo'],
                ]);
            }
        }

        foreach ($input->adjustments as $adjustment) {
            if ($adjustment['type'] === PayrollLine::DEDUCTION && $adjustment['amount'] > 0) {
                $lines[] = new PayrollLine('ADJ_DED', $adjustment['name'], PayrollLine::DEDUCTION, $adjustment['amount'], $adjustment['taxable'], array_filter([
                    'note' => $adjustment['note'] ?? null,
                ]));
            }
        }

        return $lines;
    }
}
