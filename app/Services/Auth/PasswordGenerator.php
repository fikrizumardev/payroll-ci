<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Password sementara untuk akun baru & reset password oleh Super Admin.
 * Format mudah dibacakan/diketik: tiga blok 4 karakter (mis. "Kp7m-Qx3r-Zt9w"),
 * tanpa karakter yang mirip (0/O, 1/l/I), selalu lolos kebijakan password.
 */
final class PasswordGenerator
{
    private const LOWER  = 'abcdefghjkmnpqrstuvwxyz';
    private const UPPER  = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const DIGITS = '23456789';

    public static function temporary(?string $username = null): string
    {
        $all = self::LOWER . self::UPPER . self::DIGITS;

        do {
            $chars = [self::pick(self::UPPER), self::pick(self::LOWER), self::pick(self::DIGITS), self::pick(self::DIGITS)];

            while (count($chars) < 12) {
                $chars[] = self::pick($all);
            }

            // Fisher–Yates dengan sumber acak kriptografis
            for ($i = count($chars) - 1; $i > 0; $i--) {
                $j = random_int(0, $i);
                [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
            }

            $password = implode('-', str_split(implode('', $chars), 4));
        } while (AuthService::passwordPolicyErrors($password, $username) !== []);

        return $password;
    }

    private static function pick(string $alphabet): string
    {
        return $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
}
