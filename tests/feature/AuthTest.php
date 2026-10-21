<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\UserModel;
use App\Services\Auth\AuthService;
use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Tests\Support\Database\Seeds\AuthTestSeeder;

/**
 * Autentikasi username + password: login, logout, throttle, lockout,
 * akun nonaktif, wajib ganti password, dan filter peran.
 *
 * @internal
 */
final class AuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private const CSRF_TOKEN = 'test-csrf-token-0123456789abcdef';

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = AuthTestSeeder::class;

    /** Service bersama (mis. auth) di-reset agar tiap test mulai dari kondisi bersih. */
    protected $setUpMethods = ['resetFactories', 'resetServices', 'mockCache', 'mockEmail', 'mockSession'];

    protected function setUp(): void
    {
        parent::setUp();

        // Bersihkan bucket Throttler dari test sebelumnya
        cache()->clean();

        // Token CSRF dirotasi setiap request sehingga sulit disimulasikan berantai;
        // CSRF dimatikan di sini kecuali pada test khusus CSRF.
        if ($this->name() !== 'testPostWithoutCsrfTokenIsRejected') {
            $filters                    = config('Filters');
            $filters->globals['before'] = array_values(array_diff($filters->globals['before'], ['csrf']));
            Factories::injectMock('config', 'Filters', $filters);
        }
    }

    public function testLoginPageHasUsernameFieldAndNoEmail(): void
    {
        $result = $this->get('login');

        $result->assertOK();
        $result->assertSeeElement('input[name=username]');
        $result->assertSeeElement('input[name=password]');
        $this->assertStringNotContainsStringIgnoringCase('email', $result->getBody());
        $this->assertNotContains('email', $this->db->getFieldNames('users'));
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->get('dashboard')->assertRedirectTo(site_url('login'));
    }

    public function testSuccessfulLoginIsCaseInsensitive(): void
    {
        $result = $this->login('  ADMIN ', AuthTestSeeder::PASSWORD);

        $result->assertRedirectTo(site_url('dashboard'));
        $result->assertSessionHas(AuthService::SESSION_KEY);
        $this->seeInDatabase('audit_logs', ['action' => 'auth.login', 'username' => 'admin']);

        $user = (new UserModel())->findByUsername('admin');
        $this->assertNotNull($user['last_login_at']);
    }

    public function testWrongPasswordShowsGenericMessage(): void
    {
        $result = $this->login('admin', 'salah-sekali-1');

        $result->assertRedirect();
        $result->assertSessionHas('error', 'Username atau password salah.');
        $result->assertSessionMissing(AuthService::SESSION_KEY);
        $this->seeInDatabase('users', ['username' => 'admin', 'failed_attempts' => 1]);
    }

    public function testUnknownUsernameShowsSameGenericMessage(): void
    {
        $result = $this->login('tidak.terdaftar', 'apa-saja-123');

        $result->assertSessionHas('error', 'Username atau password salah.');
        $this->seeInDatabase('audit_logs', ['action' => 'auth.login_failed', 'username' => 'tidak.terdaftar']);
    }

    public function testInvalidUsernameFormatFailsValidation(): void
    {
        $result = $this->login('ab', 'apa-saja-123');

        $result->assertSessionHas('errors');
        $result->assertSessionMissing(AuthService::SESSION_KEY);
    }

    public function testInactiveUserCannotLogin(): void
    {
        $result = $this->login('karyawan.nonaktif', AuthTestSeeder::PASSWORD);

        $result->assertSessionHas('error', 'Akun Anda nonaktif. Silakan hubungi administrator.');
        $result->assertSessionMissing(AuthService::SESSION_KEY);
    }

    public function testAccountIsLockedAfterTooManyFailures(): void
    {
        $this->db->table('users')->where('username', 'hr.user')->update(['failed_attempts' => AuthService::MAX_FAILED_ATTEMPTS - 1]);

        $result = $this->login('hr.user', 'salah-lagi-99');
        $this->assertStringContainsString('dikunci sementara', (string) session('error'));

        // Password benar pun ditolak selama masa kunci
        $this->resetAuth();
        $result = $this->login('hr.user', AuthTestSeeder::PASSWORD);
        $this->assertStringContainsString('dikunci sementara', (string) session('error'));
        $result->assertSessionMissing(AuthService::SESSION_KEY);
        $this->seeInDatabase('audit_logs', ['action' => 'auth.account_locked', 'username' => 'hr.user']);
    }

    public function testLoginIsThrottledAfterFiveAttemptsPerMinute(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->resetAuth();
            $this->login('finance.user', "salah-{$i}-abc")->assertRedirect();
        }

        $this->resetAuth();
        $result = $this->login('finance.user', AuthTestSeeder::PASSWORD);

        $result->assertStatus(429);
        $result->assertSee('Terlalu banyak percobaan login');
        $result->assertSessionMissing(AuthService::SESSION_KEY);
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $result = $this->post('login', ['username' => 'admin', 'password' => AuthTestSeeder::PASSWORD]);

        $result->assertRedirect();
        $result->assertSessionMissing(AuthService::SESSION_KEY);
    }

    public function testLogoutEndsSession(): void
    {
        $this->login('admin', AuthTestSeeder::PASSWORD);
        $this->resetAuth();

        $result = $this->withSession($this->sessionWithCsrf())->post('logout', ['csrf_token' => self::CSRF_TOKEN]);

        $result->assertRedirectTo(site_url('login'));
        $result->assertSessionMissing(AuthService::SESSION_KEY);
        $this->seeInDatabase('audit_logs', ['action' => 'auth.logout', 'username' => 'admin']);
    }

    public function testRememberMeIssuesHashedToken(): void
    {
        $result = $this->login('karyawan.satu', AuthTestSeeder::PASSWORD, remember: true);

        $result->assertCookie(AuthService::REMEMBER_COOKIE);
        $cookie = $result->response()->getCookie(AuthService::REMEMBER_COOKIE);
        [$selector, $validator] = explode(':', $cookie->getValue());

        $this->assertTrue($cookie->isHTTPOnly());
        $this->seeInDatabase('user_tokens', ['selector' => $selector, 'token_hash' => hash('sha256', $validator)]);
        $this->dontSeeInDatabase('user_tokens', ['token_hash' => $validator]);
    }

    public function testMustChangePasswordIsEnforced(): void
    {
        $this->login('karyawan.baru', AuthTestSeeder::PASSWORD);
        $this->resetAuth();

        $this->withSession($this->sessionWithCsrf())->get('dashboard')->assertRedirectTo(site_url('account/password'));

        $this->resetAuth();
        $result = $this->withSession($this->sessionWithCsrf())->post('account/password', [
            'csrf_token'                => self::CSRF_TOKEN,
            'current_password'          => AuthTestSeeder::PASSWORD,
            'new_password'              => 'PasswordBaru2026',
            'new_password_confirmation' => 'PasswordBaru2026',
        ]);

        $result->assertRedirectTo(site_url('dashboard'));
        $user = (new UserModel())->findByUsername('karyawan.baru');
        $this->assertFalse($user['must_change_password']);
        $this->assertTrue(password_verify('PasswordBaru2026', $user['password_hash']));
    }

    public function testPasswordPolicy(): void
    {
        $this->assertNotSame([], AuthService::passwordPolicyErrors('pendek1'));
        $this->assertNotSame([], AuthService::passwordPolicyErrors('tanpaangka'));
        $this->assertNotSame([], AuthService::passwordPolicyErrors('admin12345', 'admin'));
        $this->assertSame([], AuthService::passwordPolicyErrors('Payroll2026', 'andi.pratama'));
    }

    public function testRoleFilter(): void
    {
        $this->withRoutes([
            ['get', 'test/hr-only', 'DashboardController::index', ['filter' => ['auth', 'role:hr']]],
        ]);

        $this->login('karyawan.satu', AuthTestSeeder::PASSWORD);
        $this->resetAuth();
        $this->withSession($this->sessionWithCsrf())->get('test/hr-only')->assertStatus(403);

        $this->login('hr.user', AuthTestSeeder::PASSWORD);
        $this->resetAuth();
        $this->withSession($this->sessionWithCsrf())->get('test/hr-only')->assertOK();

        // Super Admin selalu lolos
        $this->login('admin', AuthTestSeeder::PASSWORD);
        $this->resetAuth();
        $this->withSession($this->sessionWithCsrf())->get('test/hr-only')->assertOK();
    }

    private function login(string $username, string $password, bool $remember = false): TestResponse
    {
        $data = ['csrf_token' => self::CSRF_TOKEN, 'username' => $username, 'password' => $password];

        if ($remember) {
            $data['remember'] = '1';
        }

        return $this->withSession($this->sessionWithCsrf([]))->post('login', $data);
    }

    /**
     * Session berisi token CSRF + data session yang sudah ada (mis. user yang login).
     *
     * @param array<string, mixed>|null $base
     *
     * @return array<string, mixed>
     */
    private function sessionWithCsrf(?array $base = null): array
    {
        $base ??= $_SESSION ?? [];

        return ['csrf_token' => self::CSRF_TOKEN] + $base;
    }

    /**
     * Setiap request di test memakai service bersama yang sama; reset agar
     * AuthService/Security membaca ulang state session seperti request baru.
     */
    private function resetAuth(): void
    {
        Services::resetSingle('auth');
        Services::resetSingle('security');
    }
}
