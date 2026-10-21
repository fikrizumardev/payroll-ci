<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Pengaturan default: profil perusahaan, tarif BPJS, pajak, dan aturan kehadiran.
 * Tarif harian transport & uang makan disimpan di `salary_components.default_amount`
 * (lihat SalaryComponentSeeder) agar hanya ada satu sumber nilai.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $sort = 0;
        $rows = [];

        foreach ($this->settings() as $group => $items) {
            foreach ($items as $key => [$label, $type, $value, $description]) {
                $rows[] = [
                    'key'         => $key,
                    'value'       => $value,
                    'group'       => $group,
                    'type'        => $type,
                    'label'       => $label,
                    'description' => $description,
                    'sort_order'  => $sort += 10,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        $this->db->table('settings')->insertBatch($rows);
    }

    /**
     * @return array<string, array<string, array{string, string, string|null, string|null}>>
     */
    private function settings(): array
    {
        return [
            'company' => [
                'company.name'    => ['Nama Perusahaan', 'string', 'PT Nusantara Digital Solusi', null],
                'company.address' => ['Alamat', 'text', 'Gedung Graha Niaga Lt. 12, Jl. Jend. Sudirman Kav. 58, Jakarta Selatan 12190', null],
                'company.city'    => ['Kota', 'string', 'Jakarta Selatan', 'Dipakai di tanda tangan slip gaji'],
                'company.phone'   => ['Telepon', 'string', '(021) 5290 8800', null],
                'company.website' => ['Website', 'string', 'www.nusantaradigital.co.id', null],
                'company.npwp'    => ['NPWP Perusahaan', 'string', '0012345678901000', 'Fiktif, untuk data contoh'],
                'company.logo'    => ['Logo', 'string', null, 'Path relatif dari folder public'],
            ],
            'bpjs' => [
                'bpjs.kes.company_rate'  => ['BPJS Kesehatan — porsi perusahaan (%)', 'decimal', '4.000', null],
                'bpjs.kes.employee_rate' => ['BPJS Kesehatan — porsi karyawan (%)', 'decimal', '1.000', null],
                'bpjs.kes.max_wage'      => ['BPJS Kesehatan — batas atas upah', 'int', '12000000', null],
                'bpjs.jht.company_rate'  => ['JHT — porsi perusahaan (%)', 'decimal', '3.700', null],
                'bpjs.jht.employee_rate' => ['JHT — porsi karyawan (%)', 'decimal', '2.000', null],
                'bpjs.jkk.company_rate'  => ['JKK — porsi perusahaan (%)', 'decimal', '0.240', 'Kelompok risiko sangat rendah'],
                'bpjs.jkm.company_rate'  => ['JKM — porsi perusahaan (%)', 'decimal', '0.300', null],
                'bpjs.jp.company_rate'   => ['JP — porsi perusahaan (%)', 'decimal', '2.000', null],
                'bpjs.jp.employee_rate'  => ['JP — porsi karyawan (%)', 'decimal', '1.000', null],
                'bpjs.jp.max_wage'       => ['JP — batas atas upah', 'int', '10547400', 'Nilai 2025; perbarui sesuai ketetapan BPJS Ketenagakerjaan terbaru'],
            ],
            'tax' => [
                'tax.method'                 => ['Metode PPh 21', 'string', 'gross', 'gross = pajak ditanggung karyawan'],
                'tax.position_cost_rate'     => ['Biaya jabatan (%)', 'decimal', '5.000', 'Dipakai pada perhitungan tahunan (Desember)'],
                'tax.position_cost_max_year' => ['Biaya jabatan — maksimum setahun', 'int', '6000000', null],
            ],
            'payroll' => [
                'payroll.minimum_wage'      => ['UMP / UMK acuan', 'int', '5396761', 'UMP DKI Jakarta 2025; perbarui setiap tahun'],
                'payroll.overtime_divisor'  => ['Pembagi upah sejam lembur', 'int', '173', 'Upah sejam = 1/173 × upah sebulan (PP 35/2021)'],
                'payroll.pay_day'           => ['Tanggal gajian default', 'int', '25', 'Jika jatuh di hari libur, maju ke hari kerja sebelumnya'],
            ],
            'attendance' => [
                'attendance.work_start'             => ['Jam masuk', 'time', '08:00', null],
                'attendance.work_end'               => ['Jam pulang', 'time', '17:00', null],
                'attendance.late_tolerance_minutes' => ['Toleransi keterlambatan (menit)', 'int', '15', null],
                'attendance.late_penalty'           => ['Denda per hari terlambat', 'int', '25000', null],
            ],
        ];
    }
}
