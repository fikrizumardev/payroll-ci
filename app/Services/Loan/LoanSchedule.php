<?php

declare(strict_types=1);

namespace App\Services\Loan;

use DateTimeImmutable;

/**
 * Jadwal cicilan pinjaman: gabungan cicilan yang sudah dibukukan, potongan yang
 * sedang berjalan di payroll belum terkunci, dan proyeksi bulan-bulan berikutnya.
 * Murni (tanpa database) sehingga mudah diuji.
 */
final class LoanSchedule
{
    public const PAID      = 'paid';      // sudah dibukukan (periode terkunci / di luar sistem)
    public const DEDUCTING = 'deducting'; // sudah terpotong di payroll yang belum dikunci
    public const SCHEDULED = 'scheduled'; // proyeksi

    /**
     * @param array{amount: int, installment: int, start_period: string}                         $loan
     * @param list<array{period: string, amount: int, paid_at?: string|null, installment_no?: int}> $installments cicilan terbukukan
     * @param list<array{period: string, amount: int, status: string}>                            $deductions   potongan di payroll belum terkunci
     *
     * @return list<array{no: int, period: string, amount: int, state: string, paid_at: string|null, period_status: string|null, balance: int}>
     */
    public static function build(array $loan, array $installments, array $deductions = []): array
    {
        $rows      = [];
        $balance   = (int) $loan['amount'];
        $covered   = [];
        $lastMonth = null;

        usort($installments, static fn (array $a, array $b): int => strcmp($a['period'], $b['period']));
        usort($deductions, static fn (array $a, array $b): int => strcmp($a['period'], $b['period']));

        foreach ($installments as $installment) {
            $balance -= (int) $installment['amount'];
            $covered[$installment['period']] = true;
            $lastMonth = max($lastMonth ?? '', $installment['period']);
            $rows[]    = self::row(count($rows) + 1, $installment['period'], (int) $installment['amount'], self::PAID, $balance, $installment['paid_at'] ?? null);
        }

        foreach ($deductions as $deduction) {
            if (isset($covered[$deduction['period']]) || $balance <= 0) {
                continue;
            }

            $amount  = min((int) $deduction['amount'], $balance);
            $balance -= $amount;
            $covered[$deduction['period']] = true;
            $lastMonth = max($lastMonth ?? '', $deduction['period']);
            $rows[]    = self::row(count($rows) + 1, $deduction['period'], $amount, self::DEDUCTING, $balance, null, $deduction['status']);
        }

        $month = $lastMonth === null ? $loan['start_period'] : max($loan['start_period'], self::nextMonth($lastMonth));

        // Pengaman: tenor maks. 36 bulan, proyeksi tidak lebih dari 120 baris
        for ($guard = 0; $balance > 0 && (int) $loan['installment'] > 0 && $guard < 120; $guard++) {
            $amount  = min((int) $loan['installment'], $balance);
            $balance -= $amount;
            $rows[]  = self::row(count($rows) + 1, $month, $amount, self::SCHEDULED, $balance);
            $month   = self::nextMonth($month);
        }

        return $rows;
    }

    /**
     * Periode cicilan terakhir (YYYY-MM) menurut jadwal, atau null bila jadwal kosong.
     *
     * @param list<array{period: string}> $schedule
     */
    public static function endPeriod(array $schedule): ?string
    {
        return $schedule === [] ? null : end($schedule)['period'];
    }

    public static function nextMonth(string $period): string
    {
        return (new DateTimeImmutable($period . '-01'))->modify('+1 month')->format('Y-m');
    }

    /**
     * @return array{no: int, period: string, amount: int, state: string, paid_at: string|null, period_status: string|null, balance: int}
     */
    private static function row(int $no, string $period, int $amount, string $state, int $balance, ?string $paidAt = null, ?string $periodStatus = null): array
    {
        return [
            'no'            => $no,
            'period'        => $period,
            'amount'        => $amount,
            'state'         => $state,
            'paid_at'       => $paidAt,
            'period_status' => $periodStatus,
            'balance'       => max(0, $balance),
        ];
    }
}
