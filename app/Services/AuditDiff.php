<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Menyusun perbandingan data sebelum/sesudah dari audit log menjadi baris tabel.
 * Data bersarang diratakan dengan notasi titik (mis. `bank.account_no`).
 */
final class AuditDiff
{
    public const CHANGED = 'changed';
    public const ADDED   = 'added';
    public const REMOVED = 'removed';
    public const SAME    = 'same';

    private const MAX_DEPTH = 3;

    /**
     * @param array<array-key, mixed>|null $before
     * @param array<array-key, mixed>|null $after
     *
     * @return list<array{key: string, before: string|null, after: string|null, change: string}>
     */
    public static function rows(?array $before, ?array $after): array
    {
        $old  = $before === null ? [] : self::flatten($before);
        $new  = $after === null ? [] : self::flatten($after);
        $rows = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $key       = (string) $key;
            $hasBefore = array_key_exists($key, $old);
            $hasAfter  = array_key_exists($key, $new);

            $change = match (true) {
                $hasBefore && ! $hasAfter => self::REMOVED,
                ! $hasBefore && $hasAfter => self::ADDED,
                $old[$key] === $new[$key] => self::SAME,
                default                   => self::CHANGED,
            };

            $rows[] = [
                'key'    => $key,
                'before' => $hasBefore ? $old[$key] : null,
                'after'  => $hasAfter ? $new[$key] : null,
                'change' => $change,
            ];
        }

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, string>
     */
    private static function flatten(array $data, string $prefix = '', int $depth = 1): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value) && $value !== [] && ! array_is_list($value) && $depth < self::MAX_DEPTH) {
                $flat += self::flatten($value, $name, $depth + 1);

                continue;
            }

            $flat[$name] = self::stringify($value);
        }

        return $flat;
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null  => 'null',
            is_bool($value)  => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default          => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    }
}
