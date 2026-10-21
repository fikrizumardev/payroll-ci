<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Hasil hitung gaji satu karyawan.
 */
final class PayrollResult
{
    /**
     * @param list<PayrollLine> $lines
     * @param list<string>      $warnings
     */
    public function __construct(
        public readonly array $lines,
        public readonly int $bpjsBase,
        public readonly int $brutoPph,
        public readonly int $pph21,
        public readonly string $terCategory,
        public readonly ?float $terRate,
        public readonly bool $annualized,
        public readonly array $warnings = [],
    ) {
    }

    public function total(string $type): int
    {
        $total = 0;

        foreach ($this->lines as $line) {
            if ($line->type === $type) {
                $total += $line->amount;
            }
        }

        return $total;
    }

    public function totalEarning(): int
    {
        return $this->total(PayrollLine::EARNING);
    }

    public function totalDeduction(): int
    {
        return $this->total(PayrollLine::DEDUCTION);
    }

    public function companyContribution(): int
    {
        return $this->total(PayrollLine::COMPANY_CONTRIBUTION);
    }

    public function takeHomePay(): int
    {
        return $this->totalEarning() - $this->totalDeduction();
    }

    public function amountOf(string $code): int
    {
        $total = 0;

        foreach ($this->lines as $line) {
            if ($line->code === $code) {
                $total += $line->amount;
            }
        }

        return $total;
    }

    /**
     * Iuran BPJS porsi karyawan (dipotong dari gaji).
     */
    public function bpjsEmployeeTotal(): int
    {
        return $this->amountOf('BPJS_KES_EE') + $this->amountOf('JHT_EE') + $this->amountOf('JP_EE');
    }

    /**
     * Iuran pensiun porsi karyawan (pengurang penghasilan neto setahun).
     */
    public function pensionContribution(): int
    {
        return $this->amountOf('JHT_EE') + $this->amountOf('JP_EE');
    }
}
