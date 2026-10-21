<?php

declare(strict_types=1);

namespace Tests\Support\Payroll;

use App\Database\Seeds\TerRateSeeder;
use App\Services\Payroll\EmployeePayrollInput;
use App\Services\Payroll\PayrollRates;

/**
 * Tarif & input standar untuk unit test engine payroll (tanpa database).
 */
final class RatesFixture
{
    public static function rates(): PayrollRates
    {
        $brackets = [];

        foreach (TerRateSeeder::TABLES as $category => $rows) {
            $min = 0;

            foreach ($rows as [$max, $rate]) {
                $brackets[$category][] = [$min, $max, (float) $rate];
                $min = $max === null ? $min : $max + 1;
            }
        }

        return new PayrollRates(
            ptkp: [
                'TK/0' => 54_000_000, 'TK/1' => 58_500_000, 'TK/2' => 63_000_000, 'TK/3' => 67_500_000,
                'K/0'  => 58_500_000, 'K/1' => 63_000_000, 'K/2' => 67_500_000, 'K/3' => 72_000_000,
            ],
            terCategory: [
                'TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A',
                'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B',
                'K/3'  => 'C',
            ],
            terBrackets: $brackets,
        );
    }

    /**
     * Karyawan hadir penuh 21 hari kerja dengan tunjangan standar PayrollKu.
     *
     * @param array<string, mixed> $overrides argumen bernama EmployeePayrollInput
     */
    public static function input(int $basic, int $positionAllowance, int $commAllowance, string $ptkp, array $overrides = []): EmployeePayrollInput
    {
        $fixed = [];

        if ($positionAllowance > 0) {
            $fixed[] = ['code' => 'POS_ALW', 'name' => 'Tunjangan Jabatan', 'amount' => $positionAllowance, 'taxable' => true, 'bpjsBase' => true];
        }

        if ($commAllowance > 0) {
            $fixed[] = ['code' => 'COMM_ALW', 'name' => 'Tunjangan Komunikasi', 'amount' => $commAllowance, 'taxable' => true, 'bpjsBase' => true];
        }

        return new EmployeePayrollInput(...$overrides + [
            'employeeId'     => 1,
            'ptkpStatus'     => $ptkp,
            'basicSalary'    => $basic,
            'periodWorkdays' => 21,
            'activeWorkdays' => 21,
            'month'          => 9,
            'fixedEarnings'  => $fixed,
            'dailyEarnings'  => [
                ['code' => 'TRANSPORT', 'name' => 'Tunjangan Transport', 'rate' => 30_000, 'taxable' => true],
                ['code' => 'MEAL', 'name' => 'Uang Makan', 'rate' => 35_000, 'taxable' => true],
            ],
            'presentDays'    => 21,
        ]);
    }
}
