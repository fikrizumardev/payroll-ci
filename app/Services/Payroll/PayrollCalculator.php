<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Orkestrator perhitungan gaji satu karyawan (murni, tanpa database):
 * pendapatan → lembur → BPJS → potongan → PPh 21.
 */
final class PayrollCalculator
{
    private readonly EarningCalculator $earnings;
    private readonly OvertimeCalculator $overtime;
    private readonly BpjsCalculator $bpjs;
    private readonly DeductionCalculator $deductions;
    private readonly Pph21Calculator $pph21;

    public function __construct(private readonly PayrollRates $rates)
    {
        $this->earnings   = new EarningCalculator();
        $this->overtime   = new OvertimeCalculator($rates->overtimeDivisor);
        $this->bpjs       = new BpjsCalculator($rates);
        $this->deductions = new DeductionCalculator($rates);
        $this->pph21      = new Pph21Calculator($rates);
    }

    public function calculate(EmployeePayrollInput $input): PayrollResult
    {
        $warnings = [];
        $wage     = $input->monthlyWage();
        $lines    = $this->earnings->calculate($input);

        if ($input->overtimeHours !== []) {
            if ($input->overtimeEligible) {
                $overtime = $this->overtime->calculate($wage, $input->overtimeHours);

                if ($overtime !== null) {
                    $lines[] = $overtime;
                }
            } else {
                $warnings[] = 'Terdapat lembur disetujui, tetapi jabatan tidak berhak lembur — lembur tidak dibayarkan.';
            }
        }

        $lines = [
            ...$lines,
            ...$this->bpjs->calculate($wage, $input->bpjsKesEnrolled, $input->bpjsTkEnrolled),
            ...$this->deductions->calculate($input),
        ];

        $bruto = self::grossForTax($lines);
        $tax   = $this->tax($input, $bruto, $lines);
        $lines = [...$lines, ...$tax['lines']];

        $takeHomePay = 0;

        foreach ($lines as $line) {
            $takeHomePay += match ($line->type) {
                PayrollLine::EARNING   => $line->amount,
                PayrollLine::DEDUCTION => -$line->amount,
                default                => 0,
            };
        }

        if ($takeHomePay < 0) {
            $warnings[] = 'Take home pay bernilai negatif — periksa potongan karyawan ini.';
        }

        return new PayrollResult(
            lines: $lines,
            bpjsBase: $wage,
            brutoPph: $bruto,
            pph21: $tax['pph21'],
            terCategory: $tax['category'],
            terRate: $tax['rate'],
            annualized: $input->isFinalTaxMonth,
            warnings: $warnings,
        );
    }

    /**
     * Bruto PPh 21 = pendapatan kena pajak + premi BPJS Kes/JKK/JKM perusahaan
     * − potongan yang mengurangi upah (alpha, keterlambatan).
     *
     * @param list<PayrollLine> $lines
     */
    public static function grossForTax(array $lines): int
    {
        $gross = 0;

        foreach ($lines as $line) {
            if (! $line->taxable) {
                continue;
            }

            $gross += $line->type === PayrollLine::DEDUCTION ? -$line->amount : $line->amount;
        }

        return max(0, $gross);
    }

    /**
     * @param list<PayrollLine> $lines
     *
     * @return array{pph21: int, category: string, rate: float|null, lines: list<PayrollLine>}
     */
    private function tax(EmployeePayrollInput $input, int $bruto, array $lines): array
    {
        $category = $this->rates->terCategoryFor($input->ptkpStatus);

        if ($input->isFinalTaxMonth) {
            $pension = 0;

            foreach ($lines as $line) {
                if (in_array($line->code, ['JHT_EE', 'JP_EE'], true)) {
                    $pension += $line->amount;
                }
            }

            $annual = $this->pph21->annual(
                $input->yearToDate['gross'] + $bruto,
                $input->yearToDate['pension'] + $pension,
                $input->yearToDate['months'] + 1,
                $input->ptkpStatus,
                $input->yearToDate['pph21'],
            );
            $pph21 = $annual['pph21'];
            $rate  = null;
            $meta  = ['method' => 'annual'] + $annual;
        } else {
            $monthly = $this->pph21->monthly($bruto, $input->ptkpStatus);
            $pph21   = $monthly['pph21'];
            $rate    = $monthly['rate'];
            $meta    = ['method' => 'ter', 'basis' => $bruto, 'category' => $category, 'rate' => $rate];
        }

        $taxLines = [];

        if ($pph21 > 0) {
            $taxLines[] = new PayrollLine('PPH21', 'PPh 21', PayrollLine::DEDUCTION, $pph21, false, $meta);
        } elseif ($pph21 < 0) {
            // Lebih potong di masa pajak terakhir dikembalikan ke karyawan
            $taxLines[] = new PayrollLine('PPH21_REFUND', 'Pengembalian Kelebihan PPh 21', PayrollLine::EARNING, -$pph21, false, $meta);
        }

        return ['pph21' => $pph21, 'category' => $category, 'rate' => $rate, 'lines' => $taxLines];
    }
}
