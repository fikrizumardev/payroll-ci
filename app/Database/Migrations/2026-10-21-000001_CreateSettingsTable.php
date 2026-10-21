<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateSettingsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'key'         => $this->string(100),
            'value'       => ['type' => 'TEXT', 'null' => true],
            'group'       => $this->string(50, false, 'general'),
            'type'        => $this->string(20, false, 'string'),
            'label'       => $this->string(150),
            'description' => $this->string(255, true),
            'sort_order'  => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('key');
        $this->forge->addKey('group');
        $this->forge->createTable('settings', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('settings', true);
    }
}
