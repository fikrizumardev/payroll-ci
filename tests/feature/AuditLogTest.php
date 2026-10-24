<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLogModel;
use App\Models\UserModel;
use App\Services\AuditDiff;
use App\Services\AuditLogger;
use Tests\Support\Feature\SeededFeatureTestCase;

/**
 * Audit log (T-1202, T-1203): akses, filter, rincian before/after, dan sanitasi data sensitif.
 *
 * @internal
 */
final class AuditLogTest extends SeededFeatureTestCase
{
    public function testAccessIsLimitedToSuperadminAndFinance(): void
    {
        $this->as('admin')->get('audit-logs')->assertOK();
        $this->as('budi.santoso')->get('audit-logs')->assertOK();
        $this->as('sinta.maharani')->get('audit-logs')->assertStatus(403);
        $this->as('andi.pratama')->get('audit-logs')->assertStatus(403);
    }

    public function testPageShowsBeforeAfterDiff(): void
    {
        $result = $this->as('budi.santoso')->get('audit-logs', ['action' => 'setting.update', 'from' => '2026-09-01', 'to' => '2026-09-01']);

        $result->assertOK();
        $result->assertSee('Memperbarui batas upah Jaminan Pensiun');
        $result->assertSee('Ubah pengaturan');
        $result->assertSee('Sebelum');
        $result->assertSee('Sesudah');
        $result->assertSee('10042300');
        $result->assertSee('10547400');
        $result->assertSee('Selasa, 1 September 2026');
    }

    public function testFiltersByUserActionDateAndKeyword(): void
    {
        $budi = (new UserModel())->findByUsername('budi.santoso');

        $byUser = $this->as('admin')->get('audit-logs', ['user_id' => $budi['id']]);
        $byUser->assertSee('Menyetujui pinjaman PJM-2026-0002');
        $byUser->assertDontSee('Import kehadiran September 2026');

        $byModule = $this->as('admin')->get('audit-logs', ['action' => 'loan.*']);
        $byModule->assertSee('Mengajukan pinjaman PJM-2026-0004');
        $byModule->assertDontSee('Menonaktifkan akun hadi.gunawan');

        $byDate = $this->as('admin')->get('audit-logs', ['from' => '2026-07-01', 'to' => '2026-07-31', 'action' => 'loan.approve']);
        $byDate->assertSee('PJM-2026-0003');
        $byDate->assertDontSee('PJM-2026-0002');

        // Tanggal terbalik ditukar otomatis
        $swapped = $this->as('admin')->get('audit-logs', ['from' => '2026-07-31', 'to' => '2026-07-01', 'action' => 'loan.approve']);
        $swapped->assertSee('PJM-2026-0003');

        $byKeyword = $this->as('admin')->get('audit-logs', ['q' => 'hadi.gunawan']);
        $byKeyword->assertSee('Menonaktifkan akun hadi.gunawan');
        $byKeyword->assertDontSee('PJM-2026-0002');

        // Nilai filter tak valid diabaikan
        $this->as('admin')->get('audit-logs', ['action' => "x' OR 1=1 --", 'from' => 'kemarin'])->assertOK();
    }

    public function testLoggerNeverStoresSecretsAndMasksIdentityNumbers(): void
    {
        $user = (new UserModel())->findByUsername('admin');

        service('auditLogger')->log('user.update', 'user', $user['id'], 'Uji sanitasi', [
            'password_hash' => '$2y$10$rahasia',
            'nik'           => '3174012345678012',
        ], [
            'password'  => 'Rahasia#2026',
            'npwp'      => '3174012345678012',
            'bank'      => ['bank_account_no' => '1234567890', 'token' => 'abc'],
            'name'      => 'Tetap terbaca',
        ], $user);

        $row    = $this->db->table('audit_logs')->where('description', 'Uji sanitasi')->get()->getRowArray();
        $before = json_decode($row['before'], true);
        $after  = json_decode($row['after'], true);

        $this->assertSame(AuditLogger::REDACTED, $before['password_hash']);
        $this->assertSame('3174•••••••••012', $before['nik']);
        $this->assertSame(AuditLogger::REDACTED, $after['password']);
        $this->assertSame('3174•••••••••012', $after['npwp']);
        $this->assertSame('1234•••890', $after['bank']['bank_account_no']);
        $this->assertSame(AuditLogger::REDACTED, $after['bank']['token']);
        $this->assertSame('Tetap terbaca', $after['name']);
        $this->assertStringNotContainsString('Rahasia#2026', $row['after']);
    }

    public function testDiffRowsAndActionCatalog(): void
    {
        $rows = AuditDiff::rows(['status' => 'pending', 'amount' => 100, 'meta' => ['rate' => 2]], ['status' => 'approved', 'amount' => 100, 'note' => 'ok']);
        $map  = array_column($rows, null, 'key');

        $this->assertSame('changed', $map['status']['change']);
        $this->assertSame('same', $map['amount']['change']);
        $this->assertSame('removed', $map['meta.rate']['change']);
        $this->assertSame('added', $map['note']['change']);

        $this->assertSame('Setujui payroll', AuditLogModel::label('payroll.approve'));
        $this->assertSame('rose', AuditLogModel::tone('loan.reject'));
        $this->assertSame('Payroll · unknown_step', AuditLogModel::label('payroll.unknown_step'));

        // Semua aksi yang tercatat di data seed memiliki label katalog
        foreach (array_column($this->db->table('audit_logs')->distinct()->select('action')->get()->getResultArray(), 'action') as $action) {
            $this->assertArrayHasKey($action, AuditLogModel::ACTIONS, $action);
        }
    }
}
