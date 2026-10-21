<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PayrollPeriodModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Penjaga data periode payroll yang sudah final.
 *
 * Tanggal yang termasuk periode berstatus approved / paid / locked tidak boleh
 * lagi mengubah data sumber payroll (kehadiran, lembur, hari libur), karena
 * hasil hitungnya sudah disetujui atau dibayarkan.
 */
class PayrollPeriodGuard
{
    public const CLOSED_STATUSES = [
        PayrollPeriodModel::STATUS_APPROVED,
        PayrollPeriodModel::STATUS_PAID,
        PayrollPeriodModel::STATUS_LOCKED,
    ];

    /** @var list<array<string, mixed>>|null */
    private ?array $closed = null;

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public static function make(): self
    {
        return new self(db_connect());
    }

    /**
     * Periode tertutup yang memuat tanggal tersebut.
     *
     * @return array<string, mixed>|null
     */
    public function closedPeriodFor(string $date): ?array
    {
        foreach ($this->closedPeriods() as $period) {
            if ($period['start_date'] <= $date && $period['end_date'] >= $date) {
                return $period;
            }
        }

        return null;
    }

    public function isClosed(string $date): bool
    {
        return $this->closedPeriodFor($date) !== null;
    }

    /**
     * Pesan penolakan yang aman ditampilkan, atau null bila tanggal masih boleh diubah.
     */
    public function errorFor(string $date): ?string
    {
        $period = $date === '' ? null : $this->closedPeriodFor($date);

        if ($period === null) {
            return null;
        }

        return "Tanggal ini termasuk periode payroll {$period['name']} yang sudah "
            . strtolower(PayrollPeriodModel::STATUSES[$period['status']]) . '.';
    }

    /**
     * Tanggal akhir periode tertutup terakhir.
     */
    public function lastClosedDate(): ?string
    {
        $periods = $this->closedPeriods();

        return $periods === [] ? null : max(array_column($periods, 'end_date'));
    }

    /**
     * Periode (YYYY-MM) yang sudah tertutup.
     *
     * @return list<string>
     */
    public function closedMonths(): array
    {
        return array_map(static fn (array $p): string => sprintf('%04d-%02d', $p['year'], $p['month']), $this->closedPeriods());
    }

    /**
     * Nama periode draft/review yang sudah di-generate dan memuat salah satu tanggal —
     * hasil payroll-nya perlu di-generate ulang agar perubahan data ikut terhitung.
     *
     * @param list<string> $dates
     *
     * @return list<string>
     */
    public function staleGeneratedPeriods(array $dates): array
    {
        if ($dates === []) {
            return [];
        }

        return array_column($this->db->table('payroll_periods')
            ->select('name')
            ->whereIn('status', [PayrollPeriodModel::STATUS_DRAFT, PayrollPeriodModel::STATUS_REVIEW])
            ->where('generated_at IS NOT NULL')
            ->where('start_date <=', max($dates))
            ->where('end_date >=', min($dates))
            ->orderBy('start_date')
            ->get()->getResultArray(), 'name');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function closedPeriods(): array
    {
        return $this->closed ??= $this->db->table('payroll_periods')
            ->select('id, name, year, month, start_date, end_date, status')
            ->whereIn('status', self::CLOSED_STATUSES)
            ->orderBy('start_date')
            ->get()->getResultArray();
    }
}
