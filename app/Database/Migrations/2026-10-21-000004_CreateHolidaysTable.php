<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateHolidaysTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'   => $this->id(),
            'date' => ['type' => 'DATE'],
            'name' => $this->string(150),
            // national | collective_leave | company
            'type' => $this->string(20, false, 'national'),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('date');
        $this->forge->createTable('holidays', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('holidays', true);
    }
}
