<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\AttendanceModel;
use App\Models\EmployeeModel;
use App\Models\TerRateModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Memastikan DatabaseSeeder menghasilkan data contoh sesuai PRD §7 & Lampiran B.
 *
 * @internal
 */
final class SeedDataTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = DatabaseSeeder::class;
    protected $seedOnce    = true;

    public function testMasterDataCounts(): void
    {
        $this->seeInDatabase('departments', ['code' => 'ENG']);
        $this->assertSame(6, $this->db->table('departments')->countAllResults());
        $this->assertSame(20, $this->db->table('positions')->countAllResults());
        $this->assertSame(31, $this->db->table('employees')->countAllResults());
        $this->assertSame(30, (new EmployeeModel())->active()->countAllResults());
        $this->assertSame(8, $this->db->table('ptkp_rates')->countAllResults());
        $this->assertSame(125, $this->db->table('ter_rates')->countAllResults());
    }

    public function testUsersHaveNoEmailAndUseUsername(): void
    {
        $this->assertNotContains('email', $this->db->getFieldNames('users'));

        $users = new UserModel();
        $this->assertSame(32, $users->countAllResults());
        $this->assertSame(31, $users->where('is_active', 1)->countAllResults());

        $admin = $users->findByUsername('  ADMIN ');
        $this->assertNotNull($admin);
        $this->assertSame('superadmin', $admin['role']);
        $this->assertTrue(password_verify('Admin#2026', $admin['password_hash']));

        $andi = $users->findByUsername('andi.pratama');
        $this->assertSame('employee', $andi['role']);
        $this->assertTrue(password_verify('Payroll#2026', $andi['password_hash']));

        $this->assertFalse($users->findByUsername('hadi.gunawan')['is_active']);
    }

    public function testTerRateLookup(): void
    {
        $ter = new TerRateModel();

        $this->assertSame(2.5, $ter->rateFor('A', 10_608_600));  // contoh PRD §6.5
        $this->assertSame(0.0, $ter->rateFor('A', 5_400_000));   // batas atas baris pertama
        $this->assertSame(0.25, $ter->rateFor('A', 5_400_001));  // batas bawah baris kedua
        $this->assertSame(34.0, $ter->rateFor('C', 2_000_000_000));
        $this->assertSame(1.0, $ter->rateFor('B', 9_200_000));
    }

    public function testSettingService(): void
    {
        $settings = service('appSettings');
        $settings->flush();

        $this->assertSame(4.0, $settings->get('bpjs.kes.company_rate'));
        $this->assertSame(10_547_400, $settings->get('bpjs.jp.max_wage'));
        $this->assertSame('PT Nusantara Digital Solusi', $settings->get('company.name'));
        $this->assertSame('fallback', $settings->get('tidak.ada', 'fallback'));
        $this->assertArrayHasKey('company.npwp', $settings->group('company'));
    }

    public function testAttendanceScenarios(): void
    {
        $employees = array_column((new EmployeeModel())->withDeleted()->findAll(), 'id', 'nip');
        $attendance = new AttendanceModel();

        // Galih: tepat satu hari alpha di September 2026
        $galih = $attendance->summary($employees['EMP-0015'], '2026-09-01', '2026-09-30');
        $this->assertSame(1, $galih['absent']);

        // Anisa tidak punya kehadiran sebelum tanggal masuk, Hadi tidak sesudah resign
        $this->dontSeeInDatabase('attendances', ['employee_id' => $employees['EMP-0006'], 'date <' => '2026-08-03']);
        $this->dontSeeInDatabase('attendances', ['employee_id' => $employees['EMP-0031'], 'date >' => '2026-09-15']);

        // Tidak ada kehadiran di hari libur nasional
        $this->dontSeeInDatabase('attendances', ['date' => '2026-08-17']);
    }

    public function testDepartmentHeadsAndLoans(): void
    {
        $head = $this->db->table('departments')
            ->select('employees.nip')
            ->join('employees', 'employees.id = departments.head_employee_id')
            ->where('departments.code', 'ENG')
            ->get()->getRowArray();

        $this->assertSame('EMP-0010', $head['nip']);
        $this->seeInDatabase('loans', ['loan_no' => 'PJM-2026-0002', 'status' => 'active', 'remaining' => 11_000_000]);
        $this->seeInDatabase('loans', ['loan_no' => 'PJM-2026-0004', 'status' => 'pending']);
    }
}
