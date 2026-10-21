<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Halaman tamu (login): user yang sudah login langsung diarahkan ke dashboard.
 */
class GuestFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('auth')->check()) {
            return redirect()->route('dashboard');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
