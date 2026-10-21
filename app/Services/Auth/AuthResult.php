<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Hasil percobaan login.
 */
final class AuthResult
{
    public const OK       = 'ok';
    public const INVALID  = 'invalid';
    public const LOCKED   = 'locked';
    public const INACTIVE = 'inactive';

    /**
     * @param array<string, mixed>|null $user
     */
    private function __construct(
        public readonly string $status,
        public readonly ?array $user = null,
        public readonly ?int $lockedMinutes = null,
    ) {
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function success(array $user): self
    {
        return new self(self::OK, $user);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function locked(int $minutes): self
    {
        return new self(self::LOCKED, null, max(1, $minutes));
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public function ok(): bool
    {
        return $this->status === self::OK;
    }

    /**
     * Pesan untuk pengguna. Kredensial salah selalu memakai pesan generik
     * agar tidak membocorkan username mana yang terdaftar.
     */
    public function message(): string
    {
        return match ($this->status) {
            self::OK       => 'Login berhasil.',
            self::LOCKED   => "Akun dikunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {$this->lockedMinutes} menit.",
            self::INACTIVE => 'Akun Anda nonaktif. Silakan hubungi administrator.',
            default        => 'Username atau password salah.',
        };
    }
}
