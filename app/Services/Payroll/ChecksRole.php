<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\UserModel;

/**
 * Pengecekan peran pelaku aksi payroll. Super Admin selalu diizinkan.
 */
trait ChecksRole
{
    /**
     * @param array<string, mixed> $actor
     */
    protected static function actorHasRole(array $actor, string ...$roles): bool
    {
        $role = $actor['role'] ?? null;

        return $role === UserModel::ROLE_SUPERADMIN || in_array($role, $roles, true);
    }

    /**
     * @param array<string, mixed> $actor
     */
    protected static function ensureRole(array $actor, string $action, string ...$roles): void
    {
        if (! self::actorHasRole($actor, ...$roles)) {
            $labels = implode(' / ', array_map(static fn (string $role): string => UserModel::ROLES[$role] ?? $role, $roles));

            throw new PayrollException("Aksi \"{$action}\" hanya dapat dilakukan oleh {$labels}.");
        }
    }
}
