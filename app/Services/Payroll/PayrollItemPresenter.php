<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Keterangan dasar hitung setiap baris payroll_items (dari kolom meta),
 * mis. "22 hari × Rp 30.000" atau "4% × Rp 9.000.000".
 */
final class PayrollItemPresenter
{
    /**
     * @param array{code: string, meta?: array<string, mixed>|null} $item
     */
    public static function describe(array $item): ?string
    {
        $meta = $item['meta'] ?? [];

        if (! is_array($meta) || $meta === []) {
            return null;
        }

        $code = $item['code'];

        if ($code === 'OVERTIME') {
            return sprintf(
                '%s jam dalam %d hari · pengali %s× · upah sejam %s',
                self::number($meta['hours'] ?? 0),
                (int) ($meta['days'] ?? 0),
                self::number($meta['multiplier'] ?? 0),
                rupiah($meta['hourly_rate'] ?? 0),
            );
        }

        if ($code === 'PPH21' || $code === 'PPH21_REFUND') {
            return ($meta['method'] ?? null) === 'annual'
                ? 'Perhitungan setahun (masa pajak terakhir)'
                : sprintf('TER %s %s × bruto %s', $meta['category'] ?? '', percent_id($meta['rate'] ?? 0), rupiah($meta['basis'] ?? 0));
        }

        if ($code === 'LOAN') {
            return null; // nomor pinjaman sudah tercantum pada nama baris
        }

        if (isset($meta['qty'], $meta['rate'])) {
            return sprintf('%d %s × %s', (int) $meta['qty'], $meta['unit'] ?? '', rupiah($meta['rate']));
        }

        if (isset($meta['basis'], $meta['rate'])) {
            return sprintf('%s × %s', percent_id($meta['rate']), rupiah($meta['basis']));
        }

        if (! empty($meta['prorated'])) {
            return isset($meta['active_workdays'], $meta['period_workdays'])
                ? sprintf('Prorata %d/%d hari kerja dari %s', $meta['active_workdays'], $meta['period_workdays'], rupiah($meta['monthly'] ?? 0))
                : 'Prorata dari ' . rupiah($meta['monthly'] ?? 0);
        }

        return isset($meta['note']) && $meta['note'] !== '' ? (string) $meta['note'] : null;
    }

    private static function number(int|float|string $value): string
    {
        return rtrim(rtrim(number_id((float) $value, 2), '0'), ',');
    }
}
