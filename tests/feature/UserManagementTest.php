<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserModel;
use App\Services\Auth\AuthService;
use App\Services\Auth\PasswordGenerator;
use Tests\Support\Feature\SeededFeatureTestCase;

/**
 * User Management (T-1201): daftar, tambah/ubah, reset password, aktif/nonaktif, buka kunci,
 * dan penjagaan Super Admin. Setiap test memakai akun berbeda (state berbagi dalam kelas).
 *
 * @internal
 */
final class UserManagementTest extends SeededFeatureTestCase
{
    public function testOnlySuperadminCanOpenUserManagement(): void
    {
        foreach (['sinta.maharani', 'budi.santoso', 'andi.pratama'] as $username) {
            $this->as($username)->get('users')->assertStatus(403);
            $this->as($username)->post('users', ['username' => 'penyusup', 'name' => 'X', 'role' => 'superadmin'])->assertStatus(403);
        }

        $this->dontSeeInDatabase('users', ['username' => 'penyusup']);

        $result = $this->as('admin')->get('users');
        $result->assertOK();
        $result->assertSee('Daftar Pengguna');
        $result->assertSee('Menampilkan 1–25 dari 32 akun');
        $result->assertSee('@agus.salim');
        $result->assertSee('Anda');

        $this->as('admin')->get('users', ['q' => 'sinta'])->assertSee('@sinta.maharani');
        $result->assertDontSee('password_hash');
    }

    public function testListCanBeFilteredByRoleStatusAndKeyword(): void
    {
        $finance = $this->as('admin')->get('users', ['role' => 'finance']);
        $finance->assertSee('@budi.santoso');
        $finance->assertDontSee('@andi.pratama');

        $inactive = $this->as('admin')->get('users', ['status' => 'inactive']);
        $inactive->assertSee('@hadi.gunawan');
        $inactive->assertDontSee('@budi.santoso');

        $search = $this->as('admin')->get('users', ['q' => 'EMP-0014']);
        $search->assertSee('@andi.pratama');
        $search->assertDontSee('@galih.prasetyo');
    }

    public function testCreateUserWithTemporaryPassword(): void
    {
        $result = $this->as('admin')->post('users', [
            'username'    => '  Auditor.Internal ',
            'name'        => 'Auditor   Internal',
            'role'        => 'finance',
            'employee_id' => '',
        ]);

        $result->assertRedirectTo(url_to('users.index'));
        $result->assertSessionHas('credential');

        $credential = $_SESSION['credential'];
        $user       = (new UserModel())->findByUsername('auditor.internal');

        $this->assertNotNull($user);
        $this->assertSame('Auditor Internal', $user['name']);
        $this->assertSame('finance', $user['role']);
        $this->assertTrue($user['is_active']);
        $this->assertTrue($user['must_change_password']);
        $this->assertSame('auditor.internal', $credential['username']);
        $this->assertTrue(password_verify($credential['password'], $user['password_hash']));
        $this->assertSame([], AuthService::passwordPolicyErrors($credential['password'], 'auditor.internal'));

        $log = $this->db->table('audit_logs')->where(['action' => 'user.create', 'entity_id' => (string) $user['id']])->get()->getRowArray();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString($credential['password'], (string) $log['after']);
        $this->assertStringNotContainsString('password', (string) $log['after']);
    }

    public function testCreateUserValidation(): void
    {
        $andi = $this->employee('EMP-0014');
        $hadi = $this->employee('EMP-0031');

        $cases = [
            'username'    => ['username' => 'Andi Pratama!', 'name' => 'X', 'role' => 'hr', 'employee_id' => ''],
            'employee_id' => ['username' => 'tanpa.karyawan', 'name' => 'X', 'role' => 'employee', 'employee_id' => ''],
        ];

        foreach ($cases as $field => $payload) {
            $result = $this->as('admin')->post('users', $payload);
            $result->assertSessionHas('errors');
            $this->assertArrayHasKey($field, $_SESSION['errors'], $field);
            $result->assertSessionHas('open_modal', 'create');
        }

        $duplicate = $this->as('admin')->post('users', ['username' => 'andi.pratama', 'name' => 'X', 'role' => 'hr', 'employee_id' => '']);
        $duplicate->assertSessionHas('errors');
        $this->assertSame('Username sudah dipakai.', $_SESSION['errors']['username']);

        $linked = $this->as('admin')->post('users', ['username' => 'andi.kedua', 'name' => 'X', 'role' => 'employee', 'employee_id' => (string) $andi]);
        $linked->assertSessionHas('errors');
        $this->assertSame('Karyawan ini sudah memiliki akun.', $_SESSION['errors']['employee_id']);

        $resigned = $this->as('admin')->post('users', ['username' => 'hadi.kedua', 'name' => 'X', 'role' => 'employee', 'employee_id' => (string) $hadi]);
        $resigned->assertSessionHas('errors');
        $this->assertStringContainsString('sudah tidak aktif', $_SESSION['errors']['employee_id']);

        $this->dontSeeInDatabase('users', ['username' => 'tanpa.karyawan']);
        $this->dontSeeInDatabase('users', ['username' => 'andi.kedua']);
    }

    public function testUpdateUserRecordsChangedFieldsOnly(): void
    {
        $user = (new UserModel())->findByUsername('putri.lestari');

        $this->as('admin')->post("users/{$user['id']}", [
            'username'    => 'putri.lestari',
            'name'        => 'Putri A. Lestari',
            'role'        => 'hr',
            'employee_id' => (string) $user['employee_id'],
        ])->assertRedirectTo(url_to('users.index'));

        $this->seeInDatabase('users', ['id' => $user['id'], 'name' => 'Putri A. Lestari']);

        $log = $this->db->table('audit_logs')->where(['action' => 'user.update', 'entity_id' => (string) $user['id']])->get()->getRowArray();
        $this->assertSame(['name' => 'Putri Ayu Lestari'], json_decode($log['before'], true));
        $this->assertSame(['name' => 'Putri A. Lestari'], json_decode($log['after'], true));
    }

    public function testResetPasswordForcesChangeAndRevokesRememberTokens(): void
    {
        $user = (new UserModel())->findByUsername('yoga.aditya');
        $this->db->table('users')->where('id', $user['id'])->update(['failed_attempts' => 4, 'locked_until' => date('Y-m-d H:i:s', time() + 600)]);
        $this->db->table('user_tokens')->insert([
            'user_id' => $user['id'], 'selector' => str_repeat('a', 24), 'token_hash' => hash('sha256', 'x'),
            'expires_at' => date('Y-m-d H:i:s', time() + DAY), 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->as('admin')->post("users/{$user['id']}/reset-password");

        $result->assertRedirectTo(url_to('users.index'));
        $result->assertSessionHas('credential');

        $fresh = (new UserModel())->find($user['id']);
        $this->assertTrue(password_verify($_SESSION['credential']['password'], $fresh['password_hash']));
        $this->assertFalse(password_verify('Payroll#2026', $fresh['password_hash']));
        $this->assertTrue($fresh['must_change_password']);
        $this->assertSame(0, $fresh['failed_attempts']);
        $this->assertNull($fresh['locked_until']);
        $this->dontSeeInDatabase('user_tokens', ['user_id' => $user['id']]);
        $this->seeInDatabase('audit_logs', ['action' => 'user.reset_password', 'entity_id' => (string) $user['id']]);

        // Pengguna diarahkan ke ganti password sebelum boleh membuka halaman lain
        $this->as('yoga.aditya')->get('my')->assertRedirectTo(url_to('account.password'));
    }

    public function testDeactivateEndsAccessAndActivateRestoresIt(): void
    {
        $user = (new UserModel())->findByUsername('taufik.hidayat');

        $this->as('taufik.hidayat')->get('my')->assertOK();

        $this->as('admin')->post("users/{$user['id']}/deactivate")->assertRedirectTo(url_to('users.index'));
        $this->seeInDatabase('users', ['id' => $user['id'], 'is_active' => 0]);
        $this->seeInDatabase('audit_logs', ['action' => 'user.deactivate', 'entity_id' => (string) $user['id']]);

        // Sesi yang masih ada langsung tidak berlaku
        $this->as('taufik.hidayat')->get('my')->assertRedirectTo(url_to('login'));

        $this->as('admin')->post("users/{$user['id']}/activate")->assertRedirectTo(url_to('users.index'));
        $this->seeInDatabase('users', ['id' => $user['id'], 'is_active' => 1]);
        $this->seeInDatabase('audit_logs', ['action' => 'user.activate', 'entity_id' => (string) $user['id']]);
        $this->as('taufik.hidayat')->get('my')->assertOK();
    }

    public function testAccountOfResignedEmployeeCannotBeActivated(): void
    {
        $hadi = (new UserModel())->findByUsername('hadi.gunawan');

        $this->as('admin')->post("users/{$hadi['id']}/activate")->assertSessionHas('error');
        $this->seeInDatabase('users', ['id' => $hadi['id'], 'is_active' => 0]);
    }

    public function testSuperadminCannotLockThemselvesOut(): void
    {
        $admin = (new UserModel())->findByUsername('admin');

        $this->as('admin')->post("users/{$admin['id']}/deactivate")->assertSessionHas('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        $this->as('admin')->post("users/{$admin['id']}/reset-password")->assertSessionHas('error');

        $demote = $this->as('admin')->post("users/{$admin['id']}", ['username' => 'admin', 'name' => 'Administrator Sistem', 'role' => 'hr', 'employee_id' => '']);
        $demote->assertSessionHas('errors');
        $this->assertSame('Anda tidak dapat mengubah peran akun Anda sendiri.', $_SESSION['errors']['role']);

        $fresh = (new UserModel())->find($admin['id']);
        $this->assertTrue($fresh['is_active']);
        $this->assertSame('superadmin', $fresh['role']);
        $this->assertTrue(password_verify('Admin#2026', $fresh['password_hash']));
    }

    public function testUnlockLockedAccount(): void
    {
        $user = (new UserModel())->findByUsername('reza.mahendra');
        $this->db->table('users')->where('id', $user['id'])->update(['locked_until' => date('Y-m-d H:i:s', time() + 900)]);

        $this->as('admin')->get('users', ['status' => 'locked'])->assertSee('@reza.mahendra');

        $this->as('admin')->post("users/{$user['id']}/unlock")->assertSessionHas('success');
        $this->seeInDatabase('users', ['id' => $user['id'], 'locked_until' => null, 'failed_attempts' => 0]);
        $this->seeInDatabase('audit_logs', ['action' => 'user.unlock', 'entity_id' => (string) $user['id']]);
    }

    public function testResigningEmployeeDeactivatesAndAuditsAccount(): void
    {
        $user = (new UserModel())->findByUsername('slamet.riyadi');

        $this->as('sinta.maharani')->post("employees/{$user['employee_id']}/resign", ['resign_date' => date('Y-m-d'), 'status' => 'resigned'])
            ->assertSessionHas('success');

        $this->seeInDatabase('users', ['id' => $user['id'], 'is_active' => 0]);
        $this->seeInDatabase('audit_logs', ['action' => 'user.deactivate', 'entity_id' => (string) $user['id']]);
    }

    public function testTemporaryPasswordGenerator(): void
    {
        $seen = [];

        for ($i = 0; $i < 50; $i++) {
            $password = PasswordGenerator::temporary('abcd');

            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Za-km-z2-9]{4}-[A-HJ-NP-Za-km-z2-9]{4}-[A-HJ-NP-Za-km-z2-9]{4}$/', $password);
            $this->assertSame([], AuthService::passwordPolicyErrors($password, 'abcd'));
            $seen[$password] = true;
        }

        $this->assertCount(50, $seen);
    }
}
