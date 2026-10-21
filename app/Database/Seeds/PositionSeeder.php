<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Jabatan beserta gaji pokok standar dan tunjangan tetap (PRD Lampiran B.2).
 */
class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $now         = date('Y-m-d H:i:s');
        $departments = array_column($this->db->table('departments')->get()->getResultArray(), 'id', 'code');
        $rows        = [];

        foreach ([
            // kode, jabatan, dept, grade, gaji pokok, tunj. jabatan, tunj. komunikasi, lembur
            ['DIR-01', 'Direktur Utama', 'DIR', 10, 45_000_000, 10_000_000, 1_000_000, false],
            ['DIR-02', 'Direktur Operasional', 'DIR', 9, 38_000_000, 8_000_000, 1_000_000, false],
            ['HRD-01', 'HR Manager', 'HRD', 7, 18_000_000, 4_000_000, 500_000, false],
            ['HRD-02', 'HR Generalist', 'HRD', 4, 8_500_000, 1_000_000, 200_000, true],
            ['HRD-03', 'Payroll Officer', 'HRD', 4, 8_000_000, 1_000_000, 200_000, true],
            ['HRD-04', 'Talent Acquisition', 'HRD', 3, 7_000_000, 750_000, 200_000, true],
            ['FIN-01', 'Finance Manager', 'FIN', 7, 20_000_000, 4_000_000, 500_000, false],
            ['FIN-02', 'Accountant', 'FIN', 5, 9_500_000, 1_250_000, 200_000, true],
            ['FIN-03', 'Finance Staff', 'FIN', 3, 6_500_000, 500_000, 150_000, true],
            ['ENG-01', 'Engineering Manager', 'ENG', 8, 28_000_000, 6_000_000, 750_000, false],
            ['ENG-02', 'Senior Software Engineer', 'ENG', 6, 19_000_000, 2_500_000, 300_000, true],
            ['ENG-03', 'Software Engineer', 'ENG', 5, 12_000_000, 1_500_000, 300_000, true],
            ['ENG-04', 'QA Engineer', 'ENG', 4, 10_000_000, 1_250_000, 200_000, true],
            ['ENG-05', 'UI/UX Designer', 'ENG', 5, 11_000_000, 1_250_000, 200_000, true],
            ['SLS-01', 'Sales & Marketing Manager', 'SLS', 7, 17_000_000, 3_500_000, 500_000, false],
            ['SLS-02', 'Account Executive', 'SLS', 3, 7_500_000, 750_000, 300_000, true],
            ['SLS-03', 'Digital Marketing Specialist', 'SLS', 3, 8_000_000, 750_000, 200_000, true],
            ['OPS-01', 'Operations Supervisor', 'OPS', 5, 9_000_000, 1_500_000, 200_000, true],
            ['OPS-02', 'Office Administrator', 'OPS', 2, 5_800_000, 400_000, 100_000, true],
            ['OPS-03', 'Driver / Office Support', 'OPS', 1, 5_400_000, 0, 0, true],
        ] as [$code, $name, $dept, $grade, $salary, $positionAllowance, $commAllowance, $overtime]) {
            $rows[] = [
                'department_id'           => $departments[$dept],
                'code'                    => $code,
                'name'                    => $name,
                'grade'                   => $grade,
                'base_salary'             => $salary,
                'position_allowance'      => $positionAllowance,
                'communication_allowance' => $commAllowance,
                'overtime_eligible'       => (int) $overtime,
                'created_at'              => $now,
                'updated_at'              => $now,
            ];
        }

        $this->db->table('positions')->insertBatch($rows);
    }
}
