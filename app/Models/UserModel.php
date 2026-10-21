<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pengguna aplikasi. Login memakai username + password (tanpa email).
 */
class UserModel extends Model
{
    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_HR         = 'hr';
    public const ROLE_FINANCE    = 'finance';
    public const ROLE_EMPLOYEE   = 'employee';

    public const ROLES = [
        self::ROLE_SUPERADMIN => 'Super Admin',
        self::ROLE_HR         => 'HR',
        self::ROLE_FINANCE    => 'Finance',
        self::ROLE_EMPLOYEE   => 'Karyawan',
    ];

    /** Pola username: huruf kecil, angka, titik, underscore; 4–30 karakter. */
    public const USERNAME_PATTERN = '/^[a-z0-9._]{4,30}$/';

    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'username', 'password_hash', 'name', 'role', 'employee_id', 'is_active', 'must_change_password',
        'failed_attempts', 'locked_until', 'last_login_at', 'last_login_ip', 'password_changed_at',
    ];

    protected array $casts = [
        'employee_id'          => '?int',
        'is_active'            => 'bool',
        'must_change_password' => 'bool',
        'failed_attempts'      => 'int',
    ];

    protected $validationRules = [
        'id'          => 'permit_empty|is_natural_no_zero',
        'username'    => 'required|min_length[4]|max_length[30]|regex_match[/^[a-z0-9._]{4,30}$/]|is_unique[users.username,id,{id}]',
        'name'        => 'required|max_length[150]',
        'role'        => 'required|in_list[superadmin,hr,finance,employee]',
        'employee_id' => 'permit_empty|is_natural_no_zero|is_unique[users.employee_id,id,{id}]',
    ];

    protected $validationMessages = [
        'username' => [
            'is_unique'   => 'Username sudah dipakai.',
            'regex_match' => 'Username hanya boleh huruf kecil, angka, titik, dan underscore (4–30 karakter).',
        ],
        'employee_id' => ['is_unique' => 'Karyawan ini sudah memiliki akun.'],
    ];

    protected $beforeInsert = ['normalizeUsername'];
    protected $beforeUpdate = ['normalizeUsername'];

    public static function normalize(string $username): string
    {
        return strtolower(trim($username));
    }

    public function findByUsername(string $username): ?array
    {
        return $this->where('username', self::normalize($username))->first();
    }

    /**
     * @param array{data: array<string, mixed>} $event
     *
     * @return array{data: array<string, mixed>}
     */
    protected function normalizeUsername(array $event): array
    {
        if (isset($event['data']['username'])) {
            $event['data']['username'] = self::normalize((string) $event['data']['username']);
        }

        return $event;
    }
}
