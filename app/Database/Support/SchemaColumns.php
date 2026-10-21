<?php

declare(strict_types=1);

namespace App\Database\Support;

/**
 * Definisi kolom yang dipakai berulang di migrasi, supaya tipe data
 * konsisten: ID = INT UNSIGNED, uang = BIGINT (rupiah utuh),
 * persentase = DECIMAL(6,3), flag = TINYINT(1).
 */
trait SchemaColumns
{
    protected function id(): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true];
    }

    protected function foreignId(bool $nullable = false): array
    {
        return ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => $nullable];
    }

    protected function money(int $default = 0): array
    {
        return ['type' => 'BIGINT', 'constraint' => 20, 'default' => $default];
    }

    protected function percent(?string $default = null): array
    {
        $column = ['type' => 'DECIMAL', 'constraint' => '6,3'];

        return $default === null ? $column + ['null' => true] : $column + ['default' => $default];
    }

    protected function flag(bool $default = false): array
    {
        return ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true, 'default' => (int) $default];
    }

    protected function string(int $length = 255, bool $nullable = false, ?string $default = null): array
    {
        $column = ['type' => 'VARCHAR', 'constraint' => $length, 'null' => $nullable];

        return $default === null ? $column : $column + ['default' => $default];
    }

    protected function json(): array
    {
        return ['type' => 'JSON', 'null' => true];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function timestamps(bool $softDeletes = false): array
    {
        $columns = [
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        if ($softDeletes) {
            $columns['deleted_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        return $columns;
    }
}
