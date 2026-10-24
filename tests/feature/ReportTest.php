<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Report\ReportService;
use CodeIgniter\Exceptions\PageNotFoundException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\Support\Feature\SeededFeatureTestCase;

/**
 * Laporan (Fase 11): rekap gaji, BPJS, PPh 21, kehadiran, tren biaya, dan ekspor CSV/XLSX.
 *
 * Data seed: Agustus 2026 terkunci, September 2026 dibayar (31 karyawan, Hadi prorata),
 * Oktober 2026 draft (30 karyawan).
 *
 * @internal
 */
final class ReportTest extends SeededFeatureTestCase
{
    private const PAGES = ['reports', 'reports/payroll', 'reports/bpjs', 'reports/pph21', 'reports/attendance', 'reports/cost-trend'];

    // ------------------------------------------------------------------
    // Akses
    // ------------------------------------------------------------------

    public function testStaffCanOpenEveryReport(): void
    {
        foreach (['sinta.maharani', 'budi.santoso', 'admin'] as $username) {
            foreach (self::PAGES as $page) {
                $this->as($username)->get($page)->assertOK();
            }
        }
    }

    public function testEmployeeCannotOpenReportsOrExports(): void
    {
        foreach ([...self::PAGES, 'reports/payroll/export', 'reports/pph21/export?format=xlsx'] as $page) {
            $this->as('andi.pratama')->get($page)->assertStatus(403);
        }
    }

    public function testUnknownReportExportIsNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->as('budi.santoso')->get('reports/salaries/export');
    }

    // ------------------------------------------------------------------
    // Rekap gaji (T-1101)
    // ------------------------------------------------------------------

    public function testPayrollRecapReconcilesWithPayrollTotals(): void
    {
        foreach ([[2026, 8], [2026, 9], [2026, 10]] as [$year, $month]) {
            $periodId = $this->period($year, $month)['id'];
            $recap    = ReportService::make()->payrollRecap($periodId);
            $db       = $this->db->table('payrolls')
                ->select('COUNT(*) AS n, SUM(total_earning) AS gross, SUM(total_deduction) AS deduction, SUM(take_home_pay) AS thp, SUM(bpjs_company_total) AS company')
                ->select('SUM(CASE WHEN pph21 > 0 THEN pph21 ELSE 0 END) AS pph21', false)
                ->where('period_id', $periodId)->get()->getRowArray();

            $this->assertSame((int) $db['n'], $recap['employees']);
            $this->assertSame((int) $db['gross'], $recap['total']['gross']);
            $this->assertSame((int) $db['deduction'], $recap['total']['deduction']);
            $this->assertSame((int) $db['pph21'], $recap['total']['pph21']);
            $this->assertSame((int) $db['thp'], $recap['total']['thp']);
            $this->assertSame((int) $db['gross'] + (int) $db['company'], $recap['total']['employer_cost']);

            $subtotalThp = 0;

            foreach ($recap['groups'] as $group) {
                $subtotalThp += $group['subtotal']['thp'];

                foreach ($group['rows'] as $row) {
                    $a = $row['amounts'];
                    $this->assertSame($a['gross'], $a['basic'] + $a['allowance'] + $a['daily'] + $a['overtime'] + $a['other_earning'], $row['name']);
                    $this->assertSame($a['deduction'], $a['bpjs_employee'] + $a['pph21'] + $a['other_deduction'], $row['name']);
                    $this->assertSame($a['thp'], $a['gross'] - $a['deduction'], $row['name']);
                    $this->assertGreaterThanOrEqual(0, $a['other_earning']);
                }
            }

            $this->assertSame($recap['total']['thp'], $subtotalThp);
        }
    }

    public function testOverwithheldTaxRefundIsNotCountedAsDeduction(): void
    {
        // Hadi resign 15 Sep: PPh setahun lebih potong → payrolls.pph21 negatif + pendapatan PPH21_REFUND
        $september = $this->period(2026, 9)['id'];
        $payroll   = $this->db->table('payrolls')->where(['period_id' => $september, 'employee_id' => $this->employee('EMP-0031')])->get()->getRowArray();
        $refund    = -(int) $payroll['pph21'];

        $this->assertGreaterThan(0, $refund);

        $rows = array_merge(...array_column(ReportService::make()->payrollRecap($september)['groups'], 'rows'));
        $hadi = array_values(array_filter($rows, static fn (array $row): bool => $row['nip'] === 'EMP-0031'))[0]['amounts'];

        $this->assertSame(0, $hadi['pph21']);
        $this->assertSame((int) $payroll['total_deduction'] - (int) $payroll['bpjs_employee_total'], $hadi['other_deduction']);
        $this->assertGreaterThanOrEqual($refund, $hadi['other_earning']);

        $pph  = ReportService::make()->pph21($september);
        $row  = array_values(array_filter($pph['rows'], static fn (array $row): bool => $row['nip'] === 'EMP-0031'))[0];
        $sum  = (int) $this->db->table('payrolls')->selectSum('pph21')->where('period_id', $september)->get()->getRow()->pph21;

        $this->assertSame(['annual', 0, $refund], [$row['method'], $row['pph21'], $row['refund']]);
        $this->assertSame($sum, $pph['total']['net']);
        $this->assertSame($pph['total']['pph21'] - $refund, $pph['total']['net']);
    }

    public function testPayrollRecapPageGroupsByDepartment(): void
    {
        $september = $this->period(2026, 9)['id'];

        $result = $this->as('budi.santoso')->get('reports/payroll', ['period' => $september]);
        $result->assertOK();
        $result->assertSee('Rekap Gaji per Departemen');
        $result->assertSee('Subtotal Engineering');
        $result->assertSee('Total 31 karyawan');
        $result->assertSee('Hadi Gunawan');
        $result->assertDontSee('Angka sementara');

        // Default = periode terbaru (Oktober, masih draft) → ditandai sementara
        $draft = $this->as('budi.santoso')->get('reports/payroll');
        $draft->assertSee('Angka sementara');
        $draft->assertSee('Total 30 karyawan');
    }

    public function testPayrollRecapCsvExport(): void
    {
        $september = $this->period(2026, 9)['id'];

        $result = $this->as('sinta.maharani')->get('reports/payroll/export', ['period' => $september]);

        $result->assertOK();
        $result->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $result->assertHeader('Content-Disposition', 'attachment; filename="laporan-payroll-2026-09.csv"');

        $body  = (string) $result->response()->getBody();
        $lines = array_map(static fn (string $line): array => str_getcsv($line, ';', '"', ''), explode("\n", trim(substr($body, 3))));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertSame(['NIP', 'Nama', 'Departemen'], array_slice($lines[0], 0, 3));
        $this->assertSame('Take Home Pay', $lines[0][18]);

        $total = end($lines);
        $thp   = (int) $this->db->table('payrolls')->selectSum('take_home_pay')->where('period_id', $september)->get()->getRow()->take_home_pay;

        $this->assertSame('TOTAL 31 KARYAWAN', $total[1]);
        $this->assertSame((string) $thp, $total[18]);
        $this->assertCount(1 + 31 + 6 + 1, $lines); // header + karyawan + subtotal 6 departemen + total

        $this->seeInDatabase('audit_logs', ['action' => 'report.export', 'entity' => 'payroll_period', 'entity_id' => (string) $september]);
    }

    public function testPayrollRecapXlsxUsesLiveSubtotalFormulas(): void
    {
        $august = $this->period(2026, 8)['id'];

        $result = $this->as('budi.santoso')->get('reports/payroll/export', ['period' => $august, 'format' => 'xlsx']);

        $result->assertOK();
        $result->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $result->assertHeader('Content-Disposition', 'attachment; filename="laporan-payroll-2026-08.xlsx"');

        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, (string) $result->response()->getBody());

        try {
            $sheet = IOFactory::load($file)->getActiveSheet();
        } finally {
            unlink($file);
        }

        $this->assertSame('Laporan Rekap Gaji', $sheet->getCell('A1')->getValue());

        $lastRow = $sheet->getHighestRow();
        $thpCell = $sheet->getCell('S' . $lastRow);
        $thp     = (int) $this->db->table('payrolls')->selectSum('take_home_pay')->where('period_id', $august)->get()->getRow()->take_home_pay;

        $this->assertSame('TOTAL 31 KARYAWAN', $sheet->getCell('B' . $lastRow)->getValue());
        $this->assertStringStartsWith('=SUBTOTAL(9,S', (string) $thpCell->getValue());
        $this->assertSame($thp, (int) $thpCell->getCalculatedValue());

        // NIP ditulis sebagai teks, nominal sebagai angka
        $firstRow = $this->firstDataRow($sheet);
        $this->assertMatchesRegularExpression('/^EMP-\d{4}$/', $sheet->getCell('A' . $firstRow)->getValue());
        $this->assertIsInt($sheet->getCell('S' . $firstRow)->getValue());
    }

    // ------------------------------------------------------------------
    // BPJS (T-1102) & PPh 21 (T-1103)
    // ------------------------------------------------------------------

    public function testBpjsReportSplitsCompanyAndEmployeeShares(): void
    {
        $september = $this->period(2026, 9)['id'];
        $bpjs      = ReportService::make()->bpjs($september);
        $db        = $this->db->table('payrolls')->select('SUM(bpjs_company_total) AS company, SUM(bpjs_employee_total) AS employee')
            ->where('period_id', $september)->get()->getRowArray();

        $this->assertSame((int) $db['company'], $bpjs['total']['company']);
        $this->assertSame((int) $db['employee'], $bpjs['total']['employee']);
        $this->assertSame((int) $db['company'], array_sum(array_column($bpjs['programs'], 'company')));
        $this->assertSame((int) $db['employee'], array_sum(array_column($bpjs['programs'], 'employee')));
        $this->assertSame(0, $bpjs['programs']['jkk']['employee']);
        $this->assertSame(4.0, $bpjs['programs']['kes']['er_rate']);
        $this->assertSame(1.0, $bpjs['programs']['kes']['ee_rate']);

        $page = $this->as('budi.santoso')->get('reports/bpjs', ['period' => $september]);
        $page->assertOK();
        $page->assertSee('Jaminan Pensiun (JP)');
        $page->assertSee(rupiah($bpjs['total']['total']));

        $csv = $this->as('budi.santoso')->get('reports/bpjs/export', ['period' => $september]);
        $csv->assertOK();
        $csv->assertSee('KESEHATAN Perusahaan');
        $csv->assertSee('TOTAL 31 PESERTA');
    }

    public function testPph21ReportMasksIdentityOnScreenButExportsFullNik(): void
    {
        $september = $this->period(2026, 9)['id'];
        $pph       = ReportService::make()->pph21($september);
        $nik       = (string) $this->db->table('employees')->where('nip', 'EMP-0014')->get()->getRow()->nik;
        $sum       = (int) $this->db->table('payroll_items pi')->selectSum('pi.amount')->join('payrolls p', 'p.id = pi.payroll_id')
            ->where(['p.period_id' => $september, 'pi.code' => 'PPH21'])->get()->getRow()->amount;

        $this->assertSame($sum, $pph['total']['pph21']);
        $this->assertCount(31, $pph['rows']);

        $page = $this->as('budi.santoso')->get('reports/pph21', ['period' => $september]);
        $page->assertOK();
        $page->assertSee(mask_id($nik));
        $page->assertDontSee($nik);
        $page->assertSee(ReportService::TAX_OBJECT_CODE);

        $csv  = $this->as('budi.santoso')->get('reports/pph21/export', ['period' => $september]);
        $body = (string) $csv->response()->getBody();

        $this->assertStringContainsString("'" . $nik, $body);
        $this->assertStringContainsString('Kode Objek Pajak', $body);
        $this->assertMatchesRegularExpression('/;"TER Bulanan";[ABC];\d+,\d+;/', $body); // tarif berdesimal koma (mis. 1,75)
        $this->assertStringContainsString('"PPh 21 Disetor (Neto)"', $body);
    }

    // ------------------------------------------------------------------
    // Kehadiran (T-1104) & tren biaya (T-1105)
    // ------------------------------------------------------------------

    public function testAttendanceReportMatchesAttendanceRecords(): void
    {
        $report = ReportService::make()->attendance(2026, 9);
        $galih  = array_values(array_filter($report['rows'], static fn (array $row): bool => $row['nip'] === 'EMP-0015'))[0];
        $count  = $this->db->table('attendances')->where('date >=', '2026-09-01')->where('date <=', '2026-09-30')->where('status !=', 'holiday')->countAllResults();

        $this->assertSame(1, $galih['days']['absent']);
        $this->assertSame($count, array_sum(array_map(static fn (array $row): int => array_sum(array_diff_key($row['days'], ['late_minutes' => 0, 'holiday' => 0])), $report['rows'])));
        $this->assertCount(31, $report['rows']); // Hadi resign 15 Sep tetap muncul

        $page = $this->as('sinta.maharani')->get('reports/attendance', ['month' => '2026-09']);
        $page->assertOK();
        $page->assertSee('Galih Prasetyo');
        $page->assertSee('September 2026');

        $csv = $this->as('sinta.maharani')->get('reports/attendance/export', ['month' => '2026-09', 'format' => 'csv']);
        $csv->assertOK();
        $csv->assertHeader('Content-Disposition', 'attachment; filename="laporan-attendance-2026-09.csv"');
        $csv->assertSee('TOTAL 31 KARYAWAN');
    }

    public function testCostTrendCoversTwelveMonths(): void
    {
        $trend = ReportService::make()->costTrend(2026, 10);
        $cost  = (int) $this->db->table('payrolls')->select('SUM(total_earning + bpjs_company_total) AS cost', false)->get()->getRow()->cost;

        $this->assertCount(12, $trend['months']);
        $this->assertSame('2025-11', $trend['months'][0]['key']);
        $this->assertCount(3, array_filter($trend['totals'], static fn (?int $v): bool => $v !== null));
        $this->assertSame($cost, $trend['grand']);
        $this->assertSame($cost, array_sum(array_column($trend['departments'], 'total')));

        $page = $this->as('budi.santoso')->get('reports/cost-trend', ['end' => '2026-10']);
        $page->assertOK();
        $page->assertSee('cost-trend-chart');
        $page->assertSee('Engineering');

        $xlsx = $this->as('budi.santoso')->get('reports/cost-trend/export', ['end' => '2026-10', 'format' => 'xlsx']);
        $xlsx->assertOK();
        $xlsx->assertHeader('Content-Disposition', 'attachment; filename="laporan-cost-trend-2026-10.xlsx"');
    }

    private function firstDataRow(Worksheet $sheet): int
    {
        foreach ($sheet->getRowIterator() as $row) {
            $value = $sheet->getCell('A' . $row->getRowIndex())->getValue();

            if (is_string($value) && str_starts_with($value, 'EMP-')) {
                return $row->getRowIndex();
            }
        }

        $this->fail('Baris data karyawan tidak ditemukan.');
    }
}
