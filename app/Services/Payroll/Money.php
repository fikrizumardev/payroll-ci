<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Aritmetika rupiah berbasis integer (tanpa float) dengan pembulatan half-up.
 * Tarif persen diubah ke "milli-persen" (2,5% → 2500; 0,24% → 240) agar eksak.
 */
final class Money
{
    /**
     * round(a / b) half-up untuk bilangan bulat (b > 0).
     */
    public static function divRound(int $numerator, int $denominator): int
    {
        if ($numerator < 0) {
            return -self::divRound(-$numerator, $denominator);
        }

        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }

    /**
     * Persen dari nominal: percent(9_000_000, 0.24) → 21_600
     */
    public static function percent(int $amount, float|string $ratePercent): int
    {
        return self::divRound($amount * self::milliPercent($ratePercent), 100_000);
    }

    /**
     * Nominal × pembilang / penyebut (dibulatkan), mis. prorata gaji.
     */
    public static function ratio(int $amount, int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            return 0;
        }

        return self::divRound($amount * $numerator, $denominator);
    }

    public static function milliPercent(float|string $ratePercent): int
    {
        return (int) round((float) $ratePercent * 1000);
    }

    /**
     * Pembulatan ke bawah ke ribuan penuh (aturan PKP).
     */
    public static function floorThousand(int $amount): int
    {
        return $amount <= 0 ? 0 : intdiv($amount, 1000) * 1000;
    }
}
