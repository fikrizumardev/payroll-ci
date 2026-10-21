<?php

declare(strict_types=1);

namespace App\Services\Attendance;

use App\Models\AttendanceModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Import kehadiran dari CSV: baca → validasi per baris → pratinjau → simpan.
 *
 * Kolom (header wajib, urutan bebas, huruf besar/kecil bebas):
 *   nip;nama;tanggal;status;jam_masuk;jam_pulang;keterangan
 * `nama` hanya informasi dan tidak dibaca. Pemisah `;` atau `,` dideteksi otomatis.
 */
class AttendanceImporter
{
    public const MAX_ROWS = 2000;

    public const COLUMNS = ['nip', 'nama', 'tanggal', 'status', 'jam_masuk', 'jam_pulang', 'keterangan'];

    private const REQUIRED = ['nip', 'tanggal', 'status'];

    /** Alias status yang diterima selain kode sistem. */
    private const STATUS_ALIASES = [
        'h' => 'present', 'hadir' => 'present', 'masuk' => 'present',
        't' => 'late', 'telat' => 'late', 'terlambat' => 'late',
        's' => 'sick', 'sakit' => 'sick',
        'c' => 'leave', 'cuti' => 'leave',
        'i' => 'permit', 'izin' => 'permit', 'ijin' => 'permit',
        'a' => 'absent', 'alpha' => 'absent', 'alpa' => 'absent', 'mangkir' => 'absent',
        'l' => 'holiday', 'libur' => 'holiday',
    ];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AttendanceService $attendance,
    ) {
    }

    public static function make(): self
    {
        return new self(db_connect(), AttendanceService::make());
    }

    public static function parseStatus(string $value): ?string
    {
        $value = strtolower(trim($value));

        if (array_key_exists($value, AttendanceModel::STATUSES)) {
            return $value;
        }

        return self::STATUS_ALIASES[$value] ?? null;
    }

    /**
     * "2026-10-05", "05/10/2026", "5-10-2026" → "2026-10-05"; selain itu null.
     */
    public static function parseDate(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $value, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /**
     * Template CSV berisi karyawan aktif untuk satu tanggal.
     *
     * @param list<array{nip: string, full_name: string}> $employees
     */
    public static function template(array $employees, string $date): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNS, ';');

        foreach ($employees as $employee) {
            fputcsv($handle, [$employee['nip'], $employee['full_name'], $date, 'hadir', '08:00', '17:00', ''], ';');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Baca CSV menjadi baris bernama kolom.
     *
     * @return list<array{line: int, values: array<string, string>}>
     *
     * @throws RuntimeException header tidak valid / file kosong / terlalu besar
     */
    public function read(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", (string) $content);
        $lines   = explode("\n", $content);
        $first   = '';

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $first = $line;
                break;
            }
        }

        if ($first === '') {
            throw new RuntimeException('File CSV kosong.');
        }

        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $header    = null;
        $rows      = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = array_map('trim', str_getcsv($line, $delimiter, '"', ''));

            if ($header === null) {
                $header  = array_map(static fn (string $h): string => str_replace([' ', '-'], '_', strtolower($h)), $cells);
                $missing = array_diff(self::REQUIRED, $header);

                if ($missing !== []) {
                    throw new RuntimeException('Kolom wajib tidak ditemukan: ' . implode(', ', $missing) . '. Gunakan template yang disediakan.');
                }

                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('File melebihi ' . number_id(self::MAX_ROWS) . ' baris. Pecah menjadi beberapa file.');
            }

            $values = [];

            foreach ($header as $position => $column) {
                $values[$column] = (string) ($cells[$position] ?? '');
            }

            $rows[] = ['line' => $index + 1, 'values' => $values];
        }

        if ($rows === []) {
            throw new RuntimeException('File CSV tidak berisi data kehadiran.');
        }

        return $rows;
    }

    /**
     * Validasi setiap baris dan tentukan aksinya (baru / ubah / tetap / error).
     *
     * @param list<array{line: int, values: array<string, string>}> $rows
     *
     * @return array{rows: list<array<string, mixed>>, summary: array{total: int, valid: int, errors: int, insert: int, update: int, same: int}}
     */
    public function preview(array $rows, ?string $today = null): array
    {
        $nips      = array_values(array_unique(array_filter(array_map(static fn (array $r): string => strtoupper(trim($r['values']['nip'] ?? '')), $rows))));
        $employees = [];

        if ($nips !== []) {
            foreach ($this->db->table('employees')->select('id, nip, full_name, join_date, resign_date')->whereIn('nip', $nips)->where('deleted_at', null)->get()->getResultArray() as $employee) {
                $employees[$employee['nip']] = $employee;
            }
        }

        $parsed = [];

        foreach ($rows as $row) {
            $values   = $row['values'];
            $nip      = strtoupper(trim($values['nip'] ?? ''));
            $employee = $employees[$nip] ?? null;
            $date     = self::parseDate($values['tanggal'] ?? '');
            $status   = self::parseStatus($values['status'] ?? '');
            $errors   = [];

            if ($nip === '') {
                $errors[] = 'NIP kosong.';
            } elseif ($employee === null) {
                $errors[] = "NIP {$nip} tidak terdaftar.";
            }

            if ($date === null) {
                $errors[] = 'Tanggal tidak valid (gunakan YYYY-MM-DD atau DD/MM/YYYY).';
            } elseif ($employee !== null && ($date < $employee['join_date'] || ($employee['resign_date'] !== null && $date > $employee['resign_date']))) {
                $errors[] = 'Karyawan belum/tidak lagi bekerja pada tanggal ini.';
            }

            if ($status === null) {
                $errors[] = 'Status "' . ($values['status'] ?? '') . '" tidak dikenal.';
            }

            $data = null;

            if ($errors === []) {
                $result = $this->attendance->normalize((int) $employee['id'], $date, [
                    'status'    => $status,
                    'check_in'  => $values['jam_masuk'] ?? '',
                    'check_out' => $values['jam_pulang'] ?? '',
                    'note'      => $values['keterangan'] ?? '',
                ], $today);

                $data   = $result['data'];
                $errors = array_values($result['errors']);
            }

            $parsed[] = [
                'line'     => $row['line'],
                'nip'      => $nip,
                'name'     => $employee['full_name'] ?? ($values['nama'] ?? ''),
                'date'     => $date ?? ($values['tanggal'] ?? ''),
                'raw'      => $values,
                'data'     => $data,
                'errors'   => $errors,
                'action'   => $errors === [] ? null : 'error',
            ];
        }

        // Baris ganda (NIP + tanggal sama) di file: baris berikutnya ditandai error
        $seen = [];

        foreach ($parsed as &$row) {
            if ($row['data'] === null) {
                continue;
            }

            $key = $row['data']['employee_id'] . '|' . $row['data']['date'];

            if (isset($seen[$key])) {
                $row['errors'][] = "Duplikat dengan baris {$seen[$key]}.";
                $row['action']   = 'error';
                $row['data']     = null;

                continue;
            }

            $seen[$key] = $row['line'];
        }
        unset($row);

        $valid    = array_values(array_filter(array_column($parsed, 'data')));
        $existing = $this->attendance->existing(array_column($valid, 'employee_id'), array_column($valid, 'date'));
        $summary  = ['total' => count($parsed), 'valid' => 0, 'errors' => 0, 'insert' => 0, 'update' => 0, 'same' => 0];

        foreach ($parsed as &$row) {
            if ($row['data'] !== null) {
                $row['action']   = AttendanceService::changeType($row['data'], $existing[$row['data']['employee_id'] . '|' . $row['data']['date']] ?? null);
                $row['existing'] = $existing[$row['data']['employee_id'] . '|' . $row['data']['date']]['status'] ?? null;
                $summary['valid']++;
                $summary[$row['action']]++;
            } else {
                $summary['errors']++;
            }
        }
        unset($row);

        return ['rows' => $parsed, 'summary' => $summary];
    }

    /**
     * Rentang tanggal data valid (untuk ringkasan/audit).
     *
     * @param list<array<string, mixed>> $rows baris pratinjau
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function dateRange(array $rows): array
    {
        $dates = array_column(array_values(array_filter(array_column($rows, 'data'))), 'date');

        if ($dates === []) {
            return [null, null];
        }

        sort($dates);

        return [reset($dates), end($dates)];
    }
}
