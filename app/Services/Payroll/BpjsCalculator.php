<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Iuran BPJS Kesehatan & Ketenagakerjaan (JHT, JKK, JKM, JP).
 * Dasar iuran = upah sebulan sesuai kontrak (tidak diprorata), dengan batas atas
 * upah untuk BPJS Kesehatan dan Jaminan Pensiun.
 */
final class BpjsCalculator
{
    public function __construct(private readonly PayrollRates $rates)
    {
    }

    /**
     * @return list<PayrollLine>
     */
    public function calculate(int $wage, bool $kesEnrolled = true, bool $tkEnrolled = true): array
    {
        if ($wage <= 0) {
            return [];
        }

        $r       = $this->rates;
        $kesBase = min($wage, $r->kesMaxWage);
        $jpBase  = min($wage, $r->jpMaxWage);
        $lines   = [];

        $line = static fn (string $code, string $name, string $type, int $base, float $rate, bool $taxable): PayrollLine => new PayrollLine(
            $code,
            $name,
            $type,
            Money::percent($base, $rate),
            $taxable,
            ['basis' => $base, 'rate' => $rate],
        );

        if ($kesEnrolled) {
            $lines[] = $line('BPJS_KES_ER', 'BPJS Kesehatan (Perusahaan)', PayrollLine::COMPANY_CONTRIBUTION, $kesBase, $r->kesCompanyRate, true);
            $lines[] = $line('BPJS_KES_EE', 'BPJS Kesehatan (Karyawan)', PayrollLine::DEDUCTION, $kesBase, $r->kesEmployeeRate, false);
        }

        if ($tkEnrolled) {
            // JKK & JKM ditanggung perusahaan dan menjadi objek PPh 21
            $lines[] = $line('JKK_ER', 'BPJS JKK (Perusahaan)', PayrollLine::COMPANY_CONTRIBUTION, $wage, $r->jkkCompanyRate, true);
            $lines[] = $line('JKM_ER', 'BPJS JKM (Perusahaan)', PayrollLine::COMPANY_CONTRIBUTION, $wage, $r->jkmCompanyRate, true);
            // JHT & JP porsi perusahaan bukan objek PPh 21
            $lines[] = $line('JHT_ER', 'BPJS JHT (Perusahaan)', PayrollLine::COMPANY_CONTRIBUTION, $wage, $r->jhtCompanyRate, false);
            $lines[] = $line('JHT_EE', 'BPJS JHT (Karyawan)', PayrollLine::DEDUCTION, $wage, $r->jhtEmployeeRate, false);
            $lines[] = $line('JP_ER', 'BPJS Jaminan Pensiun (Perusahaan)', PayrollLine::COMPANY_CONTRIBUTION, $jpBase, $r->jpCompanyRate, false);
            $lines[] = $line('JP_EE', 'BPJS Jaminan Pensiun (Karyawan)', PayrollLine::DEDUCTION, $jpBase, $r->jpEmployeeRate, false);
        }

        return $lines;
    }
}
