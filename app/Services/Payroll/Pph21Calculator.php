<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * PPh 21 pegawai tetap — PP 58/2023 & PMK 168/2023 (metode gross).
 *
 * - Masa Januari–November: bruto sebulan × tarif TER sesuai kategori PTKP.
 * - Masa pajak terakhir (Desember, atau bulan terakhir bekerja): dihitung ulang
 *   setahun dengan tarif Pasal 17, lalu dikurangi PPh yang sudah dipotong.
 */
final class Pph21Calculator
{
    /** Lapisan tarif Pasal 17 UU HPP: [batas atas lapisan, tarif %]. */
    private const ARTICLE_17_BRACKETS = [
        [60_000_000, 5],
        [250_000_000, 15],
        [500_000_000, 25],
        [5_000_000_000, 30],
        [null, 35],
    ];

    public function __construct(private readonly PayrollRates $rates)
    {
    }

    /**
     * @return array{pph21: int, category: string, rate: float}
     */
    public function monthly(int $grossIncome, string $ptkpStatus): array
    {
        $category = $this->rates->terCategoryFor($ptkpStatus);
        $rate     = $this->rates->terRate($category, max(0, $grossIncome));

        return [
            'pph21'    => Money::percent(max(0, $grossIncome), $rate),
            'category' => $category,
            'rate'     => $rate,
        ];
    }

    /**
     * Perhitungan setahun pada masa pajak terakhir.
     *
     * @param int $grossYear         bruto setahun (termasuk bulan ini)
     * @param int $pensionYear       iuran JHT + JP karyawan setahun
     * @param int $months            jumlah bulan bekerja di tahun pajak (untuk batas biaya jabatan)
     * @param int $pph21AlreadyPaid  PPh 21 yang sudah dipotong bulan-bulan sebelumnya
     *
     * @return array<string, int> rincian; `pph21` = PPh bulan ini (negatif = lebih bayar)
     */
    public function annual(int $grossYear, int $pensionYear, int $months, string $ptkpStatus, int $pph21AlreadyPaid): array
    {
        $months       = max(1, min(12, $months));
        $positionCost = min(
            Money::percent($grossYear, $this->rates->positionCostRate),
            Money::ratio($this->rates->positionCostMaxYear, $months, 12),
        );
        $netIncome   = $grossYear - $positionCost - $pensionYear;
        $ptkp        = $this->rates->ptkpAmount($ptkpStatus);
        $taxable     = Money::floorThousand($netIncome - $ptkp);
        $pph21Annual = self::article17($taxable);

        return [
            'gross_year'     => $grossYear,
            'position_cost'  => $positionCost,
            'pension_year'   => $pensionYear,
            'net_income'     => $netIncome,
            'ptkp'           => $ptkp,
            'taxable_income' => $taxable,
            'pph21_annual'   => $pph21Annual,
            'pph21_paid'     => $pph21AlreadyPaid,
            'pph21'          => $pph21Annual - $pph21AlreadyPaid,
            'months'         => $months,
        ];
    }

    /**
     * PPh terutang setahun menurut tarif progresif Pasal 17.
     */
    public static function article17(int $taxableIncome): int
    {
        $tax   = 0;
        $lower = 0;

        foreach (self::ARTICLE_17_BRACKETS as [$upper, $rate]) {
            if ($taxableIncome <= $lower) {
                break;
            }

            $portion = ($upper === null ? $taxableIncome : min($taxableIncome, $upper)) - $lower;
            $tax += Money::percent($portion, $rate);
            $lower = $upper ?? $taxableIncome;
        }

        return $tax;
    }
}
