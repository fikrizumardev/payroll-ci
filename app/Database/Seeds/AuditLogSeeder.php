<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Jejak aktivitas contoh agar halaman Audit Log & "Aktivitas Terbaru" di dashboard
 * tidak kosong. Aktivitas payroll (generate/approve) ditambahkan oleh
 * PayrollHistorySeeder setelah engine payroll tersedia.
 */
class AuditLogSeeder extends Seeder
{
    private const USER_AGENTS = [
        'chrome-win' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        'safari-mac' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
        'chrome-and' => 'Mozilla/5.0 (Linux; Android 14; SM-A556E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Mobile Safari/537.36',
    ];

    public function run(): void
    {
        $users         = array_column($this->db->table('users')->select('id, username')->get()->getResultArray(), 'id', 'username');
        $employees     = array_column($this->db->table('employees')->select('id, nip')->get()->getResultArray(), 'id', 'nip');
        $loans         = array_column($this->db->table('loans')->select('id, loan_no')->get()->getResultArray(), 'id', 'loan_no');
        $septemberRows = $this->db->table('attendances')->where('date >=', '2026-09-01')->where('date <=', '2026-09-30')->countAllResults();

        $events = [
            // waktu, username, aksi, entitas, id entitas, deskripsi, before, after, IP, UA
            ['2026-06-22 14:40:00', 'budi.santoso', 'loan.approve', 'loan', $loans['PJM-2026-0002'], 'Menyetujui pinjaman PJM-2026-0002 (Kevin Tanoto)', ['status' => 'pending'], ['status' => 'approved'], '10.10.1.27', 'safari-mac'],
            ['2026-07-24 09:30:00', 'budi.santoso', 'loan.approve', 'loan', $loans['PJM-2026-0003'], 'Menyetujui pinjaman PJM-2026-0003 (Rizky Firmansyah)', ['status' => 'pending'], ['status' => 'approved'], '10.10.1.27', 'safari-mac'],
            ['2026-07-28 10:05:00', 'sinta.maharani', 'employee.create', 'employee', $employees['EMP-0006'], 'Menambahkan karyawan baru Anisa Rahmawati (EMP-0006)', null, ['nip' => 'EMP-0006', 'join_date' => '2026-08-10'], '10.10.1.14', 'chrome-win'],
            ['2026-07-28 10:07:00', 'admin', 'user.create', 'user', $users['anisa.rahmawati'], 'Membuat akun login anisa.rahmawati', null, ['username' => 'anisa.rahmawati', 'role' => 'employee'], '10.10.1.2', 'chrome-win'],
            ['2026-09-01 08:12:00', 'admin', 'setting.update', 'setting', 'bpjs.jp.max_wage', 'Memperbarui batas upah Jaminan Pensiun', ['value' => '10042300'], ['value' => '10547400'], '10.10.1.2', 'chrome-win'],
            ['2026-09-15 16:45:00', 'sinta.maharani', 'employee.resign', 'employee', $employees['EMP-0031'], 'Mencatat resign Hadi Gunawan per 15 Sep 2026', ['status' => 'active'], ['status' => 'resigned', 'resign_date' => '2026-09-15'], '10.10.1.14', 'chrome-win'],
            ['2026-09-15 16:47:00', 'admin', 'user.deactivate', 'user', $users['hadi.gunawan'], 'Menonaktifkan akun hadi.gunawan', ['is_active' => 1], ['is_active' => 0], '10.10.1.2', 'chrome-win'],
            ['2026-10-01 08:03:00', 'putri.lestari', 'auth.login', 'user', $users['putri.lestari'], 'Login berhasil', null, null, '10.10.1.15', 'chrome-win'],
            ['2026-10-01 09:20:00', 'putri.lestari', 'attendance.import', 'attendance', null, 'Import kehadiran September 2026 (31 karyawan)', null, ['rows' => $septemberRows], '10.10.1.15', 'chrome-win'],
            ['2026-10-05 13:22:00', 'admin', 'user.reset_password', 'user', $users['yoga.aditya'], 'Reset password yoga.aditya atas permintaan karyawan', null, null, '10.10.1.2', 'chrome-win'],
            ['2026-10-19 11:20:00', 'fajar.nugroho', 'loan.create', 'loan', $loans['PJM-2026-0004'], 'Mengajukan pinjaman PJM-2026-0004 sebesar Rp 2.000.000', null, ['amount' => 2000000, 'tenor_months' => 4], '182.253.14.77', 'chrome-and'],
            ['2026-10-20 07:58:00', 'sinta.maharani', 'auth.login', 'user', $users['sinta.maharani'], 'Login berhasil', null, null, '10.10.1.14', 'chrome-win'],
            ['2026-10-20 08:31:00', 'andi.pratama', 'auth.login_failed', 'user', $users['andi.pratama'], 'Login gagal: password salah', null, null, '182.253.10.21', 'chrome-and'],
            ['2026-10-20 08:32:00', 'andi.pratama', 'auth.login', 'user', $users['andi.pratama'], 'Login berhasil', null, null, '182.253.10.21', 'chrome-and'],
            ['2026-10-20 09:10:00', 'budi.santoso', 'auth.login', 'user', $users['budi.santoso'], 'Login berhasil', null, null, '10.10.1.27', 'safari-mac'],
            ['2026-10-20 10:42:00', 'sinta.maharani', 'employee.update', 'employee', $employees['EMP-0017'], 'Memperbarui status PTKP Mega Safitri', ['ptkp_status' => 'TK/0'], ['ptkp_status' => 'TK/1'], '10.10.1.14', 'chrome-win'],
            ['2026-10-20 17:05:00', 'sinta.maharani', 'auth.logout', 'user', $users['sinta.maharani'], 'Logout', null, null, '10.10.1.14', 'chrome-win'],
            ['2026-10-21 07:55:00', 'admin', 'auth.login', 'user', $users['admin'], 'Login berhasil', null, null, '10.10.1.2', 'chrome-win'],
        ];

        $rows = [];

        foreach ($events as [$time, $username, $action, $entity, $entityId, $description, $before, $after, $ip, $agent]) {
            $rows[] = [
                'user_id'     => $users[$username] ?? null,
                'username'    => $username,
                'action'      => $action,
                'entity'      => $entity,
                'entity_id'   => $entityId === null ? null : (string) $entityId,
                'description' => $description,
                'before'      => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
                'after'       => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
                'ip_address'  => $ip,
                'user_agent'  => self::USER_AGENTS[$agent],
                'created_at'  => $time,
            ];
        }

        $this->db->table('audit_logs')->insertBatch($rows);
    }
}
