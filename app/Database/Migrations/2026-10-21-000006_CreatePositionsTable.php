<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreatePositionsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'                      => $this->id(),
            'department_id'           => $this->foreignId(),
            'code'                    => $this->string(10),
            'name'                    => $this->string(100),
            'grade'                   => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'base_salary'             => $this->money(),
            'position_allowance'      => $this->money(),
            'communication_allowance' => $this->money(),
            'overtime_eligible'       => $this->flag(),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('department_id');
        $this->forge->addForeignKey('department_id', 'departments', 'id', 'CASCADE', 'RESTRICT', 'fk_positions_department');
        $this->forge->createTable('positions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('positions', true);
    }
}
