<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateSalaryComponentsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'             => $this->id(),
            'code'           => $this->string(30),
            'name'           => $this->string(100),
            'type'           => $this->string(30),                 // earning | deduction | company_contribution
            'calc_type'      => $this->string(20, false, 'fixed'), // fixed | daily | formula | manual
            'default_amount' => $this->money(),
            'taxable'        => $this->flag(),
            'is_bpjs_base'   => $this->flag(),
            'is_system'      => $this->flag(),
            'is_active'      => $this->flag(true),
            'sort_order'     => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'description'    => $this->string(255, true),
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey(['type', 'sort_order']);
        $this->forge->createTable('salary_components', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('salary_components', true);
    }
}
