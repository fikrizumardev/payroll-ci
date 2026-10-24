<?php

declare(strict_types=1);

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor laporan ke CSV dan XLSX dari satu definisi kolom & baris yang sama.
 *
 * Kolom: ['label' => 'Gaji Pokok', 'type' => 'text|id|int|money|decimal|percent', 'width' => 16]
 * Baris: ['type' => 'data|group|subtotal|total', 'cells' => [...]]
 *   - group    : judul kelompok (mis. nama departemen), hanya sel pertama yang dipakai
 *   - subtotal : jumlah baris data sejak judul kelompok terakhir
 *   - total    : jumlah seluruh baris data
 *
 * CSV mengikuti format Excel lokal Indonesia: UTF-8 dengan BOM, pemisah titik koma,
 * desimal koma, dan nomor identitas panjang diawali apostrof agar tidak berubah
 * menjadi notasi ilmiah (sama dengan ekspor data karyawan).
 * Pada XLSX, subtotal & total ditulis sebagai rumus SUBTOTAL() sehingga tetap
 * benar bila baris disaring atau diubah di Excel.
 */
class ReportExporter
{
    private const MONEY_FORMAT   = '#,##0;[Red]-#,##0';
    private const DECIMAL_FORMAT = '#,##0.00';
    private const PERCENT_FORMAT = '0.00"%"';

    private const HEADER_FILL   = 'FF312E81';
    private const GROUP_FILL    = 'FFEEF2FF';
    private const SUBTOTAL_FILL = 'FFF1F5F9';
    private const TOTAL_FILL    = 'FFE0E7FF';

    /**
     * @param list<array{label: string, type?: string, width?: int}>       $columns
     * @param list<array{type?: string, cells: list<int|float|string|null>}> $rows
     */
    public static function csv(array $columns, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_column($columns, 'label'), ';', '"', '');

        foreach ($rows as $row) {
            $type = $row['type'] ?? 'data';

            if ($type === 'group') {
                continue; // nama kelompok sudah ada di kolom data (mis. kolom Departemen)
            }

            $cells = [];

            foreach ($columns as $index => $column) {
                $cells[] = self::csvValue($row['cells'][$index] ?? null, $column['type'] ?? 'text');
            }

            fputcsv($handle, $cells, ';', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Workbook satu sheet: kop (judul + keterangan), header tabel, data, subtotal & total.
     *
     * @param list<string>                                                 $heading baris kop: [judul, keterangan…]
     * @param list<array{label: string, type?: string, width?: int}>       $columns
     * @param list<array{type?: string, cells: list<int|float|string|null>}> $rows
     */
    public static function xlsx(string $sheetTitle, array $heading, array $columns, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('PayrollKu')
            ->setTitle($heading[0] ?? $sheetTitle)
            ->setSubject($heading[0] ?? $sheetTitle);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheetTitle), 0, 31));

        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $line       = 1;

        foreach ($heading as $index => $text) {
            $sheet->setCellValue("A{$line}", $text);
            $sheet->getStyle("A{$line}")->getFont()->setBold($index === 0)->setSize($index === 0 ? 14 : 10)
                ->getColor()->setARGB($index === 0 ? 'FF0F172A' : 'FF475569');
            $line++;
        }

        $headerRow = ++$line;

        foreach ($columns as $index => $column) {
            $cell = Coordinate::stringFromColumnIndex($index + 1) . $headerRow;
            $sheet->setCellValue($cell, $column['label']);
            $sheet->getColumnDimensionByColumn($index + 1)->setWidth($column['width'] ?? 14);
        }

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        $line       = $headerRow + 1;
        $firstData  = $line;
        $groupStart = $line;

        foreach ($rows as $row) {
            $type  = $row['type'] ?? 'data';
            $range = "A{$line}:{$lastColumn}{$line}";

            if ($type === 'group') {
                $sheet->setCellValue("A{$line}", (string) ($row['cells'][0] ?? ''));
                $sheet->mergeCells($range);
                $sheet->getStyle($range)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FF312E81']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::GROUP_FILL]],
                ]);
                $groupStart = ++$line;

                continue;
            }

            foreach ($columns as $index => $column) {
                $value  = $row['cells'][$index] ?? null;
                $col    = Coordinate::stringFromColumnIndex($index + 1);
                $kind   = $column['type'] ?? 'text';
                $isSum  = in_array($kind, ['money', 'int', 'decimal'], true) && in_array($type, ['subtotal', 'total'], true) && $value !== null;
                $origin = $type === 'total' ? $firstData : $groupStart;

                if ($isSum) {
                    // 9 = SUM; SUBTOTAL mengabaikan subtotal lain di dalam rentang
                    $sheet->setCellValue("{$col}{$line}", "=SUBTOTAL(9,{$col}{$origin}:{$col}" . ($line - 1) . ')');
                } elseif ($value === null) {
                    continue;
                } elseif (in_array($kind, ['text', 'id'], true)) {
                    $sheet->setCellValueExplicit("{$col}{$line}", (string) $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue("{$col}{$line}", $value);
                }
            }

            if ($type !== 'data') {
                $sheet->getStyle($range)->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $type === 'total' ? self::TOTAL_FILL : self::SUBTOTAL_FILL]],
                    'borders' => ['top' => ['borderStyle' => $type === 'total' ? Border::BORDER_MEDIUM : Border::BORDER_THIN, 'color' => ['argb' => 'FF94A3B8']]],
                ]);
            }

            $line++;
        }

        $lastRow = max($line - 1, $headerRow);

        foreach ($columns as $index => $column) {
            $format = match ($column['type'] ?? 'text') {
                'money', 'int' => self::MONEY_FORMAT,
                'decimal'      => self::DECIMAL_FORMAT,
                'percent'      => self::PERCENT_FORMAT,
                default        => null,
            };

            if ($format !== null && $lastRow > $headerRow) {
                $col = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getStyle("{$col}" . ($headerRow + 1) . ":{$col}{$lastRow}")->getNumberFormat()->setFormatCode($format);
                $sheet->getStyle("{$col}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setARGB('FFCBD5E1');

        $sheet->freezePane('A' . ($headerRow + 1));
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->setSelectedCell('A' . ($headerRow + 1));

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $binary = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();

        return $binary;
    }

    private static function csvValue(int|float|string|null $value, string $type): string
    {
        if ($value === null) {
            return '';
        }

        return match ($type) {
            'money', 'int'       => (string) (int) $value,
            'decimal', 'percent' => str_replace('.', ',', rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.')),
            'id'                 => $value === '' ? '' : "'" . $value,
            default              => self::neutralizeFormula((string) $value),
        };
    }

    /**
     * Cegah CSV/formula injection: teks yang diawali = + - @ (atau tab/CR) tidak boleh
     * dieksekusi sebagai rumus saat file dibuka di spreadsheet.
     */
    private static function neutralizeFormula(string $value): string
    {
        return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }
}
