<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLogModel;
use CodeIgniter\HTTP\IncomingRequest;
use Throwable;

/**
 * Pencatat jejak aktivitas pengguna ke tabel `audit_logs`.
 *
 *   service('auditLogger')->log('payroll.approve', 'payroll_period', $id, 'Menyetujui payroll Oktober 2026');
 */
class AuditLogger
{
    public function __construct(private readonly AuditLogModel $model = new AuditLogModel())
    {
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, mixed>|null $actor  user yang melakukan aksi; default user yang sedang login
     */
    public function log(
        string $action,
        ?string $entity = null,
        int|string|null $entityId = null,
        ?string $description = null,
        ?array $before = null,
        ?array $after = null,
        ?array $actor = null,
    ): void {
        $actor ??= $this->currentUser();
        $request = service('request');

        $data = [
            'user_id'     => $actor['id'] ?? null,
            'username'    => $actor['username'] ?? null,
            'action'      => $action,
            'entity'      => $entity,
            'entity_id'   => $entityId === null ? null : (string) $entityId,
            'description' => $description === null ? null : mb_substr($description, 0, 255),
            'before'      => $before,
            'after'       => $after,
            'ip_address'  => $request instanceof IncomingRequest ? $request->getIPAddress() : null,
            'user_agent'  => $request instanceof IncomingRequest ? mb_substr((string) $request->getUserAgent(), 0, 255) : null,
        ];

        try {
            $this->model->insert($data);
        } catch (Throwable $e) {
            // Audit log tidak boleh menggagalkan aksi utama
            log_message('error', 'Gagal menulis audit log {action}: {message}', ['action' => $action, 'message' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentUser(): ?array
    {
        try {
            return service('auth')->user();
        } catch (Throwable) {
            return null;
        }
    }
}
