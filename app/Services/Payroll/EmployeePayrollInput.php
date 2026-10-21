<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Seluruh data yang dibutuhkan untuk menghitung gaji satu karyawan dalam satu periode.
 * Murni data (tanpa akses database) sehingga kalkulator mudah diuji.
 */
final class EmployeePayrollInput
{
    /**
     * @param list<array{code: string, name: string, amount: int, taxable: bool, bpjsBase: bool}>  $fixedEarnings    tunjangan tetap per bulan
     * @param list<array{code: string, name: string, rate: int, taxable: bool}>                    $dailyEarnings    tunjangan per hari hadir
     * @param list<array{code: string, name: string, amount: int}>                                 $fixedDeductions  potongan tetap per bulan (mis. koperasi)
     * @param list<float>                                                                          $overtimeHours    jam lembur per hari (disetujui)
     * @param list<array{name: string, type: string, amount: int, taxable: bool, note?: string|null}> $adjustments  penyesuaian manual
     * @param list<array{loanId: int, loanNo: string, amount: int}>                                 $loanInstallments cicilan yang dipotong periode ini
     * @param array{months: int, gross: int, pension: int, pph21: int}                             $yearToDate       akumulasi sebelum periode ini (tahun pajak sama)
     */
    public function __construct(
        public readonly int $employeeId,
        public readonly string $ptkpStatus,
        public readonly int $basicSalary,
        public readonly int $periodWorkdays,
        public readonly int $activeWorkdays,
        public readonly int $month,
        public readonly array $fixedEarnings = [],
        public readonly array $dailyEarnings = [],
        public readonly array $fixedDeductions = [],
        public readonly int $presentDays = 0,
        public readonly int $lateDays = 0,
        public readonly int $absentDays = 0,
        public readonly bool $overtimeEligible = false,
        public readonly array $overtimeHours = [],
        public readonly array $adjustments = [],
        public readonly array $loanInstallments = [],
        public readonly bool $bpjsKesEnrolled = true,
        public readonly bool $bpjsTkEnrolled = true,
        public readonly bool $isFinalTaxMonth = false,
        public readonly array $yearToDate = ['months' => 0, 'gross' => 0, 'pension' => 0, 'pph21' => 0],
    ) {
    }

    /**
     * Upah sebulan penuh sesuai kontrak (gaji pokok + tunjangan tetap dasar BPJS),
     * dipakai sebagai dasar BPJS dan upah lembur.
     */
    public function monthlyWage(): int
    {
        $wage = $this->basicSalary;

        foreach ($this->fixedEarnings as $earning) {
            if ($earning['bpjsBase']) {
                $wage += $earning['amount'];
            }
        }

        return $wage;
    }

    public function isProrated(): bool
    {
        return $this->periodWorkdays > 0 && $this->activeWorkdays < $this->periodWorkdays;
    }
}
