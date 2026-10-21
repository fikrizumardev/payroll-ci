<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Saldo awal PPh 21 tahun berjalan (data migrasi sebelum sistem dipakai).
 * Dipakai pada perhitungan setahun di masa pajak terakhir (Desember / resign).
 */
class CreateTaxYtdBalancesTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'            => $this->id(),
            'employee_id'   => $this->foreignId(),
            'year'          => ['type' => 'SMALLINT', 'unsigned' => true],
            'through_month' => ['type' => 'TINYINT', 'unsigned' => true], // saldo s.d. bulan ini
            'months'        => ['type' => 'TINYINT', 'unsigned' => true], // jumlah bulan bekerja
            'gross_income'  => $this->money(),                            // bruto PPh 21
            'pension'       => $this->money(),                            // iuran JHT + JP karyawan
            'pph21'         => $this->money(),                            // PPh 21 yang sudah dipotong
            'note'          => $this->string(255, true),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['employee_id', 'year']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE', 'fk_tax_ytd_balances_employee');
        $this->forge->createTable('tax_ytd_balances', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('tax_ytd_balances', true);
    }
}
