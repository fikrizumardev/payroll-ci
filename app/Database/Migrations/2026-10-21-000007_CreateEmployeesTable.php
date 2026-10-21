<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateEmployeesTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'                => $this->id(),
            'nip'               => $this->string(20),
            'full_name'         => $this->string(150),
            'nik'               => ['type' => 'CHAR', 'constraint' => 16],
            'npwp'              => $this->string(20, true),
            'gender'            => ['type' => 'CHAR', 'constraint' => 1], // L | P
            'birth_place'       => $this->string(100, true),
            'birth_date'        => ['type' => 'DATE', 'null' => true],
            'address'           => ['type' => 'TEXT', 'null' => true],
            'phone'             => $this->string(20, true),
            'department_id'     => $this->foreignId(),
            'position_id'       => $this->foreignId(),
            'employment_type'   => $this->string(20, false, 'permanent'), // permanent | contract | probation
            'status'            => $this->string(20, false, 'active'),    // active | resigned | terminated
            'join_date'         => ['type' => 'DATE'],
            'resign_date'       => ['type' => 'DATE', 'null' => true],
            'ptkp_status'       => $this->string(5),
            'basic_salary'      => $this->money(),
            'bank_name'         => $this->string(50, true),
            'bank_account_no'   => $this->string(30, true),
            'bank_account_name' => $this->string(150, true),
            'bpjs_kes_enrolled' => $this->flag(true),
            'bpjs_tk_enrolled'  => $this->flag(true),
            'photo'             => $this->string(255, true),
            ...$this->timestamps(softDeletes: true),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('nip');
        $this->forge->addUniqueKey('nik');
        $this->forge->addKey('department_id');
        $this->forge->addKey('position_id');
        $this->forge->addKey('status');
        $this->forge->addKey('ptkp_status');
        $this->forge->addForeignKey('department_id', 'departments', 'id', 'CASCADE', 'RESTRICT', 'fk_employees_department');
        $this->forge->addForeignKey('position_id', 'positions', 'id', 'CASCADE', 'RESTRICT', 'fk_employees_position');
        $this->forge->addForeignKey('ptkp_status', 'ptkp_rates', 'status', 'CASCADE', 'RESTRICT', 'fk_employees_ptkp');
        $this->forge->createTable('employees', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('employees', true);
    }
}
