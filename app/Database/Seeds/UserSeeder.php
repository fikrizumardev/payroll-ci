<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Akun login (username + password). Satu Super Admin dan satu akun per karyawan.
 * Username karyawan = nama depan + nama belakang, huruf kecil, dipisah titik.
 */
class UserSeeder extends Seeder
{
    public const ADMIN_PASSWORD   = 'Admin#2026';
    public const DEFAULT_PASSWORD = 'Payroll#2026';

    /** Peran selain `employee`, berdasarkan NIP. */
    private const ROLES = [
        'EMP-0003' => 'hr',      // Sinta Maharani — HR Manager
        'EMP-0005' => 'hr',      // Putri Ayu Lestari — Payroll Officer
        'EMP-0007' => 'finance', // Budi Santoso — Finance Manager
    ];

    public function run(): void
    {
        $now                = date('Y-m-d H:i:s');
        $mustChangePassword = ENVIRONMENT === 'production' ? 1 : 0;
        $defaultHash        = static fn (): string => password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT);

        $rows = [[
            'username'             => 'admin',
            'password_hash'        => password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'name'                 => 'Administrator Sistem',
            'role'                 => 'superadmin',
            'employee_id'          => null,
            'is_active'            => 1,
            'must_change_password' => $mustChangePassword,
            'password_changed_at'  => $now,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]];

        $employees = $this->db->table('employees')->orderBy('nip')->get()->getResultArray();

        foreach ($employees as $employee) {
            $rows[] = [
                'username'             => self::usernameFor($employee['full_name']),
                'password_hash'        => $defaultHash(),
                'name'                 => $employee['full_name'],
                'role'                 => self::ROLES[$employee['nip']] ?? 'employee',
                'employee_id'          => $employee['id'],
                'is_active'            => $employee['status'] === 'active' ? 1 : 0,
                'must_change_password' => $mustChangePassword,
                'password_changed_at'  => $now,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        $this->db->table('users')->insertBatch($rows);
    }

    /**
     * "Andi Pratama" → "andi.pratama", "Nadia Putri Anggraini" → "nadia.anggraini"
     */
    public static function usernameFor(string $fullName): string
    {
        $parts = preg_split('/\s+/', strtolower(trim($fullName))) ?: [];
        $parts = array_map(static fn (string $part): string => preg_replace('/[^a-z0-9]/', '', $part), $parts);

        return count($parts) > 1 ? $parts[0] . '.' . end($parts) : $parts[0];
    }
}
