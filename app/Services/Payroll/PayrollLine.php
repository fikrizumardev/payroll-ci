<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Satu baris slip gaji.
 *
 * `taxable`:
 * - earning              → menambah bruto PPh 21
 * - company_contribution → menambah bruto PPh 21 (BPJS Kes, JKK, JKM)
 * - deduction            → mengurangi bruto PPh 21 (mis. potongan alpha/telat)
 */
final class PayrollLine
{
    public const EARNING              = 'earning';
    public const DEDUCTION            = 'deduction';
    public const COMPANY_CONTRIBUTION = 'company_contribution';

    /**
     * @param array<string, mixed> $meta dasar perhitungan (qty, rate, basis, …)
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $type,
        public readonly int $amount,
        public readonly bool $taxable = false,
        public readonly array $meta = [],
    ) {
    }
}
