<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Payslip\PayslipService;
use Tests\Support\Feature\SeededFeatureTestCase;
use ZipArchive;

/**
 * Slip gaji (web, PDF, ZIP), otorisasi kepemilikan, dan self-service karyawan.
 *
 * Data seed: Agustus 2026 terkunci, September 2026 dibayar, Oktober 2026 draft.
 *
 * @internal
 */
final class PayslipTest extends SeededFeatureTestCase
{
    // ------------------------------------------------------------------
    // Otorisasi (T-1003)
    // ------------------------------------------------------------------

    public function testEmployeeSeesOwnFinalSlipOnly(): void
    {
        $andiSep  = $this->payrollId(2026, 9, 'EMP-0014');
        $andiOct  = $this->payrollId(2026, 10, 'EMP-0014');
        $kevinSep = $this->payrollId(2026, 9, 'EMP-0011');

        $own = $this->as('andi.pratama')->get("payslips/{$andiSep}");
        $own->assertOK();
        $own->assertSee('Slip Gaji');
        $own->assertSee('Andi Pratama');
        $own->assertSee('SG/2026/09/EMP-0014');
        $own->assertSee('Unduh PDF');
        $own->assertDontSee('DRAFT');

        $this->as('andi.pratama')->get("payslips/{$kevinSep}")->assertStatus(403);     // slip orang lain
        $this->as('andi.pratama')->get("payslips/{$kevinSep}/pdf")->assertStatus(403);
        $this->as('andi.pratama')->get("payslips/{$andiOct}")->assertStatus(403);      // periode belum dibayar
        $this->as('andi.pratama')->get('payslips/999999')->assertStatus(403);          // ID tidak bisa ditebak
    }

    public function testStaffCanPreviewAnySlipIncludingDraft(): void
    {
        $kevinOct = $this->payrollId(2026, 10, 'EMP-0011');

        foreach (['sinta.maharani', 'budi.santoso', 'admin'] as $username) {
            $result = $this->as($username)->get("payslips/{$kevinOct}");
            $result->assertOK();
            $result->assertSee('Kevin Tanoto');
            $result->assertSee('Pratinjau');
            $result->assertSee('DRAFT');
        }
    }

    // ------------------------------------------------------------------
    // PDF & ZIP (T-1002, T-1005)
    // ------------------------------------------------------------------

    public function testPdfDownload(): void
    {
        $andiSep = $this->payrollId(2026, 9, 'EMP-0014');

        $result = $this->as('andi.pratama')->get("payslips/{$andiSep}/pdf");

        $result->assertOK();
        $result->assertHeader('Content-Type', 'application/pdf');
        $result->assertHeader('Content-Disposition', 'attachment; filename="Slip-Gaji-EMP-0014-2026-09.pdf"');
        $this->assertStringStartsWith('%PDF-', (string) $result->response()->getBody());
        $this->seeInDatabase('audit_logs', ['action' => 'payslip.download', 'entity_id' => (string) $andiSep]);
    }

    public function testBulkZipContainsEverySlipOfThePeriod(): void
    {
        $august = $this->period(2026, 8)['id'];

        $this->as('andi.pratama')->get("payroll/{$august}/payslips")->assertStatus(403);

        $result = $this->as('budi.santoso')->get("payroll/{$august}/payslips");
        $result->assertOK();
        $result->assertHeader('Content-Type', 'application/zip');
        $result->assertHeader('Content-Disposition', 'attachment; filename="Slip-Gaji-2026-08.zip"');

        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, (string) $result->response()->getBody());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));

        $expected = $this->db->table('payrolls')->where('period_id', $august)->countAllResults();
        $this->assertSame($expected, $zip->numFiles);
        $this->assertNotFalse($zip->locateName('Slip-Gaji-EMP-0014-2026-08.pdf'));
        $this->assertStringStartsWith('%PDF-', (string) $zip->getFromName('Slip-Gaji-EMP-0006-2026-08.pdf'));

        $zip->close();
        unlink($path);
        $this->seeInDatabase('audit_logs', ['action' => 'payslip.download_zip', 'entity_id' => (string) $august]);
    }

    // ------------------------------------------------------------------
    // Self-service (T-1004)
    // ------------------------------------------------------------------

    public function testEmployeeIsRedirectedToPersonalDashboard(): void
    {
        $this->as('andi.pratama')->get('dashboard')->assertRedirectTo(site_url('my'));

        $result = $this->as('andi.pratama')->get('my');
        $sep    = $this->db->table('payrolls')->where('id', $this->payrollId(2026, 9, 'EMP-0014'))->get()->getRow();

        $result->assertOK();
        $result->assertSee('Take home pay terakhir · September 2026');
        $result->assertSee(rupiah($sep->take_home_pay));
        $result->assertSee('Anda tidak memiliki pinjaman berjalan.');
        $result->assertSee('Slip Gaji Saya');          // menu
        $result->assertDontSee('Karyawan Aktif');       // data perusahaan

        $this->as('kevin.tanoto')->get('my')->assertSee('PJM-2026-0002');
    }

    public function testStaffWithAndWithoutEmployeeRecord(): void
    {
        // HR tetap memakai dashboard perusahaan, dengan tautan ke dashboard pribadinya
        $hr = $this->as('sinta.maharani')->get('dashboard');
        $hr->assertOK();
        $hr->assertSee('Karyawan Aktif');
        $hr->assertSee('Dashboard saya');
        $this->as('sinta.maharani')->get('my')->assertSee('Take home pay terakhir');

        $this->as('admin')->get('my')->assertRedirectTo(site_url('dashboard'));
        $this->assertStringContainsString('tidak terhubung', (string) session('warning'));
    }

    public function testMyPayslipsListsOnlyFinalPeriods(): void
    {
        $result = $this->as('andi.pratama')->get('my/payslips');

        $result->assertOK();
        $result->assertSee('Agustus 2026');
        $result->assertSee('September 2026');
        $result->assertSee(site_url('payslips/' . $this->payrollId(2026, 9, 'EMP-0014') . '/pdf'));
        $result->assertDontSee(site_url('payslips/' . $this->payrollId(2026, 10, 'EMP-0014')));
        $result->assertSee('PPh 21 dipotong');

        // Anisa baru masuk Agustus: dua slip
        $this->assertCount(2, PayslipService::make()->listForEmployee($this->employee('EMP-0006')));
    }

    private function payrollId(int $year, int $month, string $nip): int
    {
        return (int) $this->db->table('payrolls')
            ->where('period_id', $this->period($year, $month)['id'])
            ->where('employee_id', $this->employee($nip))
            ->get()->getRow()->id;
    }
}
