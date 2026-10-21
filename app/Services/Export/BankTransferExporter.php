<?php

declare(strict_types=1);

namespace App\Services\Export;

use CodeIgniter\Database\BaseConnection;

/**
 * File transfer gaji per bank (CSV) dari hasil payroll satu periode.
 *
 * Data rekening diambil dari snapshot payroll (rekening saat payroll dihitung),
 * bukan data karyawan terkini, agar file sesuai dengan yang disetujui.
 */
class BankTransferExporter
{
    public const COLUMNS = ['no', 'nip', 'nama_penerima', 'no_rekening', 'bank', 'nominal', 'berita'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public static function make(): self
    {
        return new self(db_connect());
    }

    /**
     * Berita transfer, mis. "Gaji Okt 2026".
     *
     * @param array{year: int|string, month: int|string} $period
     */
    public static function remark(array $period): string
    {
        return 'Gaji ' . periode_id((int) $period['year'], (int) $period['month'], true);
    }

    /**
     * Penerima transfer (take home pay > 0), diurutkan per bank lalu NIP.
     *
     * @return list<array{nip: string, name: string, bank: string, account_no: string, account_name: string, amount: int}>
     */
    public function recipients(int $periodId): array
    {
        $rows = $this->db->table('payrolls')
            ->select('snapshot, take_home_pay')
            ->where('period_id', $periodId)
            ->where('take_home_pay >', 0)
            ->get()->getResultArray();

        $recipients = [];

        foreach ($rows as $row) {
            $snapshot     = json_decode((string) $row['snapshot'], true) ?: [];
            $recipients[] = [
                'nip'          => (string) ($snapshot['nip'] ?? ''),
                'name'         => (string) ($snapshot['full_name'] ?? ''),
                'bank'         => trim((string) ($snapshot['bank_name'] ?? '')),
                'account_no'   => preg_replace('/\D/', '', (string) ($snapshot['bank_account_no'] ?? '')),
                'account_name' => trim((string) ($snapshot['bank_account_name'] ?? '')),
                'amount'       => (int) $row['take_home_pay'],
            ];
        }

        usort($recipients, static fn (array $a, array $b): int => [$a['bank'], $a['nip']] <=> [$b['bank'], $b['nip']]);

        return $recipients;
    }

    /**
     * Ringkasan per bank; penerima tanpa bank/rekening dikumpulkan terpisah.
     *
     * @param list<array<string, mixed>> $recipients
     *
     * @return array{banks: array<string, array{count: int, total: int}>, missing: list<array<string, mixed>>}
     */
    public static function summary(array $recipients): array
    {
        $banks   = [];
        $missing = [];

        foreach ($recipients as $recipient) {
            if ($recipient['bank'] === '' || $recipient['account_no'] === '') {
                $missing[] = $recipient;

                continue;
            }

            $banks[$recipient['bank']] ??= ['count' => 0, 'total' => 0];
            $banks[$recipient['bank']]['count']++;
            $banks[$recipient['bank']]['total'] += $recipient['amount'];
        }

        ksort($banks);

        return ['banks' => $banks, 'missing' => $missing];
    }

    /**
     * CSV standar (pemisah koma, UTF-8 tanpa BOM) agar mudah diunggah ke internet banking.
     *
     * @param list<array<string, mixed>> $recipients
     * @param array<string, mixed>       $period
     */
    public static function csv(array $recipients, array $period, ?string $bank = null): string
    {
        $remark = self::remark($period);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::COLUMNS, ',', '"', '');

        $no = 0;

        foreach ($recipients as $recipient) {
            if ($recipient['bank'] === '' || $recipient['account_no'] === '' || ($bank !== null && strcasecmp($recipient['bank'], $bank) !== 0)) {
                continue;
            }

            fputcsv($handle, [
                ++$no,
                $recipient['nip'],
                $recipient['account_name'] !== '' ? $recipient['account_name'] : mb_strtoupper($recipient['name']),
                $recipient['account_no'],
                $recipient['bank'],
                $recipient['amount'],
                $remark,
            ], ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
