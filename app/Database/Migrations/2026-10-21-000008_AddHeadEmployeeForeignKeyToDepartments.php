<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * departments.head_employee_id → employees.id
 * Dipisah karena employees juga bergantung pada departments (relasi sirkular).
 */
class AddHeadEmployeeForeignKeyToDepartments extends Migration
{
    public function up(): void
    {
        $this->forge->addForeignKey('head_employee_id', 'employees', 'id', 'CASCADE', 'SET NULL', 'fk_departments_head_employee');
        $this->forge->processIndexes('departments');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('departments', 'fk_departments_head_employee');
    }
}
