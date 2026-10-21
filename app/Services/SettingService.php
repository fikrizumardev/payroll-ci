<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SettingModel;
use InvalidArgumentException;

/**
 * Akses pengaturan aplikasi (tabel `settings`) dengan cache per request.
 *
 *   service('appSettings')->get('bpjs.kes.company_rate')   // 4.0
 *   service('appSettings')->group('company')               // ['company.name' => ..., ...]
 */
class SettingService
{
    /** @var array<string, array{value: mixed, type: string, group: string}>|null */
    private ?array $cache = null;

    public function __construct(private readonly SettingModel $model = new SettingModel())
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->load();

        return array_key_exists($key, $settings) ? $settings[$key]['value'] : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->load());
    }

    /**
     * @return array<string, mixed> key => value untuk satu grup
     */
    public function group(string $group): array
    {
        $values = [];

        foreach ($this->load() as $key => $setting) {
            if ($setting['group'] === $group) {
                $values[$key] = $setting['value'];
            }
        }

        return $values;
    }

    public function set(string $key, mixed $value): void
    {
        $settings = $this->load();

        if (! array_key_exists($key, $settings)) {
            throw new InvalidArgumentException("Pengaturan '{$key}' tidak dikenal.");
        }

        $this->model->update($key, ['value' => $this->serialize($value, $settings[$key]['type'])]);
        $this->flush();
    }

    public function flush(): void
    {
        $this->cache = null;
    }

    /**
     * @return array<string, array{value: mixed, type: string, group: string}>
     */
    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];

        foreach ($this->model->findAll() as $row) {
            $this->cache[$row['key']] = [
                'value' => $this->cast($row['value'], $row['type']),
                'type'  => $row['type'],
                'group' => $row['group'],
            ];
        }

        return $this->cache;
    }

    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int'     => (int) $value,
            'decimal' => (float) $value,
            'bool'    => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }

    private function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'bool'  => $value ? '1' : '0',
            'json'  => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }
}
