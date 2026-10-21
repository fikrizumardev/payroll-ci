<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pembatasan akses per peran. Pemakaian di route: ['filter' => 'role:hr,finance']
 * Super Admin selalu diizinkan.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');

        if (! $auth->check()) {
            return redirect()->route('login');
        }

        if ($auth->hasRole(...(array) $arguments)) {
            return null;
        }

        $response = service('response')->setStatusCode(403);

        if ($request instanceof IncomingRequest && $request->isAJAX()) {
            return $response->setJSON(['message' => 'Anda tidak memiliki akses ke fitur ini.']);
        }

        return $response->setBody(view('errors/html/error_403'));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
