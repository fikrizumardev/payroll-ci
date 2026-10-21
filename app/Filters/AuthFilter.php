<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Wajib login. User yang ditandai harus ganti password diarahkan
 * ke halaman ganti password sebelum boleh membuka halaman lain.
 */
class AuthFilter implements FilterInterface
{
    /** Route yang tetap boleh dibuka saat user wajib ganti password. */
    private const PASSWORD_CHANGE_ALLOWED = ['account/password', 'logout'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            if ($request instanceof IncomingRequest && $request->isAJAX()) {
                return service('response')->setStatusCode(401)->setJSON(['message' => 'Sesi berakhir. Silakan login kembali.']);
            }

            if ($request instanceof IncomingRequest && $request->getMethod() === 'GET') {
                // Kembalikan user ke halaman yang dituju setelah login
                $query = $request->getUri()->getQuery();
                session()->set('auth_intended_path', '/' . ltrim($request->getPath(), '/') . ($query !== '' ? '?' . $query : ''));
            }

            return redirect()->route('login')->with('warning', 'Silakan login terlebih dahulu.');
        }

        $path = $request instanceof IncomingRequest ? trim($request->getPath(), '/') : '';

        if ($auth->user()['must_change_password'] && ! in_array($path, self::PASSWORD_CHANGE_ALLOWED, true)) {
            return redirect()->route('account.password')->with('warning', 'Demi keamanan, silakan ganti password Anda terlebih dahulu.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Halaman yang butuh login tidak boleh di-cache browser (tombol Back setelah logout)
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->setHeader('Pragma', 'no-cache');

        return $response;
    }
}
