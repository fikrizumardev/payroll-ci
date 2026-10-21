<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * Mengisi ulang seluruh data contoh PayrollKu.
 *
 *   php spark db:seed DatabaseSeeder
 *
 * Semua tabel aplikasi DIKOSONGKAN terlebih dahulu sehingga hasilnya selalu
 * sama (deterministik). Di environment production seeder menolak berjalan
 * kecuali diberi opsi --force.
 */
class DatabaseSeeder extends Seeder
{
    /** Urutan penting: setiap seeder bergantung pada data seeder sebelumnya. */
    private const SEEDERS = [
        SettingSeeder::class,
        PtkpSeeder::class,
        TerRateSeeder::class,
        HolidaySeeder::class,
        DepartmentSeeder::class,
        PositionSeeder::class,
        SalaryComponentSeeder::class,
        EmployeeSeeder::class,
        UserSeeder::class,
        AttendanceSeeder::class,
        LoanSeeder::class,
        AuditLogSeeder::class,
    ];

    public function run(): void
    {
        if (ENVIRONMENT === 'production' && ! (is_cli() && CLI::getOption('force'))) {
            throw new RuntimeException('DatabaseSeeder akan menghapus seluruh data. Jalankan dengan --force bila memang disengaja.');
        }

        $this->truncateTables();

        $this->db->transException(true)->transStart();

        foreach (self::SEEDERS as $seeder) {
            $this->call($seeder);
        }

        $this->db->transComplete();

        $this->printSummary();
    }

    private function truncateTables(): void
    {
        $tables = array_diff($this->db->listTables(), ['migrations']);

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $this->db->table($table)->truncate();
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function printSummary(): void
    {
        if (! is_cli() || $this->silent) {
            return;
        }

        CLI::newLine();
        CLI::write('Ringkasan data contoh:', 'green');

        $rows = [];

        foreach (['departments', 'positions', 'employees', 'users', 'attendances', 'overtimes', 'loans', 'holidays', 'ter_rates', 'audit_logs'] as $table) {
            $rows[] = [$table, number_format($this->db->table($table)->countAllResults(), 0, ',', '.')];
        }

        CLI::table($rows, ['Tabel', 'Jumlah']);
        CLI::write('Login: admin / ' . UserSeeder::ADMIN_PASSWORD . '  ·  karyawan: <username> / ' . UserSeeder::DEFAULT_PASSWORD, 'yellow');
    }
}
