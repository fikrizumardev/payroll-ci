<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\UserModel;
use App\Services\Auth\AuthService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use InvalidArgumentException;

/**
 * Layout aplikasi & dashboard dirender dengan data seed untuk setiap peran.
 *
 * @internal
 */
final class LayoutTest extends CIUnitTestCase
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

    public function testAdminDashboardShowsCompanySummary(): void
    {
        $result = $this->actingAs('admin')->get('dashboard');

        $result->assertOK();
        $result->assertSee('Karyawan Aktif');
        $result->assertSee('30 orang');
        $result->assertSee('Aktivitas Terbaru');
        $result->assertSee('Pengguna');   // menu khusus Super Admin
        $result->assertSee('Audit Log');
    }

    public function testHrDashboardShowsCompanyAndPersonalSummary(): void
    {
        $result = $this->actingAs('sinta.maharani')->get('dashboard');

        $result->assertOK();
        $result->assertSee('Karyawan Aktif');
        $result->assertSee('HR Manager');
        $result->assertDontSee('Pengguna');
    }

    public function testEmployeeDashboardShowsOnlyPersonalData(): void
    {
        $result = $this->actingAs('andi.pratama')->get('dashboard');

        $result->assertOK();
        $result->assertSee('Andi Pratama');
        $result->assertSee('EMP-0014');
        $result->assertDontSee('Karyawan Aktif');
        $result->assertDontSee('Aktivitas Terbaru');
        $result->assertDontSee('Master Data');
    }

    public function testEmployeeWithoutOvertimeDoesNotBreakDashboard(): void
    {
        // Regresi: SUM(hours) = NULL sempat memicu error cast pada model
        $this->db->table('overtimes')->truncate();

        $this->actingAs('andi.pratama')->get('dashboard')->assertOK();
    }

    public function testUnbuiltModulesAreMarkedComingSoon(): void
    {
        $result = $this->actingAs('admin')->get('dashboard');

        $result->assertSee('Segera');
        $result->assertSeeElement('a[aria-current=page]');
    }

    public function testComponentRenderingIsIsolated(): void
    {
        helper('ui');

        $html = component('page_header', ['title' => 'Judul <b>uji</b>']);
        $this->assertStringContainsString('Judul &lt;b&gt;uji&lt;/b&gt;', $html);

        $this->expectException(InvalidArgumentException::class);
        component('tidak_ada');
    }

    private function actingAs(string $username): static
    {
        $user = (new UserModel())->findByUsername($username);

        return $this->withSession([AuthService::SESSION_KEY => $user['id']]);
    }
}
