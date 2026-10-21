<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Nominal komponen gaji per karyawan (override dari default komponen/jabatan).
 */
class CreateEmployeeComponentsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'                  => $this->id(),
            'employee_id'         => $this->foreignId(),
            'salary_component_id' => $this->foreignId(),
            'amount'              => $this->money(),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['employee_id', 'salary_component_id']);
        $this->forge->addKey('salary_component_id');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE', 'fk_employee_components_employee');
        $this->forge->addForeignKey('salary_component_id', 'salary_components', 'id', 'CASCADE', 'RESTRICT', 'fk_employee_components_component');
        $this->forge->createTable('employee_components', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('employee_components', true);
    }
}
