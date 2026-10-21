<?php

namespace App\Controllers;

use App\Services\Auth\AuthService;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller dasar seluruh halaman aplikasi.
 *
 * Demi keamanan, method yang bukan aksi route harus dideklarasikan protected/private.
 */
abstract class BaseController extends Controller
{
    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    protected AuthService $auth;

    /**
     * @param CLIRequest|IncomingRequest $request
     *
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->auth = service('auth');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentUser(): ?array
    {
        return $this->auth->user();
    }
}
