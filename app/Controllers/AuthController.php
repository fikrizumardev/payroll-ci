<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Seeds\UserSeeder;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseController
{
    /** Maksimum percobaan login per menit untuk kombinasi IP + username. */
    private const ATTEMPTS_PER_MINUTE = 5;

    /** Maksimum percobaan login per menit dari satu IP (mencegah password spraying). */
    private const ATTEMPTS_PER_MINUTE_PER_IP = 20;

    public function login(): string
    {
        return view('auth/login', [
            'title'        => 'Masuk',
            'demoAccounts' => ENVIRONMENT === 'development' ? $this->demoAccounts() : [],
        ]);
    }

    public function attempt(): RedirectResponse|ResponseInterface
    {
        $username = UserModel::normalize((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $remember = (bool) $this->request->getPost('remember');

        if (! $this->validateData(['username' => $username, 'password' => $password], [
            'username' => [
                'label'  => 'Username',
                'rules'  => 'required|regex_match[/^[a-z0-9._]{4,30}$/]',
                'errors' => [
                    'required'    => 'Username wajib diisi.',
                    'regex_match' => 'Format username tidak valid.',
                ],
            ],
            'password' => [
                'label'  => 'Password',
                'rules'  => 'required|max_length[255]',
                'errors' => ['required' => 'Password wajib diisi.'],
            ],
        ])) {
            return redirect()->route('login')->with('errors', $this->validator->getErrors())->with('old_username', $username)->with('old_remember', $remember);
        }

        if (($retryAfter = $this->throttled($username)) !== null) {
            $this->response->setStatusCode(429)->setHeader('Retry-After', (string) $retryAfter);

            return $this->response->setBody(view('auth/login', [
                'title'        => 'Masuk',
                'demoAccounts' => ENVIRONMENT === 'development' ? $this->demoAccounts() : [],
                'oldUsername'  => $username,
                'errorMessage' => "Terlalu banyak percobaan login. Coba lagi dalam {$retryAfter} detik.",
            ]));
        }

        $result = service('auth')->attempt($username, $password, $remember);

        if (! $result->ok()) {
            return redirect()->route('login')->with('error', $result->message())->with('old_username', $username)->with('old_remember', $remember);
        }

        $intended = session()->get('auth_intended_path');
        session()->remove('auth_intended_path');

        return redirect()->to($this->safeRedirectPath($intended) ?? url_to('dashboard'))
            ->with('success', 'Selamat datang, ' . $result->user['name'] . '!');
    }

    public function logout(): RedirectResponse
    {
        service('auth')->logout();

        return redirect()->route('login')->with('success', 'Anda telah keluar dari aplikasi.');
    }

    /**
     * @return int|null detik hingga boleh mencoba lagi, atau null bila tidak dibatasi
     */
    private function throttled(string $username): ?int
    {
        $throttler = service('throttler');
        $ip        = $this->request->getIPAddress();

        $allowedForUser = $throttler->check('login-' . md5($ip . '|' . $username), self::ATTEMPTS_PER_MINUTE, MINUTE);
        $waitForUser    = $throttler->getTokenTime();

        $allowedForIp = $throttler->check('login-ip-' . md5($ip), self::ATTEMPTS_PER_MINUTE_PER_IP, MINUTE);
        $waitForIp    = $throttler->getTokenTime();

        if ($allowedForUser && $allowedForIp) {
            return null;
        }

        return max(1, $allowedForUser ? $waitForIp : $waitForUser);
    }

    /**
     * Hanya path internal yang boleh dipakai sebagai tujuan redirect (cegah open redirect).
     */
    private function safeRedirectPath(mixed $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        return site_url(ltrim($path, '/'));
    }

    /**
     * Akun demo hasil seeder — hanya ditampilkan di environment development.
     *
     * @return list<array{username: string, password: string, role: string}>
     */
    private function demoAccounts(): array
    {
        return [
            ['username' => 'admin', 'password' => UserSeeder::ADMIN_PASSWORD, 'role' => 'Super Admin'],
            ['username' => 'sinta.maharani', 'password' => UserSeeder::DEFAULT_PASSWORD, 'role' => 'HR'],
            ['username' => 'budi.santoso', 'password' => UserSeeder::DEFAULT_PASSWORD, 'role' => 'Finance'],
            ['username' => 'andi.pratama', 'password' => UserSeeder::DEFAULT_PASSWORD, 'role' => 'Karyawan'],
        ];
    }
}
