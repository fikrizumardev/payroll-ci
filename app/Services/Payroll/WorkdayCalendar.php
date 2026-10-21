<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;

/**
 * Kalender hari kerja: Senin–Jumat, dikurangi tanggal libur.
 */
final class WorkdayCalendar
{
    /** @var array<string, true> */
    private readonly array $holidays;

    /**
     * @param list<string> $holidays tanggal libur (Y-m-d)
     */
    public function __construct(array $holidays = [])
    {
        $this->holidays = array_fill_keys($holidays, true);
    }

    public function isWorkday(string $date): bool
    {
        return (int) (new DateTimeImmutable($date))->format('N') <= 5 && ! isset($this->holidays[$date]);
    }

    /**
     * @return list<string>
     */
    public function workdays(string $start, string $end): array
    {
        if ($end < $start) {
            return [];
        }

        $days   = [];
        $period = new DatePeriod(new DateTimeImmutable($start), new DateInterval('P1D'), (new DateTimeImmutable($end))->modify('+1 day'));

        foreach ($period as $day) {
            $date = $day->format('Y-m-d');

            if ((int) $day->format('N') <= 5 && ! isset($this->holidays[$date])) {
                $days[] = $date;
            }
        }

        return $days;
    }

    public function count(string $start, string $end): int
    {
        return count($this->workdays($start, $end));
    }

    /**
     * Hari kerja di periode selama karyawan berstatus bekerja (memperhitungkan masuk/keluar).
     */
    public function activeWorkdays(string $periodStart, string $periodEnd, string $joinDate, ?string $resignDate): int
    {
        $start = max($periodStart, $joinDate);
        $end   = $resignDate === null ? $periodEnd : min($periodEnd, $resignDate);

        return $this->count($start, $end);
    }

    /**
     * Hari kerja terakhir pada atau sebelum tanggal tersebut.
     */
    public function previousWorkday(string $date): string
    {
        $day = new DateTimeImmutable($date);

        while (! $this->isWorkday($day->format('Y-m-d'))) {
            $day = $day->modify('-1 day');
        }

        return $day->format('Y-m-d');
    }
}
