<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateDepartmentsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'               => $this->id(),
            'code'             => $this->string(10),
            'name'             => $this->string(100),
            'description'      => $this->string(255, true),
            // FK ke employees ditambahkan di migrasi terpisah (relasi sirkular)
            'head_employee_id' => $this->foreignId(true),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('departments', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('departments', true);
    }
}
