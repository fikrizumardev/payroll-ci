<?php

declare(strict_types=1);

/**
 * Helper format tampilan untuk konteks Indonesia: rupiah, tanggal, terbilang.
 */

if (! function_exists('rupiah')) {
    /**
     * rupiah(9574785) → "Rp 9.574.785", rupiah(-5000) → "-Rp 5.000"
     */
    function rupiah(int|float|string|null $amount, bool $withPrefix = true): string
    {
        $value     = (int) round((float) ($amount ?? 0));
        $formatted = number_format(abs($value), 0, ',', '.');

        if ($withPrefix) {
            $formatted = 'Rp ' . $formatted;
        }

        return $value < 0 ? '-' . $formatted : $formatted;
    }
}

if (! function_exists('number_id')) {
    /**
     * Angka dengan pemisah ribuan titik dan desimal koma: 1234.5 → "1.234,5"
     */
    function number_id(int|float|string|null $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals, ',', '.');
    }
}

if (! function_exists('percent_id')) {
    /**
     * Persentase tanpa nol berlebih: 2.5 → "2,5%", 0.24 → "0,24%", 4 → "4%"
     */
    function percent_id(int|float|string|null $value): string
    {
        $formatted = rtrim(rtrim(number_format((float) ($value ?? 0), 3, ',', '.'), '0'), ',');

        return $formatted . '%';
    }
}

if (! function_exists('bulan_id')) {
    function bulan_id(int $month, bool $short = false): string
    {
        $long = [
            1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        $abbr = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        if ($month < 1 || $month > 12) {
            return '';
        }

        return $short ? $abbr[$month] : $long[$month];
    }
}

if (! function_exists('hari_id')) {
    /**
     * @param int $isoDay 1 (Senin) … 7 (Minggu)
     */
    function hari_id(int $isoDay): string
    {
        $days = [1 => 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        return $days[$isoDay] ?? '';
    }
}

if (! function_exists('tanggal_id')) {
    /**
     * Format tanggal Indonesia.
     *
     * - short:    21 Okt 2026
     * - long:     21 Oktober 2026
     * - full:     Rabu, 21 Oktober 2026
     * - datetime: 21 Okt 2026, 14:05
     * - month:    Oktober 2026
     */
    function tanggal_id(DateTimeInterface|string|null $date, string $format = 'short'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        try {
            $dt = $date instanceof DateTimeInterface ? $date : new DateTimeImmutable($date);
        } catch (Exception) {
            return '—';
        }

        $day   = (int) $dt->format('j');
        $month = (int) $dt->format('n');
        $year  = $dt->format('Y');

        return match ($format) {
            'long'     => sprintf('%d %s %s', $day, bulan_id($month), $year),
            'full'     => sprintf('%s, %d %s %s', hari_id((int) $dt->format('N')), $day, bulan_id($month), $year),
            'datetime' => sprintf('%d %s %s, %s', $day, bulan_id($month, true), $year, $dt->format('H:i')),
            'month'    => sprintf('%s %s', bulan_id($month), $year),
            default    => sprintf('%d %s %s', $day, bulan_id($month, true), $year),
        };
    }
}

if (! function_exists('periode_id')) {
    /**
     * periode_id(2026, 10) → "Oktober 2026"
     */
    function periode_id(int $year, int $month, bool $short = false): string
    {
        return bulan_id($month, $short) . ' ' . $year;
    }
}

if (! function_exists('terbilang')) {
    /**
     * Ejaan angka dalam Bahasa Indonesia (huruf kecil).
     * terbilang(9574785) → "sembilan juta lima ratus tujuh puluh empat ribu tujuh ratus delapan puluh lima"
     */
    function terbilang(int|string $number): string
    {
        $number = (int) $number;

        if ($number === 0) {
            return 'nol';
        }

        if ($number < 0) {
            return 'minus ' . terbilang(-$number);
        }

        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        $spell = static function (int $n) use (&$spell, $words): string {
            return match (true) {
                $n < 12                 => $words[$n],
                $n < 20                 => $words[$n - 10] . ' belas',
                $n < 100                => trim($words[intdiv($n, 10)] . ' puluh ' . $spell($n % 10)),
                $n < 200                => trim('seratus ' . $spell($n - 100)),
                $n < 1_000              => trim($words[intdiv($n, 100)] . ' ratus ' . $spell($n % 100)),
                $n < 2_000              => trim('seribu ' . $spell($n - 1_000)),
                $n < 1_000_000          => trim($spell(intdiv($n, 1_000)) . ' ribu ' . $spell($n % 1_000)),
                $n < 1_000_000_000      => trim($spell(intdiv($n, 1_000_000)) . ' juta ' . $spell($n % 1_000_000)),
                $n < 1_000_000_000_000  => trim($spell(intdiv($n, 1_000_000_000)) . ' miliar ' . $spell($n % 1_000_000_000)),
                default                 => trim($spell(intdiv($n, 1_000_000_000_000)) . ' triliun ' . $spell($n % 1_000_000_000_000)),
            };
        };

        return preg_replace('/\s+/', ' ', $spell($number));
    }
}

if (! function_exists('terbilang_rupiah')) {
    /**
     * terbilang_rupiah(1500000) → "Satu juta lima ratus ribu rupiah"
     */
    function terbilang_rupiah(int|string $amount): string
    {
        return ucfirst(terbilang($amount)) . ' rupiah';
    }
}
