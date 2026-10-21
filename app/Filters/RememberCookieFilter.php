<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Filter global (after): memasang cookie "Ingat saya" yang diantrekan AuthService
 * ke response akhir — termasuk RedirectResponse, yang tidak otomatis membawa
 * cookie dari response global.
 */
class RememberCookieFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        service('auth')->applyQueuedCookies($response);

        return $response;
    }
}
