<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Hari libur nasional & cuti bersama 2026.
 * Verifikasi dengan SKB 3 Menteri tahun berjalan sebelum dipakai di production.
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ([
            ['2026-01-01', 'Tahun Baru 2026 Masehi', 'national'],
            ['2026-01-16', 'Isra Mikraj Nabi Muhammad SAW', 'national'],
            ['2026-02-16', 'Cuti Bersama Tahun Baru Imlek', 'collective_leave'],
            ['2026-02-17', 'Tahun Baru Imlek 2577 Kongzili', 'national'],
            ['2026-03-18', 'Cuti Bersama Hari Suci Nyepi', 'collective_leave'],
            ['2026-03-19', 'Hari Suci Nyepi (Tahun Baru Saka 1948)', 'national'],
            ['2026-03-20', 'Cuti Bersama Idulfitri 1447 H', 'collective_leave'],
            ['2026-03-21', 'Idulfitri 1447 H', 'national'],
            ['2026-03-22', 'Idulfitri 1447 H', 'national'],
            ['2026-03-23', 'Cuti Bersama Idulfitri 1447 H', 'collective_leave'],
            ['2026-03-24', 'Cuti Bersama Idulfitri 1447 H', 'collective_leave'],
            ['2026-04-03', 'Wafat Yesus Kristus', 'national'],
            ['2026-04-05', 'Kebangkitan Yesus Kristus (Paskah)', 'national'],
            ['2026-05-01', 'Hari Buruh Internasional', 'national'],
            ['2026-05-14', 'Kenaikan Yesus Kristus', 'national'],
            ['2026-05-15', 'Cuti Bersama Kenaikan Yesus Kristus', 'collective_leave'],
            ['2026-05-27', 'Iduladha 1447 H', 'national'],
            ['2026-05-28', 'Cuti Bersama Iduladha 1447 H', 'collective_leave'],
            ['2026-05-31', 'Hari Raya Waisak 2570 BE', 'national'],
            ['2026-06-01', 'Hari Lahir Pancasila', 'national'],
            ['2026-06-16', 'Tahun Baru Islam 1448 H', 'national'],
            ['2026-08-17', 'Hari Proklamasi Kemerdekaan RI', 'national'],
            ['2026-08-25', 'Maulid Nabi Muhammad SAW', 'national'],
            ['2026-12-24', 'Cuti Bersama Hari Raya Natal', 'collective_leave'],
            ['2026-12-25', 'Hari Raya Natal', 'national'],
        ] as [$date, $name, $type]) {
            $rows[] = ['date' => $date, 'name' => $name, 'type' => $type, 'created_at' => $now, 'updated_at' => $now];
        }

        $this->db->table('holidays')->insertBatch($rows);
    }
}
