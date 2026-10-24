<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    /** Modul (prefix aksi) → label dan tone badge. */
    public const MODULES = [
        'auth'             => ['Autentikasi', 'sky'],
        'user'             => ['Pengguna', 'violet'],
        'employee'         => ['Karyawan', 'indigo'],
        'department'       => ['Departemen', 'indigo'],
        'position'         => ['Jabatan', 'indigo'],
        'salary_component' => ['Komponen Gaji', 'indigo'],
        'holiday'          => ['Hari Libur', 'indigo'],
        'attendance'       => ['Kehadiran', 'emerald'],
        'overtime'         => ['Lembur', 'emerald'],
        'loan'             => ['Pinjaman', 'amber'],
        'payroll'          => ['Payroll', 'indigo'],
        'payslip'          => ['Slip Gaji', 'slate'],
        'report'           => ['Laporan', 'slate'],
        'setting'          => ['Pengaturan', 'violet'],
    ];

    /** Label setiap aksi yang dicatat aplikasi. */
    public const ACTIONS = [
        'auth.login'                => 'Login',
        'auth.login_remembered'     => 'Login otomatis (Ingat saya)',
        'auth.login_failed'         => 'Login gagal',
        'auth.login_locked'         => 'Login ditolak — akun terkunci',
        'auth.login_inactive'       => 'Login ditolak — akun nonaktif',
        'auth.account_locked'       => 'Akun dikunci otomatis',
        'auth.logout'               => 'Logout',
        'auth.password_changed'     => 'Ganti password',
        'user.create'               => 'Tambah pengguna',
        'user.update'               => 'Ubah pengguna',
        'user.reset_password'       => 'Reset password',
        'user.activate'             => 'Aktifkan pengguna',
        'user.deactivate'           => 'Nonaktifkan pengguna',
        'user.unlock'               => 'Buka kunci akun',
        'employee.create'           => 'Tambah karyawan',
        'employee.update'           => 'Ubah karyawan',
        'employee.resign'           => 'Resign / berhenti',
        'employee.components'       => 'Ubah komponen gaji karyawan',
        'employee.export'           => 'Ekspor data karyawan',
        'department.create'         => 'Tambah departemen',
        'department.update'         => 'Ubah departemen',
        'department.delete'         => 'Hapus departemen',
        'position.create'           => 'Tambah jabatan',
        'position.update'           => 'Ubah jabatan',
        'position.delete'           => 'Hapus jabatan',
        'salary_component.create'   => 'Tambah komponen gaji',
        'salary_component.update'   => 'Ubah komponen gaji',
        'salary_component.delete'   => 'Hapus komponen gaji',
        'holiday.create'            => 'Tambah hari libur',
        'holiday.update'            => 'Ubah hari libur',
        'holiday.delete'            => 'Hapus hari libur',
        'attendance.save'           => 'Simpan kehadiran harian',
        'attendance.import'         => 'Import kehadiran',
        'overtime.create'           => 'Ajukan lembur',
        'overtime.update'           => 'Ubah lembur',
        'overtime.delete'           => 'Hapus lembur',
        'overtime.approve'          => 'Setujui lembur',
        'overtime.reject'           => 'Tolak lembur',
        'loan.create'               => 'Ajukan pinjaman',
        'loan.update'               => 'Ubah pinjaman',
        'loan.delete'               => 'Hapus pinjaman',
        'loan.approve'              => 'Setujui pinjaman',
        'loan.reject'               => 'Tolak pinjaman',
        'payroll.create'            => 'Buat periode payroll',
        'payroll.generate'          => 'Generate payroll',
        'payroll.submit'            => 'Ajukan review payroll',
        'payroll.approve'           => 'Setujui payroll',
        'payroll.reject'            => 'Tolak payroll',
        'payroll.pay'               => 'Tandai payroll dibayar',
        'payroll.lock'              => 'Kunci periode payroll',
        'payroll.adjustment.create' => 'Tambah penyesuaian',
        'payroll.adjustment.delete' => 'Hapus penyesuaian',
        'payroll.export_bank'       => 'Ekspor transfer bank',
        'payslip.download'          => 'Unduh slip gaji',
        'payslip.download_zip'      => 'Unduh slip massal (ZIP)',
        'report.export'             => 'Ekspor laporan',
        'setting.update'            => 'Ubah pengaturan',
    ];

    /** Aksi yang menandakan kegagalan, penolakan, atau penghapusan (badge merah). */
    private const ALERT_PATTERN = '/(delete|failed|locked|inactive|deactivate|reject|resign)/';

    protected $table         = 'audit_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = [
        'user_id', 'username', 'action', 'entity', 'entity_id', 'description',
        'before', 'after', 'ip_address', 'user_agent', 'created_at',
    ];

    protected array $casts = [
        'id'      => 'int',
        'user_id' => '?int',
        'before'  => '?json-array',
        'after'   => '?json-array',
    ];

    public static function module(string $action): string
    {
        return explode('.', $action, 2)[0];
    }

    public static function label(string $action): string
    {
        return self::ACTIONS[$action] ?? (self::MODULES[self::module($action)][0] ?? ucfirst(self::module($action))) . ' · ' . (explode('.', $action, 2)[1] ?? '');
    }

    public static function tone(string $action): string
    {
        return preg_match(self::ALERT_PATTERN, $action) === 1 ? 'rose' : (self::MODULES[self::module($action)][1] ?? 'slate');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        return $this->select('audit_logs.*, users.name AS user_name')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->orderBy('audit_logs.created_at', 'DESC')
            ->orderBy('audit_logs.id', 'DESC')
            ->findAll($limit);
    }
}
