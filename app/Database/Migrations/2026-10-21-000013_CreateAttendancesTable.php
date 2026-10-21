<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateAttendancesTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'           => $this->id(),
            'employee_id'  => $this->foreignId(),
            'date'         => ['type' => 'DATE'],
            // present | late | sick | leave | permit | absent | holiday
            'status'       => $this->string(10),
            'check_in'     => ['type' => 'TIME', 'null' => true],
            'check_out'    => ['type' => 'TIME', 'null' => true],
            'late_minutes' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'note'         => $this->string(255, true),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['employee_id', 'date']);
        $this->forge->addKey(['date', 'status']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE', 'fk_attendances_employee');
        $this->forge->createTable('attendances', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('attendances', true);
    }
}
