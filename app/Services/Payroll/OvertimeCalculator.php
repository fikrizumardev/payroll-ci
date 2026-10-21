<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Upah lembur hari kerja (PP 35/2021):
 *   upah sejam = 1/173 × upah sebulan
 *   jam ke-1 = 1,5 × upah sejam; jam ke-2 dst. = 2 × upah sejam (dihitung per hari lembur)
 */
final class OvertimeCalculator
{
    public function __construct(private readonly int $divisor = 173)
    {
    }

    /**
     * Total "pengali jam" dalam satuan 1/10.000 (mis. 3 jam → 1,5 + 2 + 2 = 5,5 → 55.000).
     *
     * @param list<float> $hoursPerDay
     */
    public static function multiplierUnits(array $hoursPerDay): int
    {
        $units = 0;

        foreach ($hoursPerDay as $hours) {
            $centiHours = max(0, (int) round($hours * 100));
            $units += min($centiHours, 100) * 150 + max($centiHours - 100, 0) * 200;
        }

        return $units;
    }

    /**
     * @param list<float> $hoursPerDay
     */
    public function calculate(int $monthlyWage, array $hoursPerDay): ?PayrollLine
    {
        $units = self::multiplierUnits($hoursPerDay);

        if ($units === 0 || $monthlyWage <= 0) {
            return null;
        }

        $amount = Money::divRound($monthlyWage * $units, $this->divisor * 10_000);

        return new PayrollLine('OVERTIME', 'Lembur', PayrollLine::EARNING, $amount, true, [
            'hours'       => round(array_sum($hoursPerDay), 2),
            'days'        => count($hoursPerDay),
            'multiplier'  => $units / 10_000,
            'hourly_rate' => Money::divRound($monthlyWage, $this->divisor),
            'basis'       => $monthlyWage,
        ]);
    }
}
