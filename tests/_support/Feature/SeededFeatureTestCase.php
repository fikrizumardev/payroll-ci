<?php

declare(strict_types=1);

namespace Tests\Support\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\UserModel;
use App\Services\Auth\AuthService;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Dasar feature test modul aplikasi: database di-seed sekali per kelas dengan
 * data contoh (DatabaseSeeder), CSRF dimatikan (diuji terpisah di AuthTest),
 * dan helper login sebagai user seed.
 *
 * Catatan: data berbagi state antar-test dalam satu kelas — setiap test
 * memakai karyawan/tanggal yang berbeda agar tidak saling memengaruhi.
 */
abstract class SeededFeatureTestCase extends CIUnitTestCase
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

        $filters                    = config('Filters');
        $filters->globals['before'] = array_values(array_diff($filters->globals['before'], ['csrf']));
        Factories::injectMock('config', 'Filters', $filters);
    }

    /**
     * @param array<string, mixed> $session data session tambahan
     */
    protected function as(string $username, array $session = []): static
    {
        // Beberapa request dalam satu test berbagi service; reset agar user tidak ter-cache
        Services::resetSingle('auth');
        Services::resetSingle('payroll');

        $user = (new UserModel())->findByUsername($username);

        return $this->withSession([AuthService::SESSION_KEY => $user['id']] + $session);
    }

    protected function employee(string $nip): int
    {
        return (int) $this->db->table('employees')->where('nip', $nip)->get()->getRow()->id;
    }

    protected function period(int $year, int $month): array
    {
        return $this->db->table('payroll_periods')->where(['year' => $year, 'month' => $month])->get()->getRowArray();
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
