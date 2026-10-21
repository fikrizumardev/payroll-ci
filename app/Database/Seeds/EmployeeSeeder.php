<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * 31 karyawan PT Nusantara Digital Solusi (PRD Lampiran B.3).
 *
 * NIK, NPWP, alamat, no HP, dan no rekening dibangkitkan secara deterministik:
 * formatnya valid, tetapi nilainya fiktif.
 */
class EmployeeSeeder extends Seeder
{
    private const SEED = 20261021;

    /**
     * nip, nama, gender, jabatan, status kerja, tgl masuk, gaji pokok, PTKP, bank,
     * tempat lahir, tgl lahir, tgl resign
     */
    private const EMPLOYEES = [
        ['EMP-0001', 'Hendra Wijaya', 'L', 'DIR-01', 'permanent', '2015-03-02', 45_000_000, 'K/3', 'BCA', 'Surabaya', '1975-04-12', null],
        ['EMP-0002', 'Ratna Kusumawati', 'P', 'DIR-02', 'permanent', '2016-01-11', 38_000_000, 'K/2', 'Mandiri', 'Yogyakarta', '1979-09-23', null],
        ['EMP-0003', 'Sinta Maharani', 'P', 'HRD-01', 'permanent', '2018-07-02', 18_000_000, 'K/1', 'BCA', 'Bandung', '1986-02-14', null],
        ['EMP-0004', 'Dimas Saputra', 'L', 'HRD-02', 'permanent', '2022-02-14', 8_500_000, 'TK/0', 'BNI', 'Jakarta', '1996-06-30', null],
        ['EMP-0005', 'Putri Ayu Lestari', 'P', 'HRD-03', 'permanent', '2021-09-01', 8_000_000, 'TK/0', 'BCA', 'Semarang', '1995-11-08', null],
        ['EMP-0006', 'Anisa Rahmawati', 'P', 'HRD-04', 'probation', '2026-08-03', 7_000_000, 'TK/0', 'BNI', 'Bogor', '2000-03-17', null],
        ['EMP-0007', 'Budi Santoso', 'L', 'FIN-01', 'permanent', '2017-05-15', 20_000_000, 'K/2', 'Mandiri', 'Surakarta', '1982-07-19', null],
        ['EMP-0008', 'Yuliana Hartono', 'P', 'FIN-02', 'permanent', '2020-10-05', 9_500_000, 'K/0', 'BCA', 'Malang', '1991-12-02', null],
        ['EMP-0009', 'Fajar Nugroho', 'L', 'FIN-03', 'contract', '2023-03-20', 6_500_000, 'TK/0', 'BRI', 'Klaten', '1998-05-26', null],
        ['EMP-0010', 'Arief Rahman', 'L', 'ENG-01', 'permanent', '2017-08-01', 28_000_000, 'K/2', 'BCA', 'Padang', '1984-10-05', null],
        ['EMP-0011', 'Kevin Tanoto', 'L', 'ENG-02', 'permanent', '2019-04-08', 19_000_000, 'K/1', 'BCA', 'Medan', '1990-01-21', null],
        ['EMP-0012', 'Nadia Putri Anggraini', 'P', 'ENG-02', 'permanent', '2019-11-18', 19_500_000, 'TK/0', 'Mandiri', 'Jakarta', '1992-08-11', null],
        ['EMP-0013', 'Rizky Firmansyah', 'L', 'ENG-03', 'permanent', '2021-06-07', 12_500_000, 'TK/0', 'BNI', 'Depok', '1995-04-03', null],
        ['EMP-0014', 'Andi Pratama', 'L', 'ENG-03', 'permanent', '2022-01-10', 12_000_000, 'TK/0', 'BCA', 'Makassar', '1997-09-15', null],
        ['EMP-0015', 'Galih Prasetyo', 'L', 'ENG-03', 'permanent', '2023-02-01', 11_500_000, 'K/0', 'BRI', 'Purwokerto', '1996-02-28', null],
        ['EMP-0016', 'Taufik Hidayat', 'L', 'ENG-03', 'contract', '2025-07-01', 10_500_000, 'TK/0', 'BCA', 'Cirebon', '1999-07-07', null],
        ['EMP-0017', 'Mega Safitri', 'P', 'ENG-04', 'permanent', '2022-08-15', 10_000_000, 'TK/1', 'BCA', 'Palembang', '1994-03-09', null],
        ['EMP-0018', 'Yoga Aditya', 'L', 'ENG-04', 'contract', '2024-01-08', 9_800_000, 'TK/0', 'Mandiri', 'Denpasar', '1998-12-19', null],
        ['EMP-0019', 'Larasati Wulandari', 'P', 'ENG-05', 'permanent', '2021-03-15', 11_000_000, 'TK/0', 'BCA', 'Yogyakarta', '1996-10-27', null],
        ['EMP-0020', 'Bayu Setiawan', 'L', 'SLS-01', 'permanent', '2018-02-05', 17_000_000, 'K/2', 'BNI', 'Bekasi', '1985-05-14', null],
        ['EMP-0021', 'Citra Dewi', 'P', 'SLS-02', 'permanent', '2023-05-02', 7_500_000, 'TK/0', 'BCA', 'Tangerang', '1999-01-30', null],
        ['EMP-0022', 'Eko Wahyudi', 'L', 'SLS-02', 'permanent', '2022-11-14', 7_800_000, 'K/1', 'BRI', 'Kediri', '1993-08-22', null],
        ['EMP-0023', 'Intan Permatasari', 'P', 'SLS-02', 'contract', '2024-04-01', 7_500_000, 'TK/0', 'Mandiri', 'Bandung', '2000-06-12', null],
        ['EMP-0024', 'Reza Mahendra', 'L', 'SLS-03', 'permanent', '2023-09-11', 8_000_000, 'TK/0', 'BCA', 'Jakarta', '1998-04-18', null],
        ['EMP-0025', 'Siti Nurhaliza', 'P', 'SLS-03', 'permanent', '2022-07-04', 8_200_000, 'K/0', 'BNI', 'Serang', '1995-09-05', null],
        ['EMP-0026', 'Agus Salim', 'L', 'OPS-01', 'permanent', '2016-10-03', 9_000_000, 'K/3', 'BRI', 'Tegal', '1981-11-11', null],
        ['EMP-0027', 'Dewi Anjani', 'P', 'OPS-02', 'permanent', '2023-01-16', 5_800_000, 'TK/0', 'BCA', 'Jakarta', '1999-02-23', null],
        ['EMP-0028', 'Wulan Sari', 'P', 'OPS-02', 'contract', '2024-06-03', 5_800_000, 'K/1', 'Mandiri', 'Sukabumi', '1997-07-29', null],
        ['EMP-0029', 'Joko Susilo', 'L', 'OPS-03', 'permanent', '2019-03-04', 5_400_000, 'K/2', 'BRI', 'Wonogiri', '1983-03-16', null],
        ['EMP-0030', 'Slamet Riyadi', 'L', 'OPS-03', 'permanent', '2020-08-18', 5_400_000, 'K/1', 'BRI', 'Boyolali', '1988-12-08', null],
        ['EMP-0031', 'Hadi Gunawan', 'L', 'ENG-03', 'permanent', '2020-02-03', 13_000_000, 'K/1', 'BCA', 'Bandung', '1991-05-20', '2026-09-15'],
    ];

    /** Kepala departemen per kode departemen. */
    private const HEADS = [
        'DIR' => 'EMP-0001',
        'HRD' => 'EMP-0003',
        'FIN' => 'EMP-0007',
        'ENG' => 'EMP-0010',
        'SLS' => 'EMP-0020',
        'OPS' => 'EMP-0026',
    ];

    /** Domisili: kode wilayah NIK (6 digit), kecamatan, kota, kode pos, nama jalan. */
    private const DOMICILES = [
        ['317407', 'Kebayoran Baru', 'Jakarta Selatan', '12130', ['Jl. Wolter Monginsidi', 'Jl. Senopati', 'Jl. Gunawarman']],
        ['317401', 'Tebet', 'Jakarta Selatan', '12810', ['Jl. Tebet Barat Dalam', 'Jl. Tebet Utara', 'Jl. Prof. Dr. Soepomo']],
        ['317404', 'Pasar Minggu', 'Jakarta Selatan', '12520', ['Jl. Raya Ragunan', 'Jl. Kebagusan Raya', 'Jl. Siaga Raya']],
        ['317507', 'Duren Sawit', 'Jakarta Timur', '13440', ['Jl. Kolonel Sugiono', 'Jl. Pondok Kelapa Raya', 'Jl. Swadaya']],
        ['317502', 'Matraman', 'Jakarta Timur', '13140', ['Jl. Matraman Raya', 'Jl. Pramuka', 'Jl. Kayu Manis']],
        ['317305', 'Kebon Jeruk', 'Jakarta Barat', '11530', ['Jl. Panjang', 'Jl. Arjuna Utara', 'Jl. Raya Kebon Jeruk']],
        ['317106', 'Menteng', 'Jakarta Pusat', '10310', ['Jl. Cikini Raya', 'Jl. Teuku Umar', 'Jl. Probolinggo']],
        ['367405', 'Pondok Aren', 'Tangerang Selatan', '15224', ['Jl. Bintaro Utama', 'Jl. Pondok Aren Raya', 'Jl. Graha Raya']],
        ['367401', 'Serpong', 'Tangerang Selatan', '15310', ['Jl. BSD Raya Utama', 'Jl. Rawa Buntu', 'Jl. Letnan Sutopo']],
        ['327504', 'Bekasi Selatan', 'Bekasi', '17141', ['Jl. Ahmad Yani', 'Jl. KH. Noer Ali', 'Jl. Kemang Pratama Raya']],
        ['327606', 'Beji', 'Depok', '16421', ['Jl. Margonda Raya', 'Jl. Juanda', 'Jl. Kemiri Muka']],
    ];

    private const PHONE_PREFIXES = ['0811', '0812', '0813', '0821', '0822', '0857', '0878', '0896'];

    /** Panjang nomor rekening per bank. */
    private const ACCOUNT_LENGTH = ['BCA' => 10, 'Mandiri' => 13, 'BNI' => 10, 'BRI' => 15];

    private Randomizer $random;

    public function run(): void
    {
        $this->random = new Randomizer(new Mt19937(self::SEED));

        $now       = date('Y-m-d H:i:s');
        $positions = [];

        foreach ($this->db->table('positions')->get()->getResultArray() as $position) {
            $positions[$position['code']] = $position;
        }

        $employees = [];
        $usedNiks  = [];

        foreach (self::EMPLOYEES as [$nip, $name, $gender, $positionCode, $type, $joinDate, $salary, $ptkp, $bank, $birthPlace, $birthDate, $resignDate]) {
            $position   = $positions[$positionCode];
            $domicile   = self::DOMICILES[$this->random->getInt(0, count(self::DOMICILES) - 1)];
            $nik        = $this->nik($domicile[0], $birthDate, $gender, $usedNiks);
            $usedNiks[] = $nik;

            $employees[] = [
                'nip'               => $nip,
                'full_name'         => $name,
                'nik'               => $nik,
                'npwp'              => $nik, // Sejak 2024 NIK berfungsi sebagai NPWP orang pribadi
                'gender'            => $gender,
                'birth_place'       => $birthPlace,
                'birth_date'        => $birthDate,
                'address'           => $this->address($domicile),
                'phone'             => $this->phone(),
                'department_id'     => $position['department_id'],
                'position_id'       => $position['id'],
                'employment_type'   => $type,
                'status'            => $resignDate === null ? 'active' : 'resigned',
                'join_date'         => $joinDate,
                'resign_date'       => $resignDate,
                'ptkp_status'       => $ptkp,
                'basic_salary'      => $salary,
                'bank_name'         => $bank,
                'bank_account_no'   => $this->digits(self::ACCOUNT_LENGTH[$bank]),
                'bank_account_name' => strtoupper($name),
                'bpjs_kes_enrolled' => 1,
                'bpjs_tk_enrolled'  => 1,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        $this->db->table('employees')->insertBatch($employees);

        $this->seedEmployeeComponents($positions, $now);
        $this->assignDepartmentHeads();
    }

    /**
     * Tunjangan tetap per karyawan, diambil dari default jabatan.
     *
     * @param array<string, array<string, mixed>> $positions
     */
    private function seedEmployeeComponents(array $positions, string $now): void
    {
        $components  = array_column($this->db->table('salary_components')->get()->getResultArray(), 'id', 'code');
        $positionsId = array_column($positions, null, 'id');
        $rows        = [];

        foreach ($this->db->table('employees')->select('id, position_id')->get()->getResultArray() as $employee) {
            $position = $positionsId[$employee['position_id']];

            foreach (['POS_ALW' => 'position_allowance', 'COMM_ALW' => 'communication_allowance'] as $code => $field) {
                if ((int) $position[$field] > 0) {
                    $rows[] = [
                        'employee_id'         => $employee['id'],
                        'salary_component_id' => $components[$code],
                        'amount'              => $position[$field],
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ];
                }
            }
        }

        $this->db->table('employee_components')->insertBatch($rows);
    }

    private function assignDepartmentHeads(): void
    {
        $employeeIds = array_column($this->db->table('employees')->select('id, nip')->get()->getResultArray(), 'id', 'nip');

        foreach (self::HEADS as $departmentCode => $nip) {
            $this->db->table('departments')
                ->where('code', $departmentCode)
                ->update(['head_employee_id' => $employeeIds[$nip]]);
        }
    }

    /**
     * NIK: 6 digit wilayah + DDMMYY (tanggal +40 untuk perempuan) + 4 digit urut.
     *
     * @param list<string> $used
     */
    private function nik(string $region, string $birthDate, string $gender, array $used): string
    {
        [$year, $month, $day] = explode('-', $birthDate);
        $day                  = (int) $day + ($gender === 'P' ? 40 : 0);

        do {
            $nik = sprintf('%s%02d%s%s%04d', $region, $day, $month, substr($year, 2), $this->random->getInt(1, 2999));
        } while (in_array($nik, $used, true));

        return $nik;
    }

    /**
     * @param array{string, string, string, string, list<string>} $domicile
     */
    private function address(array $domicile): string
    {
        [, $district, $city, $postalCode, $streets] = $domicile;

        return sprintf(
            '%s No. %d, RT %03d/RW %03d, %s, %s %s',
            $streets[$this->random->getInt(0, count($streets) - 1)],
            $this->random->getInt(1, 120),
            $this->random->getInt(1, 12),
            $this->random->getInt(1, 9),
            $district,
            $city,
            $postalCode,
        );
    }

    private function phone(): string
    {
        return self::PHONE_PREFIXES[$this->random->getInt(0, count(self::PHONE_PREFIXES) - 1)] . $this->digits(8, false);
    }

    private function digits(int $length, bool $nonZeroFirst = true): string
    {
        $value = $nonZeroFirst ? (string) $this->random->getInt(1, 9) : (string) $this->random->getInt(0, 9);

        while (strlen($value) < $length) {
            $value .= $this->random->getInt(0, 9);
        }

        return $value;
    }
}
