<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

class CreateLoansTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'           => $this->id(),
            'loan_no'      => $this->string(20),
            'employee_id'  => $this->foreignId(),
            'amount'       => $this->money(),
            'tenor_months' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'installment'  => $this->money(),
            'start_period' => ['type' => 'CHAR', 'constraint' => 7], // YYYY-MM, periode potongan pertama
            'remaining'    => $this->money(),
            // pending | approved | active | paid_off | rejected
            'status'       => $this->string(20, false, 'pending'),
            'purpose'      => $this->string(255, true),
            'notes'        => $this->string(255, true),
            'approved_by'  => $this->foreignId(true),
            'approved_at'  => ['type' => 'DATETIME', 'null' => true],
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('loan_no');
        $this->forge->addKey(['employee_id', 'status']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'RESTRICT', 'fk_loans_employee');
        $this->forge->addForeignKey('approved_by', 'users', 'id', 'CASCADE', 'SET NULL', 'fk_loans_approver');
        $this->forge->createTable('loans', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('loans', true);
    }
}
