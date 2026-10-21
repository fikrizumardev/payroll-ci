<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Rincian baris slip gaji: pendapatan, potongan, dan kontribusi perusahaan.
 */
class CreatePayrollItemsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'                  => $this->id(),
            'payroll_id'          => $this->foreignId(),
            'salary_component_id' => $this->foreignId(true),
            'code'                => $this->string(30),
            'name'                => $this->string(100),
            'type'                => $this->string(30), // earning | deduction | company_contribution
            'amount'              => $this->money(),
            'taxable'             => $this->flag(),
            'meta'                => $this->json(),     // dasar hitung: qty, rate, basis, dll.
            'sort_order'          => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['payroll_id', 'type']);
        $this->forge->addKey('code');
        $this->forge->addForeignKey('payroll_id', 'payrolls', 'id', 'CASCADE', 'CASCADE', 'fk_payroll_items_payroll');
        $this->forge->addForeignKey('salary_component_id', 'salary_components', 'id', 'CASCADE', 'SET NULL', 'fk_payroll_items_component');
        $this->forge->createTable('payroll_items', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('payroll_items', true);
    }
}
