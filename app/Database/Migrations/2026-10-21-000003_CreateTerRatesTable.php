<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateTerRatesTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'         => $this->id(),
            'category'   => ['type' => 'CHAR', 'constraint' => 1],
            'min_income' => $this->money(),
            // NULL = tidak ada batas atas (baris terakhir tiap kategori)
            'max_income' => ['type' => 'BIGINT', 'constraint' => 20, 'null' => true],
            'rate'       => $this->percent('0.000'),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['category', 'min_income']);
        $this->forge->createTable('ter_rates', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ter_rates', true);
    }
}
