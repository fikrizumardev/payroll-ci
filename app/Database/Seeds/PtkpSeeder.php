<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Penghasilan Tidak Kena Pajak (PTKP) per tahun dan kategori TER-nya.
 */
class PtkpSeeder extends Seeder
{
    public function run(): void
    {
        $now  = date('Y-m-d H:i:s');
        $rows = [];

        foreach ([
            ['TK/0', 'Tidak kawin, tanpa tanggungan', 54_000_000, 'A'],
            ['TK/1', 'Tidak kawin, 1 tanggungan', 58_500_000, 'A'],
            ['TK/2', 'Tidak kawin, 2 tanggungan', 63_000_000, 'B'],
            ['TK/3', 'Tidak kawin, 3 tanggungan', 67_500_000, 'B'],
            ['K/0', 'Kawin, tanpa tanggungan', 58_500_000, 'A'],
            ['K/1', 'Kawin, 1 tanggungan', 63_000_000, 'B'],
            ['K/2', 'Kawin, 2 tanggungan', 67_500_000, 'B'],
            ['K/3', 'Kawin, 3 tanggungan', 72_000_000, 'C'],
        ] as [$status, $description, $amount, $category]) {
            $rows[] = [
                'status'       => $status,
                'description'  => $description,
                'amount'       => $amount,
                'ter_category' => $category,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        $this->db->table('ptkp_rates')->insertBatch($rows);
    }
}
