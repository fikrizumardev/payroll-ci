<?php

namespace App\Controllers;

use App\Services\Auth\AuthService;
use App\Services\PayrollPeriodGuard;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
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

    /**
     * Apakah user boleh mengubah data master (HR; Super Admin selalu boleh).
     */
    protected function canManage(): bool
    {
        return $this->auth->hasRole('hr');
    }

    /**
     * Kembali ke halaman form dengan error validasi + input lama.
     * `$modal` dipakai halaman CRUD-modal untuk membuka ulang modal yang sama.
     *
     * @param array<string, string> $errors
     */
    protected function backWithErrors(string $url, array $errors, ?string $modal = null): RedirectResponse
    {
        $redirect = redirect()->to($url)->withInput()->with('errors', $errors)
            ->with('error', 'Data belum bisa disimpan. Periksa kembali isian yang ditandai.');

        return $modal === null ? $redirect : $redirect->with('open_modal', $modal);
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    protected function audit(string $action, string $entity, int|string|null $entityId, string $description, ?array $before = null, ?array $after = null): void
    {
        service('auditLogger')->log($action, $entity, $entityId, $description, $before, $after);
    }

    /**
     * Ambil hanya kolom yang berubah (untuk audit log before/after).
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    /**
     * Pengingat bila perubahan data menyentuh payroll yang sudah di-generate.
     *
     * @param list<string> $dates
     */
    protected function regenerateHint(array $dates, string $subject): ?string
    {
        $periods = PayrollPeriodGuard::make()->staleGeneratedPeriods($dates);

        return $periods === [] ? null : 'Payroll ' . implode(', ', $periods) . " sudah di-generate — generate ulang agar {$subject} ikut terhitung.";
    }

    protected function diff(array $before, array $after): array
    {
        $old       = [];
        $new       = [];
        $normalize = static fn (mixed $value): string => is_bool($value) ? (string) (int) $value : (string) ($value ?? '');

        foreach ($after as $key => $value) {
            if (array_key_exists($key, $before) && $normalize($before[$key]) !== $normalize($value)) {
                $old[$key] = $before[$key];
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }
}
