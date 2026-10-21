<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Komponen gaji standar. Komponen `is_system` dipakai langsung oleh engine payroll
 * dan tidak boleh dihapus dari UI.
 */
class SalaryComponentSeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ([
            // kode, nama, tipe, cara hitung, default, kena pajak, dasar BPJS, deskripsi
            ['BASIC', 'Gaji Pokok', 'earning', 'formula', 0, true, true, 'Prorata bila masuk/keluar di tengah periode'],
            ['POS_ALW', 'Tunjangan Jabatan', 'earning', 'fixed', 0, true, true, 'Default dari jabatan, bisa di-override per karyawan'],
            ['COMM_ALW', 'Tunjangan Komunikasi', 'earning', 'fixed', 0, true, true, 'Default dari jabatan, bisa di-override per karyawan'],
            ['TRANSPORT', 'Tunjangan Transport', 'earning', 'daily', 30_000, true, false, 'Per hari hadir (hadir + terlambat)'],
            ['MEAL', 'Uang Makan', 'earning', 'daily', 35_000, true, false, 'Per hari hadir (hadir + terlambat)'],
            ['OVERTIME', 'Lembur', 'earning', 'formula', 0, true, false, '1/173 × upah; jam ke-1 1,5×, berikutnya 2×'],
            ['ADJ', 'Bonus / Penyesuaian', 'earning', 'manual', 0, true, false, 'Bonus, THR, atau koreksi manual'],
            ['PPH21_REFUND', 'Pengembalian Kelebihan PPh 21', 'earning', 'formula', 0, false, false, 'Lebih potong PPh 21 di masa pajak terakhir'],

            ['BPJS_KES_EE', 'BPJS Kesehatan (Karyawan)', 'deduction', 'formula', 0, false, false, '1% dari dasar upah, maks. batas upah'],
            ['JHT_EE', 'BPJS JHT (Karyawan)', 'deduction', 'formula', 0, false, false, '2% dari dasar upah'],
            ['JP_EE', 'BPJS Jaminan Pensiun (Karyawan)', 'deduction', 'formula', 0, false, false, '1% dari dasar upah, maks. batas upah'],
            ['PPH21', 'PPh 21', 'deduction', 'formula', 0, false, false, 'Metode TER (Jan–Nov), tahunan di Desember'],
            ['ABSENT_DED', 'Potongan Alpha', 'deduction', 'formula', 0, true, false, 'Gaji pokok ÷ hari kerja × hari alpha; mengurangi bruto PPh 21'],
            ['LATE_PEN', 'Denda Keterlambatan', 'deduction', 'formula', 0, true, false, 'Tarif per hari terlambat (Pengaturan); mengurangi bruto PPh 21'],
            ['LOAN', 'Cicilan Pinjaman', 'deduction', 'formula', 0, false, false, 'Cicilan pinjaman aktif periode berjalan'],
            ['ADJ_DED', 'Potongan Lain-lain', 'deduction', 'manual', 0, false, false, 'Koreksi potongan manual'],

            ['BPJS_KES_ER', 'BPJS Kesehatan (Perusahaan)', 'company_contribution', 'formula', 0, true, false, '4% — menambah bruto PPh 21'],
            ['JKK_ER', 'BPJS JKK (Perusahaan)', 'company_contribution', 'formula', 0, true, false, '0,24% — menambah bruto PPh 21'],
            ['JKM_ER', 'BPJS JKM (Perusahaan)', 'company_contribution', 'formula', 0, true, false, '0,3% — menambah bruto PPh 21'],
            ['JHT_ER', 'BPJS JHT (Perusahaan)', 'company_contribution', 'formula', 0, false, false, '3,7% — bukan objek PPh 21'],
            ['JP_ER', 'BPJS Jaminan Pensiun (Perusahaan)', 'company_contribution', 'formula', 0, false, false, '2% — bukan objek PPh 21'],
        ] as $index => [$code, $name, $type, $calc, $default, $taxable, $bpjsBase, $description]) {
            $rows[] = [
                'code'           => $code,
                'name'           => $name,
                'type'           => $type,
                'calc_type'      => $calc,
                'default_amount' => $default,
                'taxable'        => (int) $taxable,
                'is_bpjs_base'   => (int) $bpjsBase,
                'is_system'      => 1,
                'is_active'      => 1,
                'sort_order'     => ($index + 1) * 10,
                'description'    => $description,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        $this->db->table('salary_components')->insertBatch($rows);
    }
}
