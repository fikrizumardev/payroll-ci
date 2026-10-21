<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\UserModel;
use App\Models\UserTokenModel;
use App\Services\AuditLogger;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Session\SessionInterface;

/**
 * Autentikasi berbasis session dengan username + password.
 *
 * - Password: password_hash()/password_verify(), di-rehash otomatis bila algoritma berubah.
 * - Brute-force: akun dikunci sementara setelah MAX_FAILED_ATTEMPTS gagal berturut-turut
 *   (pembatasan per menit ditangani Throttler di AuthController).
 * - "Ingat saya": token selector:validator; hanya hash SHA-256 validator yang disimpan,
 *   validator dirotasi setiap kali dipakai.
 */
class AuthService
{
    public const SESSION_KEY     = 'auth_user_id';
    public const REMEMBER_COOKIE = 'payroll_remember';

    public const MAX_FAILED_ATTEMPTS = 10;
    public const LOCK_MINUTES        = 15;
    public const REMEMBER_DAYS       = 30;
    public const MIN_PASSWORD_LENGTH = 8;

    /** Hash pembanding agar waktu respons username tak terdaftar ≈ username terdaftar. */
    private const TIMING_GUARD_HASH = '$2y$10$WCa5ADJm8.qoE0JJAh2QGeqPfmGU8HO2QWKj6KO/.jJUTUhjhn.YS';

    /** @var array<string, mixed>|null */
    private ?array $user = null;

    private bool $resolved = false;

    /**
     * Operasi cookie yang menunggu dipasang ke response (lihat RememberCookieFilter).
     *
     * @var list<array{action: 'set'|'delete', value?: string, expire?: int}>
     */
    private array $cookieQueue = [];

    public function __construct(
        private readonly UserModel $users,
        private readonly UserTokenModel $tokens,
        private readonly AuditLogger $audit,
        private readonly SessionInterface $session,
    ) {
    }

    public function attempt(string $username, string $password, bool $remember = false): AuthResult
    {
        $username = UserModel::normalize($username);
        $user     = $username === '' ? null : $this->users->findByUsername($username);

        if ($user === null) {
            password_verify($password, self::TIMING_GUARD_HASH);
            $this->audit->log('auth.login_failed', 'user', null, "Login gagal: username '{$username}' tidak terdaftar", actor: ['username' => mb_substr($username, 0, 30)]);

            return AuthResult::invalid();
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $this->audit->log('auth.login_locked', 'user', $user['id'], 'Login ditolak: akun sedang dikunci', actor: $user);

            return AuthResult::locked((int) ceil((strtotime($user['locked_until']) - time()) / 60));
        }

        if (! password_verify($password, $user['password_hash'])) {
            return $this->registerFailedAttempt($user);
        }

        if (! $user['is_active']) {
            $this->audit->log('auth.login_inactive', 'user', $user['id'], 'Login ditolak: akun nonaktif', actor: $user);

            return AuthResult::inactive();
        }

        $this->completeLogin($user, $password);

        if ($remember) {
            $this->issueRememberToken((int) $user['id']);
        }

        $this->audit->log('auth.login', 'user', $user['id'], 'Login berhasil', actor: $user);

        return AuthResult::success($this->user());
    }

    /**
     * User yang sedang login (di-cache per request). Memulihkan sesi dari cookie
     * "Ingat saya" bila session kosong. Mengembalikan null jika akun dinonaktifkan.
     *
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $userId         = $this->session->get(self::SESSION_KEY);

        if ($userId === null) {
            $this->user = $this->restoreFromRememberCookie();

            return $this->user;
        }

        $user = $this->users->find((int) $userId);

        if ($user === null || ! $user['is_active']) {
            $this->clearSession();

            return $this->user = null;
        }

        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        return $this->user()['id'] ?? null;
    }

    /**
     * Superadmin selalu lolos pengecekan peran.
     */
    public function hasRole(string ...$roles): bool
    {
        $role = $this->user()['role'] ?? null;

        return $role !== null && ($role === UserModel::ROLE_SUPERADMIN || in_array($role, $roles, true));
    }

    public function logout(): void
    {
        $user = $this->user();

        if ($user !== null) {
            $this->audit->log('auth.logout', 'user', $user['id'], 'Logout', actor: $user);
        }

        $this->forgetRememberToken();
        $this->clearSession();
    }

    /**
     * @return list<string> pesan error; kosong berarti berhasil
     */
    public function changePassword(string $currentPassword, string $newPassword, string $confirmation): array
    {
        $user = $this->user();

        if ($user === null) {
            return ['Sesi Anda sudah berakhir. Silakan login kembali.'];
        }

        $errors = [];

        if (! password_verify($currentPassword, $user['password_hash'])) {
            $errors[] = 'Password saat ini tidak sesuai.';
        }

        $errors = [...$errors, ...self::passwordPolicyErrors($newPassword, $user['username'])];

        if ($newPassword !== $confirmation) {
            $errors[] = 'Konfirmasi password baru tidak cocok.';
        }

        if ($newPassword !== '' && password_verify($newPassword, $user['password_hash'])) {
            $errors[] = 'Password baru harus berbeda dari password saat ini.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->users->update($user['id'], [
            'password_hash'        => password_hash($newPassword, PASSWORD_DEFAULT),
            'must_change_password' => 0,
            'password_changed_at'  => date('Y-m-d H:i:s'),
        ]);

        // Semua perangkat lain yang memakai "Ingat saya" harus login ulang
        $this->tokens->where('user_id', $user['id'])->delete();
        $this->cookieQueue[] = ['action' => 'delete'];
        $this->session->regenerate(true);

        $this->audit->log('auth.password_changed', 'user', $user['id'], 'Mengganti password', actor: $user);
        $this->refresh();

        return [];
    }

    /**
     * @return list<string>
     */
    public static function passwordPolicyErrors(string $password, ?string $username = null): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'Password minimal ' . self::MIN_PASSWORD_LENGTH . ' karakter.';
        }

        if (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
            $errors[] = 'Password harus mengandung huruf dan angka.';
        }

        if ($username !== null && $username !== '' && str_contains(strtolower($password), strtolower($username))) {
            $errors[] = 'Password tidak boleh mengandung username.';
        }

        return $errors;
    }

    /**
     * Memasang antrean cookie ke response akhir (dipanggil RememberCookieFilter).
     */
    public function applyQueuedCookies(ResponseInterface $response): void
    {
        foreach ($this->cookieQueue as $operation) {
            if ($operation['action'] === 'set') {
                $response->setCookie(self::REMEMBER_COOKIE, $operation['value'], $operation['expire'], httponly: true, samesite: 'Lax');
            } else {
                $response->deleteCookie(self::REMEMBER_COOKIE);
            }
        }

        $this->cookieQueue = [];
    }

    public function refresh(): void
    {
        $this->resolved = false;
        $this->user     = null;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function registerFailedAttempt(array $user): AuthResult
    {
        $attempts = (int) $user['failed_attempts'] + 1;
        $data     = ['failed_attempts' => $attempts];

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $data = [
                'failed_attempts' => 0,
                'locked_until'    => date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60),
            ];
        }

        $this->users->update($user['id'], $data);

        if (isset($data['locked_until'])) {
            $this->audit->log('auth.account_locked', 'user', $user['id'], 'Akun dikunci ' . self::LOCK_MINUTES . ' menit setelah ' . self::MAX_FAILED_ATTEMPTS . ' kali gagal login', actor: $user);

            return AuthResult::locked(self::LOCK_MINUTES);
        }

        $this->audit->log('auth.login_failed', 'user', $user['id'], 'Login gagal: password salah', actor: $user);

        return AuthResult::invalid();
    }

    /**
     * @param array<string, mixed> $user
     */
    private function completeLogin(array $user, ?string $plainPassword = null): void
    {
        $data = [
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => $this->ipAddress(),
        ];

        if ($plainPassword !== null && password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $data['password_hash'] = password_hash($plainPassword, PASSWORD_DEFAULT);
        }

        $this->users->update($user['id'], $data);

        // Cegah session fixation
        $this->session->regenerate(true);
        $this->session->set(self::SESSION_KEY, (int) $user['id']);

        $this->refresh();
    }

    private function issueRememberToken(int $userId): void
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = time() + self::REMEMBER_DAYS * DAY;

        $this->tokens->insert([
            'user_id'    => $userId,
            'selector'   => $selector,
            'token_hash' => hash('sha256', $validator),
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'user_agent' => $this->userAgent(),
            'ip_address' => $this->ipAddress(),
        ]);

        $this->cookieQueue[] = ['action' => 'set', 'value' => $selector . ':' . $validator, 'expire' => self::REMEMBER_DAYS * DAY];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function restoreFromRememberCookie(): ?array
    {
        $cookie = $this->rememberCookie();

        if ($cookie === null) {
            return null;
        }

        [$selector, $validator] = $cookie;

        $token = $this->tokens->where('selector', $selector)->first();

        if ($token === null || strtotime($token['expires_at']) < time()) {
            $this->cookieQueue[] = ['action' => 'delete'];

            return null;
        }

        if (! hash_equals($token['token_hash'], hash('sha256', $validator))) {
            // Validator tidak cocok: kemungkinan cookie dicuri, cabut token ini
            $this->tokens->delete($token['id']);
            $this->cookieQueue[] = ['action' => 'delete'];

            return null;
        }

        $user = $this->users->find($token['user_id']);

        if ($user === null || ! $user['is_active']) {
            $this->tokens->delete($token['id']);
            $this->cookieQueue[] = ['action' => 'delete'];

            return null;
        }

        // Rotasi validator
        $newValidator = bin2hex(random_bytes(32));
        $this->tokens->update($token['id'], [
            'token_hash'   => hash('sha256', $newValidator),
            'last_used_at' => date('Y-m-d H:i:s'),
            'expires_at'   => date('Y-m-d H:i:s', time() + self::REMEMBER_DAYS * DAY),
        ]);
        $this->cookieQueue[] = ['action' => 'set', 'value' => $selector . ':' . $newValidator, 'expire' => self::REMEMBER_DAYS * DAY];

        $this->session->regenerate(true);
        $this->session->set(self::SESSION_KEY, (int) $user['id']);
        $this->users->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $this->ipAddress()]);
        $this->audit->log('auth.login_remembered', 'user', $user['id'], 'Login otomatis melalui "Ingat saya"', actor: $user);

        return $user;
    }

    private function forgetRememberToken(): void
    {
        $cookie = $this->rememberCookie();

        if ($cookie !== null) {
            $this->tokens->where('selector', $cookie[0])->delete();
        }

        $this->cookieQueue[] = ['action' => 'delete'];
    }

    /**
     * @return array{string, string}|null [selector, validator]
     */
    private function rememberCookie(): ?array
    {
        $request = service('request');
        $value   = $request instanceof IncomingRequest ? $request->getCookie(self::REMEMBER_COOKIE) : null;

        if (! is_string($value) || ! preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $value, $matches)) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }

    private function clearSession(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->session->regenerate(true);
        $this->refresh();
        $this->resolved = true;
    }

    private function ipAddress(): ?string
    {
        $request = service('request');

        return $request instanceof IncomingRequest ? $request->getIPAddress() : null;
    }

    private function userAgent(): ?string
    {
        $request = service('request');

        return $request instanceof IncomingRequest ? mb_substr((string) $request->getUserAgent(), 0, 255) : null;
    }
}
