<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\EmployeeModel;
use App\Models\UserModel;
use App\Services\Auth\AuthService;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Modul master data: CRUD, aturan bisnis, hak akses, dan audit log.
 *
 * @internal
 */
final class MasterDataTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = DatabaseSeeder::class;
    protected $seedOnce    = true;

    protected $setUpMethods = ['resetFactories', 'resetServices', 'mockCache', 'mockEmail', 'mockSession'];

    protected function setUp(): void
    {
        parent::setUp();

        // CSRF diuji terpisah di AuthTest
        $filters                    = config('Filters');
        $filters->globals['before'] = array_values(array_diff($filters->globals['before'], ['csrf']));
        Factories::injectMock('config', 'Filters', $filters);
    }

    // ------------------------------------------------------------------
    // Hak akses
    // ------------------------------------------------------------------

    public function testAccessMatrix(): void
    {
        foreach (['departments', 'positions', 'salary-components', 'employees', 'employees/14', 'settings'] as $path) {
            $this->as('budi.santoso')->get($path)->assertOK();     // Finance: lihat
            $this->as('andi.pratama')->get($path)->assertStatus(403); // Karyawan: ditolak
        }

        $this->as('budi.santoso')->get('employees/create')->assertStatus(403);
        $this->as('budi.santoso')->get('holidays')->assertStatus(403);
        $this->as('budi.santoso')->post('departments', ['code' => 'XYZ', 'name' => 'Uji'])->assertStatus(403);
        $this->as('sinta.maharani')->post('settings/bpjs', ['settings' => ['bpjs.jkk.company_rate' => '0.54']])->assertStatus(403);
        $this->dontSeeInDatabase('departments', ['code' => 'XYZ']);
    }

    // ------------------------------------------------------------------
    // Departemen & jabatan
    // ------------------------------------------------------------------

    public function testDepartmentCrud(): void
    {
        $this->as('sinta.maharani')->post('departments', ['code' => 'lgl', 'name' => 'Legal & Compliance', 'description' => 'Kepatuhan'])
            ->assertRedirectTo(site_url('departments'));
        $this->seeInDatabase('departments', ['code' => 'LGL', 'name' => 'Legal & Compliance']);
        $this->seeInDatabase('audit_logs', ['action' => 'department.create']);

        $id = (int) $this->db->table('departments')->where('code', 'LGL')->get()->getRow()->id;

        $result = $this->as('sinta.maharani')->post("departments/{$id}", ['code' => 'ENG', 'name' => 'Duplikat']);
        $result->assertSessionHas('errors');
        $result->assertSessionHas('open_modal', 'edit:' . $id);

        $this->as('sinta.maharani')->post("departments/{$id}", ['code' => 'LGL', 'name' => 'Legal']);
        $this->seeInDatabase('departments', ['id' => $id, 'name' => 'Legal']);

        $this->as('sinta.maharani')->post("departments/{$id}/delete");
        $this->dontSeeInDatabase('departments', ['id' => $id]);
    }

    public function testDepartmentWithEmployeesCannotBeDeleted(): void
    {
        $eng = (int) $this->db->table('departments')->where('code', 'ENG')->get()->getRow()->id;

        $result = $this->as('sinta.maharani')->post("departments/{$eng}/delete");

        $this->assertStringContainsString('tidak bisa dihapus', (string) session('error'));
        $this->seeInDatabase('departments', ['id' => $eng]);
    }

    public function testPositionValidationAndUsageGuard(): void
    {
        $eng = (int) $this->db->table('departments')->where('code', 'ENG')->get()->getRow()->id;

        $result = $this->as('sinta.maharani')->post('positions', ['department_id' => $eng, 'code' => 'eng 99', 'name' => '', 'grade' => 0, 'base_salary' => '']);
        $errors = session('errors');
        $this->assertArrayHasKey('code', $errors);
        $this->assertSame('Nama jabatan wajib diisi.', $errors['name']); // pesan validasi Bahasa Indonesia

        $this->as('sinta.maharani')->post('positions', [
            'department_id' => $eng, 'code' => 'ENG-06', 'name' => 'Data Analyst', 'grade' => 5,
            'base_salary' => '13.500.000', 'position_allowance' => '1.250.000', 'communication_allowance' => '200.000', 'overtime_eligible' => '1',
        ]);
        $this->seeInDatabase('positions', ['code' => 'ENG-06', 'base_salary' => 13_500_000, 'overtime_eligible' => 1]);

        $used = (int) $this->db->table('positions')->where('code', 'ENG-03')->get()->getRow()->id;
        $this->as('sinta.maharani')->post("positions/{$used}/delete");
        $this->assertStringContainsString('masih dipakai', (string) session('error'));
    }

    // ------------------------------------------------------------------
    // Karyawan
    // ------------------------------------------------------------------

    public function testNextNipIgnoresSoftDeleteGrouping(): void
    {
        $this->assertSame('EMP-0032', (new EmployeeModel())->nextNip());
    }

    public function testCreateEmployeeWithAllowancesAndAccount(): void
    {
        [$departmentId, $positionId] = $this->position('ENG-04');

        $result = $this->as('sinta.maharani')->post('employees', [
            'full_name'               => 'Bagas  Kurniawan',
            'nik'                     => '3174 0112 9800 0123',
            'gender'                  => 'L',
            'birth_place'             => 'Jakarta',
            'birth_date'              => '1998-12-01',
            'department_id'           => $departmentId,
            'position_id'             => $positionId,
            'employment_type'         => 'probation',
            'join_date'               => '2026-11-02',
            'ptkp_status'             => 'TK/0',
            'basic_salary'            => '9.800.000',
            'position_allowance'      => '1.250.000',
            'communication_allowance' => '0',
            'bank_name'               => 'BCA',
            'bank_account_no'         => '1234567890',
            'bank_account_name'       => 'bagas kurniawan',
            'bpjs_kes_enrolled'       => '1',
            'bpjs_tk_enrolled'        => '1',
            'create_account'          => '1',
        ]);

        $employee = $this->db->table('employees')->where('nik', '3174011298000123')->get()->getRowArray();
        $this->assertNotNull($employee);
        $result->assertRedirectTo(site_url('employees/' . $employee['id']));

        $this->assertSame('EMP-0032', $employee['nip']);
        $this->assertSame('Bagas Kurniawan', $employee['full_name']);
        $this->assertSame('BAGAS KURNIAWAN', $employee['bank_account_name']);
        $this->seeInDatabase('employee_components', ['employee_id' => $employee['id'], 'amount' => 1_250_000]);
        $this->assertSame(1, $this->db->table('employee_components')->where('employee_id', $employee['id'])->countAllResults()); // tunj. komunikasi 0 tidak disimpan

        $user = (new UserModel())->findByUsername('bagas.kurniawan');
        $this->assertSame((int) $employee['id'], $user['employee_id']);
        $this->assertTrue($user['must_change_password']);
    }

    public function testEmployeeValidationErrors(): void
    {
        $result = $this->as('sinta.maharani')->post('employees', ['full_name' => 'A', 'nik' => '123', 'basic_salary' => '0']);

        $errors = session('errors');
        $this->assertSame('NIK harus 16 digit.', $errors['nik']);
        $this->assertArrayHasKey('department_id', $errors);
        $this->assertArrayHasKey('basic_salary', $errors);
        $this->assertSame('A', session('_ci_old_input')['post']['full_name']);
        $result->assertRedirectTo(site_url('employees/create'));
    }

    public function testUpdateEmployeeMasksSensitiveDataInAudit(): void
    {
        $andi = $this->employee('EMP-0014');
        $data = $this->db->table('employees')->where('id', $andi)->get()->getRowArray();

        $this->as('sinta.maharani')->post("employees/{$andi}", [
            ...$data,
            'bank_account_no'         => '9876543210',
            'basic_salary'            => '12.750.000',
            'position_allowance'      => '1.500.000',
            'communication_allowance' => '300.000',
        ])->assertRedirectTo(site_url("employees/{$andi}"));

        $this->seeInDatabase('employees', ['id' => $andi, 'basic_salary' => 12_750_000, 'bank_account_no' => '9876543210']);

        $log = $this->db->table('audit_logs')->where('action', 'employee.update')->where('entity_id', (string) $andi)->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertStringNotContainsString('9876543210', $log['after']);
        $this->assertStringContainsString('12750000', $log['after']);
    }

    public function testResignDeactivatesAccountAndClearsDepartmentHead(): void
    {
        $agus = $this->employee('EMP-0026'); // kepala departemen OPS

        $this->as('sinta.maharani')->post("employees/{$agus}/resign", ['resign_date' => '2026-10-31', 'status' => 'resigned', 'note' => 'Pensiun dini']);

        $this->seeInDatabase('employees', ['id' => $agus, 'status' => 'resigned', 'resign_date' => '2026-10-31']);
        $this->seeInDatabase('users', ['employee_id' => $agus, 'is_active' => 0]);
        $this->seeInDatabase('departments', ['code' => 'OPS', 'head_employee_id' => null]);
        $this->seeInDatabase('audit_logs', ['action' => 'employee.resign', 'entity_id' => (string) $agus]);

        // Tanggal keluar sebelum tanggal masuk ditolak
        $fajar = $this->employee('EMP-0009');
        $this->as('sinta.maharani')->post("employees/{$fajar}/resign", ['resign_date' => '2020-01-01']);
        $this->assertStringContainsString('sebelum tanggal masuk', (string) session('error'));
    }

    public function testComponentOverrides(): void
    {
        $citra     = $this->employee('EMP-0021');
        $transport = (int) $this->db->table('salary_components')->where('code', 'TRANSPORT')->get()->getRow()->id;
        $comm      = (int) $this->db->table('salary_components')->where('code', 'COMM_ALW')->get()->getRow()->id;

        $this->as('sinta.maharani')->post("employees/{$citra}/components", ['amounts' => [$transport => '45.000', $comm => '0']]);

        $this->seeInDatabase('employee_components', ['employee_id' => $citra, 'salary_component_id' => $transport, 'amount' => 45_000]);
        $this->dontSeeInDatabase('employee_components', ['employee_id' => $citra, 'salary_component_id' => $comm]);

        // Override harian dikosongkan → kembali ke tarif default
        $this->as('sinta.maharani')->post("employees/{$citra}/components", ['amounts' => [$transport => '']]);
        $this->dontSeeInDatabase('employee_components', ['employee_id' => $citra, 'salary_component_id' => $transport]);
    }

    public function testEmployeeExportCsv(): void
    {
        $result = $this->as('budi.santoso')->get('employees/export', ['department_id' => (string) $this->db->table('departments')->where('code', 'FIN')->get()->getRow()->id]);

        $result->assertOK();
        $result->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $lines = array_filter(explode("\n", trim($result->response()->getBody())));
        $this->assertCount(4, $lines); // header + 3 karyawan FIN
        $this->assertStringContainsString('Budi Santoso', $result->response()->getBody());
        $this->seeInDatabase('audit_logs', ['action' => 'employee.export', 'username' => 'budi.santoso']);
    }

    // ------------------------------------------------------------------
    // Komponen gaji, hari libur, pengaturan
    // ------------------------------------------------------------------

    public function testSystemSalaryComponentsAreProtected(): void
    {
        $basic = $this->db->table('salary_components')->where('code', 'BASIC')->get()->getRowArray();

        $this->as('sinta.maharani')->post("salary-components/{$basic['id']}/delete");
        $this->seeInDatabase('salary_components', ['code' => 'BASIC']);

        $this->as('sinta.maharani')->post("salary-components/{$basic['id']}", [
            'code' => 'HACK', 'name' => 'Gaji Pokok Karyawan', 'type' => 'deduction', 'calc_type' => 'manual', 'taxable' => '0', 'sort_order' => '10',
        ]);
        $this->seeInDatabase('salary_components', ['id' => $basic['id'], 'code' => 'BASIC', 'name' => 'Gaji Pokok Karyawan', 'type' => 'earning', 'calc_type' => 'formula', 'taxable' => 1]);

        $this->as('sinta.maharani')->post('salary-components', [
            'code' => 'TUNJ_KELUARGA', 'name' => 'Tunjangan Keluarga', 'type' => 'earning', 'calc_type' => 'fixed',
            'default_amount' => '500.000', 'taxable' => '1', 'is_bpjs_base' => '1', 'is_active' => '1', 'sort_order' => '35',
        ]);
        $this->seeInDatabase('salary_components', ['code' => 'TUNJ_KELUARGA', 'is_system' => 0, 'is_bpjs_base' => 1, 'default_amount' => 500_000]);

        $this->as('sinta.maharani')->post('salary-components', ['code' => 'X_FORMULA', 'name' => 'X', 'type' => 'earning', 'calc_type' => 'formula']);
        $this->dontSeeInDatabase('salary_components', ['code' => 'X_FORMULA']);
    }

    public function testHolidaysInFinalizedPeriodsAreLocked(): void
    {
        $this->as('sinta.maharani')->post('holidays', ['date' => '2026-09-14', 'name' => 'Libur Uji', 'type' => 'company']);
        $this->assertStringContainsString('September 2026', (string) (session('errors')['date'] ?? ''));
        $this->dontSeeInDatabase('holidays', ['date' => '2026-09-14']);

        $this->as('sinta.maharani')->post('holidays', ['date' => '2026-11-27', 'name' => 'Family Day', 'type' => 'company'])
            ->assertRedirectTo(site_url('holidays?year=2026'));
        $this->seeInDatabase('holidays', ['date' => '2026-11-27', 'type' => 'company']);
    }

    public function testSuperadminCanUpdateSettingsWithValidation(): void
    {
        $this->as('admin')->post('settings/bpjs', ['settings' => ['bpjs.jkk.company_rate' => '0,54', 'bpjs.kes.max_wage' => '12.000.000']]);
        $this->seeInDatabase('settings', ['key' => 'bpjs.jkk.company_rate', 'value' => '0.540']);
        $this->seeInDatabase('audit_logs', ['action' => 'setting.update', 'entity_id' => 'bpjs']);

        $this->as('admin')->post('settings/bpjs', ['settings' => ['bpjs.jkk.company_rate' => '150']]);
        $this->assertArrayHasKey('settings.bpjs.jkk.company_rate', session('errors'));

        $this->as('admin')->post('settings/tax', ['ptkp' => ['TK/0' => '54.000.000', 'K/3' => '72.500.000']]);
        $this->seeInDatabase('ptkp_rates', ['status' => 'K/3', 'amount' => 72_500_000]);
        $this->seeInDatabase('ptkp_rates', ['status' => 'TK/0', 'amount' => 54_000_000]);
    }

    public function testListPagesRenderSeedData(): void
    {
        $this->as('sinta.maharani')->get('employees', ['q' => 'pratama'])->assertSee('Andi Pratama');
        $this->as('sinta.maharani')->get('employees', ['status' => 'resigned'])->assertSee('Hadi Gunawan');
        $this->as('sinta.maharani')->get('employees/14')->assertSee('Riwayat Gaji');
        $this->as('sinta.maharani')->get('positions')->assertSee('Senior Software Engineer');
        $this->as('sinta.maharani')->get('holidays', ['year' => 2026])->assertSee('Hari Proklamasi Kemerdekaan RI');
        $this->as('admin')->get('settings', ['tab' => 'tax'])->assertSee('Tarif Efektif Rata-rata');
        $this->as('budi.santoso')->get('dashboard')->assertSee('Komposisi Biaya Payroll');
    }

    // ------------------------------------------------------------------

    private function as(string $username): static
    {
        // Beberapa request dalam satu test berbagi service; reset agar user tidak ter-cache
        Services::resetSingle('auth');

        $user = (new UserModel())->findByUsername($username);

        return $this->withSession([AuthService::SESSION_KEY => $user['id']]);
    }

    private function employee(string $nip): int
    {
        return (int) $this->db->table('employees')->where('nip', $nip)->get()->getRow()->id;
    }

    /**
     * @return array{int, int} [department_id, position_id]
     */
    private function position(string $code): array
    {
        $row = $this->db->table('positions')->where('code', $code)->get()->getRow();

        return [(int) $row->department_id, (int) $row->id];
    }

    /**
     * get() dengan query string (FeatureTestTrait::get mengirim params sebagai $_GET).
     *
     * @param array<string, mixed>|null $params
     */
    public function get(string $path, ?array $params = null): TestResponse
    {
        return $this->call('GET', $path, $params);
    }
}
