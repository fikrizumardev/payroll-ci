<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Departemen. Kepala departemen diisi oleh EmployeeSeeder setelah karyawan dibuat.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ([
            ['DIR', 'Direksi', 'Pimpinan perusahaan dan pengambil keputusan strategis'],
            ['HRD', 'Human Resources', 'Rekrutmen, pengembangan SDM, dan administrasi payroll'],
            ['FIN', 'Finance & Accounting', 'Keuangan, akuntansi, pajak, dan pembayaran'],
            ['ENG', 'Engineering', 'Pengembangan produk, QA, dan desain'],
            ['SLS', 'Sales & Marketing', 'Penjualan, akun klien, dan pemasaran digital'],
            ['OPS', 'Operations & General Affair', 'Operasional kantor, logistik, dan dukungan umum'],
        ] as [$code, $name, $description]) {
            $rows[] = ['code' => $code, 'name' => $name, 'description' => $description, 'created_at' => $now, 'updated_at' => $now];
        }

        $this->db->table('departments')->insertBatch($rows);
    }
}
