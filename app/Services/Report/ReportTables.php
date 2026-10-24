<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Models\AttendanceModel;
use App\Models\EmployeeModel;

/**
 * Definisi tabel ekspor (kolom + baris) setiap laporan untuk ReportExporter.
 * Satu definisi dipakai bersama oleh CSV dan XLSX agar isinya selalu sama.
 *
 * @phpstan-type Column array{label: string, type?: string, width?: int}
 * @phpstan-type Row array{type?: string, cells: list<int|float|string|null>}
 * @phpstan-type Table array{columns: list<Column>, rows: list<Row>}
 */
final class ReportTables
{
    /**
     * @param array<string, mixed> $recap hasil ReportService::payrollRecap()
     *
     * @return Table
     */
    public static function payroll(array $recap): array
    {
        $columns = [
            ['label' => 'NIP', 'type' => 'text', 'width' => 11],
            ['label' => 'Nama', 'type' => 'text', 'width' => 26],
            ['label' => 'Departemen', 'type' => 'text', 'width' => 18],
            ['label' => 'Jabatan', 'type' => 'text', 'width' => 24],
            ['label' => 'Status Kerja', 'type' => 'text', 'width' => 12],
            ['label' => 'PTKP', 'type' => 'text', 'width' => 7],
            ['label' => 'Hari Kerja', 'type' => 'int', 'width' => 8],
            ['label' => 'Hari Hadir', 'type' => 'int', 'width' => 8],
        ];

        foreach (ReportService::RECAP_COLUMNS as $label) {
            $columns[] = ['label' => $label, 'type' => 'money', 'width' => 15];
        }

        $rows = [];

        foreach ($recap['groups'] as $group) {
            $rows[] = ['type' => 'group', 'cells' => [$group['department'] . ' (' . count($group['rows']) . ' karyawan)']];

            foreach ($group['rows'] as $row) {
                $rows[] = ['cells' => [
                    $row['nip'], $row['name'], $row['department'], $row['position'],
                    EmployeeModel::EMPLOYMENT_TYPES[$row['employment_type']] ?? $row['employment_type'],
                    $row['ptkp_status'], $row['workdays'], $row['present_days'],
                    ...array_values($row['amounts']),
                ]];
            }

            $rows[] = ['type' => 'subtotal', 'cells' => ['', 'Subtotal ' . $group['department'], $group['department'], '', '', '', null, null, ...array_values($group['subtotal'])]];
        }

        $rows[] = ['type' => 'total', 'cells' => ['', 'TOTAL ' . $recap['employees'] . ' KARYAWAN', '', '', '', '', null, null, ...array_values($recap['total'])]];

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @param array<string, mixed> $bpjs hasil ReportService::bpjs()
     *
     * @return Table
     */
    public static function bpjs(array $bpjs): array
    {
        $columns = [
            ['label' => 'NIP', 'type' => 'text', 'width' => 11],
            ['label' => 'Nama', 'type' => 'text', 'width' => 26],
            ['label' => 'NIK', 'type' => 'id', 'width' => 19],
            ['label' => 'Upah Dasar', 'type' => 'money', 'width' => 14],
        ];

        foreach (ReportService::BPJS_PROGRAMS as $key => $program) {
            $short = strtoupper($key === 'kes' ? 'Kesehatan' : $key);

            if ($program['er'] !== null) {
                $columns[] = ['label' => "{$short} Perusahaan", 'type' => 'money', 'width' => 14];
            }

            if ($program['ee'] !== null) {
                $columns[] = ['label' => "{$short} Karyawan", 'type' => 'money', 'width' => 14];
            }
        }

        $columns[] = ['label' => 'Total Perusahaan', 'type' => 'money', 'width' => 15];
        $columns[] = ['label' => 'Total Karyawan', 'type' => 'money', 'width' => 15];
        $columns[] = ['label' => 'Total Iuran', 'type' => 'money', 'width' => 15];

        $rows = [];

        foreach ($bpjs['rows'] as $row) {
            $cells = [$row['nip'], $row['name'], $row['nik'], $row['wage']];

            foreach (ReportService::BPJS_PROGRAMS as $key => $program) {
                if ($program['er'] !== null) {
                    $cells[] = $row['programs'][$key]['company'];
                }

                if ($program['ee'] !== null) {
                    $cells[] = $row['programs'][$key]['employee'];
                }
            }

            $rows[] = ['cells' => [...$cells, $row['company'], $row['employee'], $row['company'] + $row['employee']]];
        }

        $total = ['', 'TOTAL ' . count($bpjs['rows']) . ' PESERTA', '', null];

        foreach ($bpjs['programs'] as $program) {
            if ($program['er'] !== null) {
                $total[] = $program['company'];
            }

            if ($program['ee'] !== null) {
                $total[] = $program['employee'];
            }
        }

        $rows[] = ['type' => 'total', 'cells' => [...$total, $bpjs['total']['company'], $bpjs['total']['employee'], $bpjs['total']['total']]];

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Kolom mengikuti isian bukti potong bulanan e-Bupot 21 (BPMP).
     *
     * @param array<string, mixed> $pph  hasil ReportService::pph21()
     * @param array<string, mixed> $period
     *
     * @return Table
     */
    public static function pph21(array $pph, array $period): array
    {
        $columns = [
            ['label' => 'Masa Pajak', 'type' => 'int', 'width' => 7],
            ['label' => 'Tahun Pajak', 'type' => 'int', 'width' => 8],
            ['label' => 'NIP', 'type' => 'text', 'width' => 11],
            ['label' => 'Nama', 'type' => 'text', 'width' => 26],
            ['label' => 'NIK', 'type' => 'id', 'width' => 19],
            ['label' => 'NPWP', 'type' => 'id', 'width' => 19],
            ['label' => 'Status PTKP', 'type' => 'text', 'width' => 9],
            ['label' => 'Kode Objek Pajak', 'type' => 'text', 'width' => 12],
            ['label' => 'Penghasilan Bruto', 'type' => 'money', 'width' => 16],
            ['label' => 'Metode', 'type' => 'text', 'width' => 16],
            ['label' => 'Kategori TER', 'type' => 'text', 'width' => 9],
            ['label' => 'Tarif (%)', 'type' => 'percent', 'width' => 9],
            ['label' => 'PPh 21 Dipotong', 'type' => 'money', 'width' => 15],
            ['label' => 'Lebih Potong Dikembalikan', 'type' => 'money', 'width' => 15],
            ['label' => 'PPh 21 Disetor (Neto)', 'type' => 'money', 'width' => 15],
        ];

        $rows = [];

        foreach ($pph['rows'] as $row) {
            $rows[] = ['cells' => [
                (int) $period['month'], (int) $period['year'], $row['nip'], $row['name'], $row['nik'], $row['npwp'], $row['ptkp_status'],
                ReportService::TAX_OBJECT_CODE, $row['bruto'],
                $row['method'] === 'ter' ? 'TER Bulanan' : 'Tarif Pasal 17 (setahun)',
                $row['method'] === 'ter' ? (string) $row['ter_category'] : '', $row['ter_rate'], $row['pph21'], $row['refund'], $row['pph21'] - $row['refund'],
            ]];
        }

        $rows[] = ['type' => 'total', 'cells' => [null, null, '', 'TOTAL ' . count($pph['rows']) . ' PEGAWAI', '', '', '', '', $pph['total']['bruto'], '', '', null, $pph['total']['pph21'], $pph['total']['refund'], $pph['total']['net']]];

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @param array<string, mixed> $report hasil ReportService::attendance()
     *
     * @return Table
     */
    public static function attendance(array $report): array
    {
        $columns = [
            ['label' => 'NIP', 'type' => 'text', 'width' => 11],
            ['label' => 'Nama', 'type' => 'text', 'width' => 26],
            ['label' => 'Departemen', 'type' => 'text', 'width' => 18],
            ['label' => 'Jabatan', 'type' => 'text', 'width' => 24],
            ['label' => 'Hari Kerja', 'type' => 'int', 'width' => 9],
        ];

        foreach (AttendanceModel::STATUSES as $label) {
            $columns[] = ['label' => $label, 'type' => 'int', 'width' => 9];
        }

        array_push(
            $columns,
            ['label' => 'Menit Terlambat', 'type' => 'int', 'width' => 10],
            ['label' => 'Belum Tercatat', 'type' => 'int', 'width' => 9],
            ['label' => 'Lembur (jam)', 'type' => 'decimal', 'width' => 10],
            ['label' => 'Tingkat Kehadiran (%)', 'type' => 'percent', 'width' => 11],
        );

        $rows = [];

        foreach ($report['rows'] as $row) {
            $cells = [$row['nip'], $row['name'], $row['department'], $row['position'], $row['workdays']];

            foreach (array_keys(AttendanceModel::STATUSES) as $status) {
                $cells[] = $row['days'][$status];
            }

            $rows[] = ['cells' => [...$cells, $row['days']['late_minutes'], $row['unrecorded'], $row['overtime'], $row['rate'] === null ? null : round($row['rate'], 2)]];
        }

        $total = ['', 'TOTAL ' . count($report['rows']) . ' KARYAWAN', '', '', $report['total']['workdays']];

        foreach (array_keys(AttendanceModel::STATUSES) as $status) {
            $total[] = $report['total'][$status];
        }

        $rate   = $report['total']['rate'] === null ? null : round($report['total']['rate'], 2);
        $rows[] = ['type' => 'total', 'cells' => [...$total, $report['total']['late_minutes'], $report['total']['unrecorded'], $report['total']['overtime'], $rate]];

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * @param array<string, mixed> $trend hasil ReportService::costTrend()
     *
     * @return Table
     */
    public static function costTrend(array $trend): array
    {
        $columns = [['label' => 'Departemen', 'type' => 'text', 'width' => 22]];

        foreach ($trend['months'] as $month) {
            $columns[] = ['label' => periode_id($month['year'], $month['month'], true), 'type' => 'money', 'width' => 14];
        }

        $columns[] = ['label' => 'Total', 'type' => 'money', 'width' => 16];

        $rows = [];

        foreach ($trend['departments'] as $department) {
            $rows[] = ['cells' => [$department['name'], ...$department['costs'], $department['total']]];
        }

        $rows[] = ['type' => 'total', 'cells' => ['TOTAL', ...array_map(static fn (?int $v): int => $v ?? 0, $trend['totals']), $trend['grand']]];

        return ['columns' => $columns, 'rows' => $rows];
    }
}
