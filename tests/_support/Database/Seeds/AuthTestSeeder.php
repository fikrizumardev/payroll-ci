<?php

declare(strict_types=1);

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Data minimal untuk test autentikasi (tanpa karyawan).
 */
class AuthTestSeeder extends Seeder
{
    public const PASSWORD = 'Rahasia#2026';

    public function run(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach (['audit_logs', 'user_tokens', 'users'] as $table) {
            $this->db->table($table)->truncate();
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ([
            ['admin', 'Administrator', 'superadmin', 1, 0],
            ['hr.user', 'Pengguna HR', 'hr', 1, 0],
            ['finance.user', 'Pengguna Finance', 'finance', 1, 0],
            ['karyawan.satu', 'Karyawan Satu', 'employee', 1, 0],
            ['karyawan.nonaktif', 'Karyawan Nonaktif', 'employee', 0, 0],
            ['karyawan.baru', 'Karyawan Baru', 'employee', 1, 1],
        ] as [$username, $name, $role, $active, $mustChange]) {
            $rows[] = [
                'username'             => $username,
                'password_hash'        => $hash,
                'name'                 => $name,
                'role'                 => $role,
                'is_active'            => $active,
                'must_change_password' => $mustChange,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        $this->db->table('users')->insertBatch($rows);
    }
}
