<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreatePtkpRatesTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'status'       => $this->string(5),
            'description'  => $this->string(100),
            'amount'       => $this->money(),
            'ter_category' => ['type' => 'CHAR', 'constraint' => 1],
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('status');
        $this->forge->createTable('ptkp_rates', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ptkp_rates', true);
    }
}
