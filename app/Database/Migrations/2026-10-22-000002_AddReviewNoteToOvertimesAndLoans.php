<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use App\Database\Support\SchemaColumns;
use CodeIgniter\Database\Migration;

/**
 * Catatan persetujuan lembur & pinjaman (wajib diisi saat pengajuan ditolak).
 */
class AddReviewNoteToOvertimesAndLoans extends Migration
{
    use SchemaColumns;

    public function up(): void
    {
        $this->forge->addColumn('overtimes', [
            'review_note' => $this->string(255, true) + ['after' => 'approved_at'],
        ]);
        $this->forge->addColumn('loans', [
            'review_note' => $this->string(255, true) + ['after' => 'approved_at'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('overtimes', 'review_note');
        $this->forge->dropColumn('loans', 'review_note');
    }
}
