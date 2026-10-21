<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Riwayat cicilan pinjaman. Dibuat setelah tabel payrolls karena
 * cicilan terhubung ke slip gaji tempat potongan dilakukan.
 */
class CreateLoanInstallmentsTable extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addField([
            'id'             => $this->id(),
            'loan_id'        => $this->foreignId(),
            'payroll_id'     => $this->foreignId(true), // NULL = dibayar di luar sistem payroll
            'installment_no' => ['type' => 'TINYINT', 'unsigned' => true],
            'period'         => ['type' => 'CHAR', 'constraint' => 7], // YYYY-MM
            'amount'         => $this->money(),
            'paid_at'        => ['type' => 'DATETIME', 'null' => true],
            ...$this->timestamps(),
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['loan_id', 'installment_no']);
        $this->forge->addKey('payroll_id');
        $this->forge->addForeignKey('loan_id', 'loans', 'id', 'CASCADE', 'CASCADE', 'fk_loan_installments_loan');
        $this->forge->addForeignKey('payroll_id', 'payrolls', 'id', 'CASCADE', 'SET NULL', 'fk_loan_installments_payroll');
        $this->forge->createTable('loan_installments', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('loan_installments', true);
    }
}
