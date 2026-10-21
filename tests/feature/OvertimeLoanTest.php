<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Support\Feature\SeededFeatureTestCase;

/**
 * Lembur (hitung jam, validasi, persetujuan) dan pinjaman (pengajuan, persetujuan Finance, jadwal cicilan).
 *
 * @internal
 */
final class OvertimeLoanTest extends SeededFeatureTestCase
{
    // ------------------------------------------------------------------
    // Lembur
    // ------------------------------------------------------------------

    public function testOvertimeAccessMatrix(): void
    {
        $this->as('budi.santoso')->get('overtime', ['month' => '2026-10'])->assertOK();
        $this->as('andi.pratama')->get('overtime')->assertStatus(403);
        $this->as('budi.santoso')->post('overtime', ['employee_id' => $this->employee('EMP-0014')])->assertStatus(403);
        $this->as('budi.santoso')->post('overtime/review', ['ids' => '1', 'action' => 'approve'])->assertStatus(403);
    }

    public function testOvertimeHoursAreComputedFromTimes(): void
    {
        $andi = $this->employee('EMP-0014');

        $this->as('sinta.maharani')->post('overtime', [
            'employee_id' => $andi, 'date' => '2026-10-17', 'start_time' => '18:00', 'end_time' => '21:30', 'reason' => 'Deploy rilis aplikasi 2.4',
            'hours'       => '9', // diabaikan: durasi selalu dihitung server
        ])->assertRedirect();

        $this->seeInDatabase('overtimes', ['employee_id' => $andi, 'date' => '2026-10-17', 'hours' => '3.50', 'status' => 'pending', 'start_time' => '18:00:00']);
        $this->seeInDatabase('audit_logs', ['action' => 'overtime.create']);

        // Melewati tengah malam: 22:00 → 01:30 = 3,5 jam
        $this->as('sinta.maharani')->post('overtime', [
            'employee_id' => $andi, 'date' => '2026-10-18', 'start_time' => '22:00', 'end_time' => '01:30', 'reason' => 'Migrasi database malam',
        ]);
        $this->seeInDatabase('overtimes', ['employee_id' => $andi, 'date' => '2026-10-18', 'hours' => '3.50']);
    }

    public function testOvertimeBusinessRules(): void
    {
        $andi   = $this->employee('EMP-0014');
        $hendra = $this->employee('EMP-0001');

        // Direktur tidak berhak lembur
        $this->as('sinta.maharani')->post('overtime', ['employee_id' => $hendra, 'date' => '2026-10-24', 'start_time' => '17:00', 'end_time' => '19:00', 'reason' => 'Rapat direksi']);
        $this->assertStringContainsString('tidak berhak', session('errors')['employee_id']);
        $this->assertSame('create', session('open_modal'));

        // Bertumpuk dengan pengajuan lain di hari yang sama
        $this->as('sinta.maharani')->post('overtime', ['employee_id' => $andi, 'date' => '2026-10-24', 'start_time' => '17:00', 'end_time' => '19:00', 'reason' => 'Support produksi']);
        $this->as('sinta.maharani')->post('overtime', ['employee_id' => $andi, 'date' => '2026-10-24', 'start_time' => '18:30', 'end_time' => '20:00', 'reason' => 'Support produksi']);
        $this->assertStringContainsString('Bertumpuk', session('errors')['start_time']);

        // Lebih dari 12 jam & alasan kosong
        $this->as('sinta.maharani')->post('overtime', ['employee_id' => $andi, 'date' => '2026-10-25', 'start_time' => '06:00', 'end_time' => '20:00', 'reason' => '']);
        $errors = session('errors');
        $this->assertSame('Durasi lembur maksimal 12 jam per hari.', $errors['hours']);
        $this->assertArrayHasKey('reason', $errors);

        // Periode September sudah dibayar
        $this->as('sinta.maharani')->post('overtime', ['employee_id' => $andi, 'date' => '2026-09-12', 'start_time' => '09:00', 'end_time' => '12:00', 'reason' => 'Perbaikan bug']);
        $this->assertStringContainsString('September 2026', session('errors')['date']);

        $this->assertSame(1, $this->db->table('overtimes')->where(['employee_id' => $andi, 'date' => '2026-10-24'])->countAllResults());
    }

    public function testBulkApproveRejectAndLockAfterApproval(): void
    {
        $dimas = $this->employee('EMP-0004');

        foreach (['2026-10-31' => ['09:00', '12:00'], '2026-11-01' => ['09:00', '11:00']] as $date => [$start, $end]) {
            $this->as('sinta.maharani')->post('overtime', ['employee_id' => $dimas, 'date' => $date, 'start_time' => $start, 'end_time' => $end, 'reason' => 'Rekrutmen massal akhir pekan']);
        }

        $ids = array_map('intval', array_column($this->db->table('overtimes')->select('id')->where('employee_id', $dimas)->whereIn('date', ['2026-10-31', '2026-11-01'])->get()->getResultArray(), 'id'));
        $this->assertCount(2, $ids);

        // Tolak wajib catatan
        $this->as('sinta.maharani')->post('overtime/review', ['ids' => implode(',', $ids), 'action' => 'reject']);
        $this->assertStringContainsString('wajib diisi', (string) session('error'));

        $this->as('sinta.maharani')->post('overtime/review', ['ids' => (string) $ids[1], 'action' => 'reject', 'note' => 'Tidak ada surat tugas']);
        $this->seeInDatabase('overtimes', ['id' => $ids[1], 'status' => 'rejected', 'review_note' => 'Tidak ada surat tugas']);

        // Setujui keduanya: yang sudah ditolak dilewati
        $this->as('sinta.maharani')->post('overtime/review', ['ids' => implode(',', $ids), 'action' => 'approve']);
        $sinta = (int) $this->db->table('users')->where('username', 'sinta.maharani')->get()->getRow()->id;
        $this->seeInDatabase('overtimes', ['id' => $ids[0], 'status' => 'approved', 'approved_by' => $sinta]);
        $this->assertStringContainsString('1 pengajuan lembur disetujui', (string) session('success'));
        $this->assertStringContainsString('1 dilewati', (string) session('warning'));
        $this->seeInDatabase('audit_logs', ['action' => 'overtime.approve', 'entity_id' => (string) $ids[0]]);

        // Lembur disetujui tidak bisa diubah / dihapus
        $this->as('sinta.maharani')->post("overtime/{$ids[0]}", ['employee_id' => $dimas, 'date' => '2026-10-31', 'start_time' => '09:00', 'end_time' => '15:00', 'reason' => 'Diubah setelah disetujui']);
        $this->assertStringContainsString('tidak dapat diubah', (string) session('error'));
        $this->as('sinta.maharani')->post("overtime/{$ids[0]}/delete");
        $this->seeInDatabase('overtimes', ['id' => $ids[0], 'hours' => '3.00']);

        // Pengajuan ditolak boleh diperbaiki → kembali menunggu persetujuan
        $this->as('sinta.maharani')->post("overtime/{$ids[1]}", ['employee_id' => $dimas, 'date' => '2026-11-01', 'start_time' => '09:00', 'end_time' => '10:30', 'reason' => 'Rekrutmen massal (surat tugas terlampir)']);
        $this->seeInDatabase('overtimes', ['id' => $ids[1], 'status' => 'pending', 'hours' => '1.50', 'review_note' => null]);
    }

    public function testOvertimeListShowsEstimatedWage(): void
    {
        $result = $this->as('budi.santoso')->get('overtime', ['month' => '2026-09', 'status' => 'approved']);

        $result->assertOK();
        $result->assertSee('Lembur September 2026');
        $result->assertSee('Estimasi upah lembur');
        $result->assertDontSee('Ajukan Lembur'); // Finance hanya melihat
    }

    // ------------------------------------------------------------------
    // Pinjaman
    // ------------------------------------------------------------------

    public function testLoanAccessMatrix(): void
    {
        $kevinLoan = (int) $this->db->table('loans')->where('loan_no', 'PJM-2026-0002')->get()->getRow()->id;
        $fajarLoan = (int) $this->db->table('loans')->where('loan_no', 'PJM-2026-0004')->get()->getRow()->id;

        $this->as('budi.santoso')->get('loans')->assertOK();
        $this->as('budi.santoso')->get("loans/{$kevinLoan}")->assertOK();
        $this->as('andi.pratama')->get('loans')->assertStatus(403);
        $this->as('budi.santoso')->post('loans', [])->assertStatus(403);       // Finance tidak mengajukan
        $this->as('sinta.maharani')->post("loans/{$fajarLoan}/approve")->assertStatus(403); // HR tidak menyetujui
    }

    public function testLoanApplicationRules(): void
    {
        $base = ['amount' => '6.000.000', 'tenor_months' => 4, 'start_period' => '2026-11', 'purpose' => 'Biaya sekolah anak'];

        // Kevin masih punya pinjaman berjalan
        $this->as('sinta.maharani')->post('loans', ['employee_id' => $this->employee('EMP-0011')] + $base);
        $this->assertStringContainsString('masih memiliki pinjaman PJM-2026-0002', session('errors')['employee_id']);

        // Cicilan 10 jt > 50% gaji pokok Andi (12 jt)
        $this->as('sinta.maharani')->post('loans', ['employee_id' => $this->employee('EMP-0014'), 'amount' => '30.000.000', 'tenor_months' => 3] + $base);
        $this->assertStringContainsString('melebihi 50% gaji pokok', session('errors')['tenor_months']);

        // Bulan mulai potong sudah final (September dibayar)
        $this->as('sinta.maharani')->post('loans', ['employee_id' => $this->employee('EMP-0014'), 'start_period' => '2026-09'] + $base);
        $this->assertArrayHasKey('start_period', session('errors'));

        $this->dontSeeInDatabase('loans', ['purpose' => 'Biaya sekolah anak']);
    }

    public function testLoanApprovalFlowAndSchedule(): void
    {
        $andi = $this->employee('EMP-0014');

        $this->as('sinta.maharani')->post('loans', [
            'employee_id' => $andi, 'amount' => '2.000.000', 'tenor_months' => 3, 'start_period' => '2026-11', 'purpose' => 'Servis motor',
        ])->assertRedirect();

        $loan = $this->db->table('loans')->where(['employee_id' => $andi, 'purpose' => 'Servis motor'])->get()->getRowArray();
        $this->assertSame('PJM-' . date('Y') . '-0005', $loan['loan_no']);
        $this->assertSame(666_667, (int) $loan['installment']); // dibulatkan ke atas
        $this->assertSame('pending', $loan['status']);
        $this->assertSame(2_000_000, (int) $loan['remaining']);

        // Simulasi jadwal: cicilan terakhir menyesuaikan sisa
        $page = $this->as('sinta.maharani')->get("loans/{$loan['id']}");
        $page->assertSee('Simulasi Jadwal Cicilan');
        $page->assertSee('Rp 666.666');
        $page->assertSee('Januari 2027');

        // HR masih bisa mengubah selama menunggu persetujuan
        $this->as('sinta.maharani')->post("loans/{$loan['id']}", [
            'employee_id' => $andi, 'amount' => '2.400.000', 'tenor_months' => 4, 'start_period' => '2026-11', 'purpose' => 'Servis motor',
        ]);
        $this->seeInDatabase('loans', ['id' => $loan['id'], 'amount' => 2_400_000, 'installment' => 600_000, 'remaining' => 2_400_000]);

        $this->as('budi.santoso')->post("loans/{$loan['id']}/approve", ['note' => 'Sesuai kebijakan']);
        $budi = (int) $this->db->table('users')->where('username', 'budi.santoso')->get()->getRow()->id;
        $this->seeInDatabase('loans', ['id' => $loan['id'], 'status' => 'approved', 'approved_by' => $budi, 'review_note' => 'Sesuai kebijakan']);
        $this->seeInDatabase('audit_logs', ['action' => 'loan.approve', 'entity_id' => (string) $loan['id']]);

        // Setelah disetujui tidak bisa diubah / dihapus
        $this->as('sinta.maharani')->post("loans/{$loan['id']}", ['employee_id' => $andi, 'amount' => '1.000.000', 'tenor_months' => 1, 'start_period' => '2026-11', 'purpose' => 'Servis motor']);
        $this->assertStringContainsString('menunggu persetujuan', (string) session('error'));
        $this->as('sinta.maharani')->post("loans/{$loan['id']}/delete");
        $this->seeInDatabase('loans', ['id' => $loan['id'], 'amount' => 2_400_000]);

        $this->as('budi.santoso')->get("loans/{$loan['id']}")->assertSee('Jadwal Cicilan');
    }

    public function testRejectRequiresNote(): void
    {
        $fajarLoan = (int) $this->db->table('loans')->where('loan_no', 'PJM-2026-0004')->get()->getRow()->id;

        $this->as('budi.santoso')->post("loans/{$fajarLoan}/reject");
        $this->assertStringContainsString('wajib diisi', (string) session('error'));
        $this->seeInDatabase('loans', ['id' => $fajarLoan, 'status' => 'pending']);

        $this->as('budi.santoso')->post("loans/{$fajarLoan}/reject", ['note' => 'Melebihi plafon tahun ini']);
        $this->seeInDatabase('loans', ['id' => $fajarLoan, 'status' => 'rejected', 'review_note' => 'Melebihi plafon tahun ini']);
    }

    public function testActiveLoanScheduleCombinesPostedAndRunningDeductions(): void
    {
        $kevinLoan = (int) $this->db->table('loans')->where('loan_no', 'PJM-2026-0002')->get()->getRow()->id;

        $page = $this->as('budi.santoso')->get("loans/{$kevinLoan}");

        $page->assertOK();
        $page->assertSee('Terbayar');            // Juli (di luar sistem) & Agustus (periode terkunci)
        $page->assertSee('Dipotong di payroll'); // September (dibayar) & Oktober (draft)
        $page->assertSee('Terjadwal');
        $page->assertSee('Juni 2027');           // cicilan ke-12
    }
}
