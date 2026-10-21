<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use Tests\Support\Feature\SeededFeatureTestCase;

/**
 * Modul payroll (UI): daftar & pembuatan periode, review, penyesuaian manual,
 * alur persetujuan HR → Finance sampai terkunci, dan ekspor file transfer bank.
 *
 * Data seed: Agustus 2026 terkunci, September 2026 dibayar, Oktober 2026 draft.
 *
 * @internal
 */
final class PayrollUiTest extends SeededFeatureTestCase
{
    public function testAccessMatrix(): void
    {
        $october = $this->period(2026, 10)['id'];
        $andi    = $this->employee('EMP-0014');

        foreach (['payroll', "payroll/{$october}", "payroll/{$october}/employees/{$andi}"] as $path) {
            $this->as('budi.santoso')->get($path)->assertOK();
            $this->as('andi.pratama')->get($path)->assertStatus(403);
        }

        $this->as('budi.santoso')->post("payroll/{$october}/generate")->assertStatus(403);
        $this->as('budi.santoso')->post("payroll/{$october}/employees/{$andi}/adjustments", ['name' => 'Bonus'])->assertStatus(403);
        $this->as('sinta.maharani')->post("payroll/{$october}/approve")->assertStatus(403);
        $this->as('sinta.maharani')->get("payroll/{$october}/export/bank")->assertStatus(403);
        $this->as('andi.pratama')->get('payroll/999/employees/1')->assertStatus(403);
    }

    public function testPeriodListShowsCardsPerPeriod(): void
    {
        $result = $this->as('sinta.maharani')->get('payroll');

        $result->assertOK();
        $result->assertSee('Agustus 2026');
        $result->assertSee('September 2026');
        $result->assertSee('Oktober 2026');
        $result->assertSee('Buat Periode');
        $result->assertSee('Periode bulan ini sudah ada.');

        $this->as('budi.santoso')->get('payroll')->assertDontSee('Buat Periode</button>');
    }

    public function testCreatePeriodWithDefaultAndCustomPayDate(): void
    {
        $this->as('sinta.maharani')->post('payroll', ['month' => '2026-11'])->assertRedirect();
        // 30 Nov 2026 = Senin → hari kerja terakhir November
        $this->seeInDatabase('payroll_periods', ['year' => 2026, 'month' => 11, 'pay_date' => '2026-11-30', 'status' => 'draft', 'workdays' => 21]);

        $this->as('sinta.maharani')->post('payroll', ['month' => '2026-11']);
        $this->assertStringContainsString('sudah ada', session('errors')['month']);
        $this->assertSame('create', session('open_modal'));

        $this->as('sinta.maharani')->post('payroll', ['month' => '2026-12', 'pay_date' => '2027-02-01']);
        $this->assertArrayHasKey('pay_date', session('errors'));
        $this->dontSeeInDatabase('payroll_periods', ['year' => 2026, 'month' => 12]);

        $this->as('sinta.maharani')->post('payroll', ['month' => '2026-12', 'pay_date' => '2026-12-23', 'notes' => 'Dibayar sebelum Natal']);
        $this->seeInDatabase('payroll_periods', ['year' => 2026, 'month' => 12, 'pay_date' => '2026-12-23', 'notes' => 'Dibayar sebelum Natal']);

        // Periode baru belum di-generate
        $december = $this->period(2026, 12)['id'];
        $this->as('sinta.maharani')->get("payroll/{$december}")->assertSee('Payroll belum di-generate');
    }

    public function testReviewPageShowsTotalsAndWarnings(): void
    {
        $september = $this->period(2026, 9);
        $result    = $this->as('budi.santoso')->get("payroll/{$september['id']}");

        $result->assertOK();
        $result->assertSee('Rincian per Karyawan');
        $result->assertSee('Total take home pay');
        $result->assertSee('Hadi Gunawan');
        $result->assertSee('prorata');
        $result->assertSee('File Transfer Bank'); // Finance & status dibayar
        $result->assertSee('Gaji Sep 2026');

        $thp = (int) $this->db->table('payrolls')->selectSum('take_home_pay')->where('period_id', $september['id'])->get()->getRow()->take_home_pay;
        $result->assertSee(rupiah($thp));
    }

    public function testEmployeeDetailExplainsPph21(): void
    {
        $september = $this->period(2026, 9)['id'];
        $october   = $this->period(2026, 10)['id'];

        $andi = $this->as('budi.santoso')->get("payroll/{$october}/employees/{$this->employee('EMP-0014')}");
        $andi->assertOK();
        $andi->assertSee('Cara Hitung PPh 21');
        $andi->assertSee('TK/0 → TER A');
        $andi->assertSee('Bruto PPh 21');
        $andi->assertSee('Kontribusi Perusahaan');
        $andi->assertSee('hari × Rp 30.000'); // tunjangan transport harian

        // Hadi resign 15 Sep → masa pajak terakhir dihitung setahun
        $hadi = $this->as('budi.santoso')->get("payroll/{$september}/employees/{$this->employee('EMP-0031')}");
        $hadi->assertSee('PPh 21 setahun (Pasal 17)');
        $hadi->assertSee('Prorata 11/22 hari kerja');

        // Karyawan di luar periode (Hadi sudah resign) → 404
        $this->expectException(PageNotFoundException::class);
        $this->as('budi.santoso')->get("payroll/{$october}/employees/{$this->employee('EMP-0031')}");
    }

    public function testFullWorkflowWithAdjustmentRejectionAndBankExport(): void
    {
        $october = $this->period(2026, 10)['id'];
        $andi    = $this->employee('EMP-0014');
        $before  = $this->payroll($october, $andi);

        // 1. HR: generate ulang
        $this->as('sinta.maharani')->post("payroll/{$october}/generate")->assertRedirect();
        $this->assertStringContainsString('di-generate', (string) session('success'));

        // 2. HR: tambah bonus untuk Andi → regenerate otomatis
        $this->as('sinta.maharani')->post("payroll/{$october}/employees/{$andi}/adjustments", ['name' => 'Bonus Kinerja Q3', 'type' => 'earning', 'amount' => '1.000.000', 'taxable' => '1', 'note' => 'Rilis tepat waktu']);
        $after = $this->payroll($october, $andi);
        $this->assertSame($before['total_earning'] + 1_000_000, $after['total_earning']);
        $this->assertGreaterThan($before['pph21'], $after['pph21']); // bonus kena pajak
        $this->seeInDatabase('payroll_items', ['payroll_id' => $after['id'], 'code' => 'ADJ', 'name' => 'Bonus Kinerja Q3', 'amount' => 1_000_000]);
        $this->seeInDatabase('audit_logs', ['action' => 'payroll.adjustment.create', 'entity_id' => (string) $october]);
        $this->assertStringContainsString('take home pay kini', (string) session('success'));

        // Validasi penyesuaian
        $this->as('sinta.maharani')->post("payroll/{$october}/employees/{$andi}/adjustments", ['name' => '', 'type' => 'bonus', 'amount' => '0']);
        $this->assertSame(['name', 'type', 'amount'], array_keys(session('errors')));

        // Hapus penyesuaian koreksi
        $this->as('sinta.maharani')->post("payroll/{$october}/employees/{$andi}/adjustments", ['name' => 'Koreksi Salah Input', 'type' => 'deduction', 'amount' => '50.000']);
        $adjustment = $this->db->table('payroll_adjustments')->where(['period_id' => $october, 'name' => 'Koreksi Salah Input'])->get()->getRowArray();
        $this->as('sinta.maharani')->post("payroll/{$october}/adjustments/{$adjustment['id']}/delete");
        $this->dontSeeInDatabase('payroll_adjustments', ['id' => $adjustment['id']]);
        $this->assertSame($after['take_home_pay'], $this->payroll($october, $andi)['take_home_pay']);

        // 3. HR: ajukan → Finance menolak (wajib catatan)
        $this->as('sinta.maharani')->post("payroll/{$october}/submit");
        $this->seeInDatabase('payroll_periods', ['id' => $october, 'status' => 'review']);

        $this->as('sinta.maharani')->post("payroll/{$october}/generate");
        $this->assertStringContainsString('Draft', (string) session('error'));
        $this->as('sinta.maharani')->post("payroll/{$october}/employees/{$andi}/adjustments", ['name' => 'THR', 'type' => 'earning', 'amount' => '500.000']);
        $this->assertStringContainsString('Draft', (string) session('error'));

        $this->as('budi.santoso')->post("payroll/{$october}/reject");
        $this->assertStringContainsString('wajib diisi', (string) session('error'));
        $this->as('budi.santoso')->post("payroll/{$october}/reject", ['note' => 'Lembur Taufik belum disetujui']);
        $this->seeInDatabase('payroll_periods', ['id' => $october, 'status' => 'draft', 'rejection_note' => 'Lembur Taufik belum disetujui']);
        $this->as('sinta.maharani')->get("payroll/{$october}")->assertSee('Dikembalikan oleh Finance');

        // 4. Ajukan ulang → setujui → ekspor bank → bayar → kunci
        $this->as('sinta.maharani')->post("payroll/{$october}/submit");
        $this->as('budi.santoso')->post("payroll/{$october}/approve", ['note' => 'OK']);
        $this->seeInDatabase('payroll_periods', ['id' => $october, 'status' => 'approved']);

        $all = $this->as('budi.santoso')->get("payroll/{$october}/export/bank");
        $all->assertOK();
        $all->assertHeader('Content-Disposition', 'attachment; filename="transfer-gaji-2026-10-semua-bank.csv"');
        $lines = array_values(array_filter(explode("\n", (string) $all->response()->getBody())));
        $this->assertSame('no,nip,nama_penerima,no_rekening,bank,nominal,berita', $lines[0]);
        $this->assertCount(30 + 1, $lines);

        $snapshot = json_decode($this->db->table('payrolls')->where('id', $this->payroll($october, $andi)['id'])->get()->getRow()->snapshot, true);
        $bca      = $this->as('budi.santoso')->get("payroll/{$october}/export/bank", ['bank' => 'BCA'])->response()->getBody();
        $this->assertStringContainsString(",EMP-0014,\"{$snapshot['bank_account_name']}\",{$snapshot['bank_account_no']},BCA,{$after['take_home_pay']},\"Gaji Okt 2026\"", $bca);
        $this->assertStringNotContainsString(',BRI,', $bca);
        $this->seeInDatabase('audit_logs', ['action' => 'payroll.export_bank', 'entity_id' => (string) $october]);

        $kevinLoan = $this->db->table('loans')->where('loan_no', 'PJM-2026-0002')->get()->getRowArray();

        $this->as('budi.santoso')->post("payroll/{$october}/pay");
        $this->as('budi.santoso')->post("payroll/{$october}/lock");
        $this->seeInDatabase('payroll_periods', ['id' => $october, 'status' => 'locked']);
        $this->assertStringContainsString('dikunci', (string) session('success'));

        // Cicilan Kevin Oktober dibukukan dan sisa pinjaman berkurang
        $this->seeInDatabase('loan_installments', ['loan_id' => $kevinLoan['id'], 'period' => '2026-10']);
        $this->assertLessThan((int) $kevinLoan['remaining'], (int) $this->db->table('loans')->where('id', $kevinLoan['id'])->get()->getRow()->remaining);

        // Periode terkunci: tidak bisa dikunci ulang / di-generate
        $this->as('budi.santoso')->post("payroll/{$october}/lock");
        $this->assertStringContainsString('tidak dapat dilakukan', (string) session('error'));
        $this->as('budi.santoso')->get("payroll/{$october}")->assertSee('Periode terkunci sejak');
    }

    public function testBankExportRequiresApprovedPeriod(): void
    {
        $this->as('sinta.maharani')->post('payroll', ['month' => '2027-01']);
        $draft = $this->period(2027, 1)['id'];

        $this->as('budi.santoso')->get("payroll/{$draft}/export/bank")->assertRedirect();
        $this->assertStringContainsString('setelah payroll disetujui', (string) session('error'));

        $august = $this->period(2026, 8)['id'];
        $this->as('budi.santoso')->get("payroll/{$august}/export/bank", ['bank' => 'BTN'])->assertRedirect();
        $this->assertStringContainsString('BTN', (string) session('error'));
    }

    /**
     * @return array<string, int>
     */
    private function payroll(int $periodId, int $employeeId): array
    {
        return array_map('intval', $this->db->table('payrolls')
            ->select('id, total_earning, total_deduction, pph21, take_home_pay')
            ->where(['period_id' => $periodId, 'employee_id' => $employeeId])
            ->get()->getRowArray());
    }
}
