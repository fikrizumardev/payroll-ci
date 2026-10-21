<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\PtkpRateModel;
use App\Models\TerRateModel;
use App\Services\SettingService;
use InvalidArgumentException;

/**
 * Seluruh tarif yang dipakai engine payroll, dimuat sekali per proses generate.
 * Dapat dibuat langsung (unit test) atau dari database via fromDatabase().
 */
final class PayrollRates
{
    /**
     * @param array<string, int>                                  $ptkp         status → nominal setahun
     * @param array<string, string>                               $terCategory  status → A|B|C
     * @param array<string, list<array{int, int|null, float}>>    $terBrackets  kategori → [min, max|null, tarif%]
     */
    public function __construct(
        public readonly float $kesCompanyRate = 4.0,
        public readonly float $kesEmployeeRate = 1.0,
        public readonly int $kesMaxWage = 12_000_000,
        public readonly float $jhtCompanyRate = 3.7,
        public readonly float $jhtEmployeeRate = 2.0,
        public readonly float $jkkCompanyRate = 0.24,
        public readonly float $jkmCompanyRate = 0.3,
        public readonly float $jpCompanyRate = 2.0,
        public readonly float $jpEmployeeRate = 1.0,
        public readonly int $jpMaxWage = 10_547_400,
        public readonly int $overtimeDivisor = 173,
        public readonly int $latePenalty = 25_000,
        public readonly float $positionCostRate = 5.0,
        public readonly int $positionCostMaxYear = 6_000_000,
        public readonly array $ptkp = [],
        public readonly array $terCategory = [],
        public readonly array $terBrackets = [],
    ) {
    }

    public static function fromDatabase(?SettingService $settings = null): self
    {
        $settings ??= service('appSettings');

        $ptkp        = [];
        $terCategory = [];

        foreach ((new PtkpRateModel())->findAll() as $row) {
            $ptkp[$row['status']]        = (int) $row['amount'];
            $terCategory[$row['status']] = $row['ter_category'];
        }

        $brackets = [];

        foreach ((new TerRateModel())->orderBy('category')->orderBy('min_income')->findAll() as $row) {
            $brackets[$row['category']][] = [(int) $row['min_income'], $row['max_income'] === null ? null : (int) $row['max_income'], (float) $row['rate']];
        }

        return new self(
            kesCompanyRate: $settings->float('bpjs.kes.company_rate', 4.0),
            kesEmployeeRate: $settings->float('bpjs.kes.employee_rate', 1.0),
            kesMaxWage: $settings->int('bpjs.kes.max_wage', 12_000_000),
            jhtCompanyRate: $settings->float('bpjs.jht.company_rate', 3.7),
            jhtEmployeeRate: $settings->float('bpjs.jht.employee_rate', 2.0),
            jkkCompanyRate: $settings->float('bpjs.jkk.company_rate', 0.24),
            jkmCompanyRate: $settings->float('bpjs.jkm.company_rate', 0.3),
            jpCompanyRate: $settings->float('bpjs.jp.company_rate', 2.0),
            jpEmployeeRate: $settings->float('bpjs.jp.employee_rate', 1.0),
            jpMaxWage: $settings->int('bpjs.jp.max_wage', 10_547_400),
            overtimeDivisor: $settings->int('payroll.overtime_divisor', 173),
            latePenalty: $settings->int('attendance.late_penalty', 25_000),
            positionCostRate: $settings->float('tax.position_cost_rate', 5.0),
            positionCostMaxYear: $settings->int('tax.position_cost_max_year', 6_000_000),
            ptkp: $ptkp,
            terCategory: $terCategory,
            terBrackets: $brackets,
        );
    }

    public function ptkpAmount(string $status): int
    {
        return $this->ptkp[$status] ?? throw new InvalidArgumentException("Status PTKP '{$status}' tidak dikenal.");
    }

    public function terCategoryFor(string $status): string
    {
        return $this->terCategory[$status] ?? throw new InvalidArgumentException("Kategori TER untuk status '{$status}' tidak dikenal.");
    }

    /**
     * Tarif TER (persen) untuk bruto sebulan.
     */
    public function terRate(string $category, int $grossIncome): float
    {
        foreach ($this->terBrackets[$category] ?? [] as [$min, $max, $rate]) {
            if ($grossIncome >= $min && ($max === null || $grossIncome <= $max)) {
                return $rate;
            }
        }

        if (! isset($this->terBrackets[$category])) {
            throw new InvalidArgumentException("Tabel TER kategori '{$category}' belum tersedia.");
        }

        return 0.0;
    }
}
