<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Pinjaman karyawan (kasbon).
 *
 * - Dewi  : lunas Juli 2026 (dibayar sebelum sistem payroll dipakai).
 * - Kevin : berjalan; cicilan Juli 2026 dibayar di luar sistem.
 * - Rizky : disetujui, cicilan pertama dipotong di payroll Agustus 2026.
 * - Fajar : pengajuan baru, menunggu persetujuan Finance.
 */
class LoanSeeder extends Seeder
{
    public function run(): void
    {
        $now        = date('Y-m-d H:i:s');
        $employees  = array_column($this->db->table('employees')->select('id, nip')->get()->getResultArray(), 'id', 'nip');
        $approver   = $this->db->table('users')->select('id')->where('username', 'budi.santoso')->get()->getRowArray();
        $approverId = $approver['id'] ?? null;

        $loans = [
            // no, nip, jumlah, tenor, mulai potong, status, keperluan, disetujui, cicilan terbayar di luar sistem
            ['PJM-2026-0001', 'EMP-0027', 3_000_000, 3, '2026-05', 'paid_off', 'Biaya pendidikan adik', '2026-04-20 10:15:00', ['2026-05', '2026-06', '2026-07']],
            ['PJM-2026-0002', 'EMP-0011', 12_000_000, 12, '2026-07', 'active', 'Renovasi rumah', '2026-06-22 14:40:00', ['2026-07']],
            ['PJM-2026-0003', 'EMP-0013', 6_000_000, 6, '2026-08', 'approved', 'Biaya pernikahan', '2026-07-24 09:30:00', []],
            ['PJM-2026-0004', 'EMP-0009', 2_000_000, 4, '2026-11', 'pending', 'Biaya pengobatan orang tua', null, []],
        ];

        foreach ($loans as [$loanNo, $nip, $amount, $tenor, $startPeriod, $status, $purpose, $approvedAt, $paidPeriods]) {
            $installment = intdiv($amount, $tenor);

            $this->db->table('loans')->insert([
                'loan_no'      => $loanNo,
                'employee_id'  => $employees[$nip],
                'amount'       => $amount,
                'tenor_months' => $tenor,
                'installment'  => $installment,
                'start_period' => $startPeriod,
                'remaining'    => $amount - ($installment * count($paidPeriods)),
                'status'       => $status,
                'purpose'      => $purpose,
                'approved_by'  => $approvedAt === null ? null : $approverId,
                'approved_at'  => $approvedAt,
                'created_at'   => $approvedAt === null ? '2026-10-19 11:20:00' : date('Y-m-d H:i:s', strtotime($approvedAt . ' -2 days')),
                'updated_at'   => $now,
            ]);

            $loanId = $this->db->insertID();

            foreach ($paidPeriods as $index => $period) {
                $this->db->table('loan_installments')->insert([
                    'loan_id'        => $loanId,
                    'payroll_id'     => null,
                    'installment_no' => $index + 1,
                    'period'         => $period,
                    'amount'         => $installment,
                    'paid_at'        => date('Y-m-t 17:00:00', strtotime($period . '-01')),
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }
    }
}
