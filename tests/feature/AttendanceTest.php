<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Attendance\AttendanceImporter;
use Tests\Support\Feature\SeededFeatureTestCase;

/**
 * Kehadiran harian, rekap bulanan, import CSV, dan guard periode payroll final.
 *
 * Data seed: Agustus 2026 terkunci, September 2026 dibayar, Oktober 2026 draft (sudah di-generate).
 *
 * @internal
 */
final class AttendanceTest extends SeededFeatureTestCase
{
    public function testAccessMatrix(): void
    {
        foreach (['attendance', 'attendance/recap'] as $path) {
            $this->as('budi.santoso')->get($path)->assertOK();
            $this->as('andi.pratama')->get($path)->assertStatus(403);
        }

        $this->as('budi.santoso')->get('attendance/import')->assertStatus(403);
        $this->as('budi.santoso')->post('attendance', ['date' => '2026-10-10', 'rows' => []])->assertStatus(403);
    }

    public function testDailyPageListsEmployeesEmployedOnThatDate(): void
    {
        $result = $this->as('sinta.maharani')->get('attendance', ['date' => '2026-10-05']);

        $result->assertOK();
        $result->assertSee('Senin, 5 Oktober 2026');
        $result->assertSee('Andi Pratama');
        $result->assertDontSee('Hadi Gunawan'); // resign 15 Sep 2026
        $result->assertSee('Simpan Kehadiran');

        // Periode September sudah dibayar → hanya bisa dilihat
        $closed = $this->as('sinta.maharani')->get('attendance', ['date' => '2026-09-14']);
        $closed->assertSee('Hadi Gunawan');
        $closed->assertSee('Kehadiran hanya dapat dilihat');
        $closed->assertDontSee('Simpan Kehadiran');
    }

    public function testDailySaveUpsertsAndDetectsLateArrival(): void
    {
        $andi  = $this->employee('EMP-0014');
        $dimas = $this->employee('EMP-0004');
        $putri = $this->employee('EMP-0005');

        $this->as('sinta.maharani')->post('attendance', [
            'date' => '2026-10-10',
            'rows' => [
                $andi  => ['status' => 'present', 'check_in' => '08:40', 'check_out' => '17:30', 'note' => 'Standby rilis'],
                $dimas => ['status' => 'holiday', 'check_in' => '09:00', 'check_out' => '', 'note' => ''],
                $putri => ['status' => '', 'check_in' => '', 'check_out' => '', 'note' => ''],
            ],
        ])->assertRedirect();

        // Hadir melewati toleransi 15 menit dari 08:00 → otomatis terlambat 40 menit
        $this->seeInDatabase('attendances', ['employee_id' => $andi, 'date' => '2026-10-10', 'status' => 'late', 'late_minutes' => 40, 'check_in' => '08:40:00', 'note' => 'Standby rilis']);
        $this->seeInDatabase('attendances', ['employee_id' => $dimas, 'date' => '2026-10-10', 'status' => 'holiday', 'check_in' => null]);
        $this->dontSeeInDatabase('attendances', ['employee_id' => $putri, 'date' => '2026-10-10']);
        $this->seeInDatabase('audit_logs', ['action' => 'attendance.save', 'entity_id' => '2026-10-10']);
        $this->assertStringContainsString('2 baru', (string) session('success'));
        $this->assertStringContainsString('Oktober 2026', (string) session('warning')); // payroll Oktober perlu di-generate ulang

        // Simpan ulang: update baris yang berubah, tidak menggandakan data
        $this->as('sinta.maharani')->post('attendance', [
            'date' => '2026-10-10',
            'rows' => [
                $andi  => ['status' => 'present', 'check_in' => '07:55', 'check_out' => '17:30', 'note' => 'Standby rilis'],
                $dimas => ['status' => 'holiday', 'check_in' => '', 'check_out' => '', 'note' => ''],
            ],
        ]);

        $this->seeInDatabase('attendances', ['employee_id' => $andi, 'date' => '2026-10-10', 'status' => 'present', 'late_minutes' => 0]);
        $this->assertSame(2, $this->db->table('attendances')->where('date', '2026-10-10')->countAllResults());
        $this->assertStringContainsString('0 baru, 1 diubah, 1 tetap', (string) session('success'));
    }

    public function testClosedPeriodCannotBeChanged(): void
    {
        $andi   = $this->employee('EMP-0014');
        $before = $this->db->table('attendances')->where(['employee_id' => $andi, 'date' => '2026-09-10'])->get()->getRowArray();

        $this->as('sinta.maharani')->post('attendance', [
            'date' => '2026-09-10',
            'rows' => [$andi => ['status' => 'absent']],
        ]);

        $this->assertStringContainsString('September 2026', (string) session('error'));
        $this->seeInDatabase('attendances', ['employee_id' => $andi, 'date' => '2026-09-10', 'status' => $before['status']]);
    }

    public function testInvalidRowsAreReportedPerEmployee(): void
    {
        $andi = $this->employee('EMP-0014');

        $this->as('sinta.maharani')->post('attendance', [
            'date' => '2026-10-11',
            'rows' => [$andi => ['status' => 'present', 'check_in' => '17:00', 'check_out' => '08:00']],
        ]);

        $errors = session('errors');
        $this->assertSame('Jam pulang harus setelah jam masuk.', $errors["rows.{$andi}.check_out"]);
        $this->dontSeeInDatabase('attendances', ['employee_id' => $andi, 'date' => '2026-10-11']);
    }

    public function testFutureDateOnlyAcceptsPlannedAbsence(): void
    {
        $andi   = $this->employee('EMP-0014');
        $future = date('Y-m-d', strtotime('+10 days'));

        $this->as('sinta.maharani')->post('attendance', ['date' => $future, 'rows' => [$andi => ['status' => 'present']]]);
        $this->assertArrayHasKey("rows.{$andi}.status", session('errors'));
        $this->dontSeeInDatabase('attendances', ['employee_id' => $andi, 'date' => $future]);

        $this->as('sinta.maharani')->post('attendance', ['date' => $future, 'rows' => [$andi => ['status' => 'leave', 'note' => 'Cuti tahunan']]]);
        $this->seeInDatabase('attendances', ['employee_id' => $andi, 'date' => $future, 'status' => 'leave']);
    }

    public function testMonthlyRecapRendersGrid(): void
    {
        $result = $this->as('budi.santoso')->get('attendance/recap', ['month' => '2026-09']);

        $result->assertOK();
        $result->assertSee('Rekap Kehadiran September 2026');
        $result->assertSee('Galih Prasetyo');
        $result->assertSee('Hadi Gunawan'); // masih bekerja s.d. 15 Sep
        $result->assertSee('Hadir per hari');

        $engineering = (int) $this->db->table('departments')->where('code', 'ENG')->get()->getRow()->id;
        $filtered    = $this->as('budi.santoso')->get('attendance/recap', ['month' => '2026-09', 'department_id' => $engineering]);
        $filtered->assertSee('Galih Prasetyo');
        $filtered->assertDontSee('EMP-0007 · FIN'); // Budi (Finance) tidak tampil di filter Engineering
    }

    public function testTemplateDownload(): void
    {
        $result = $this->as('sinta.maharani')->get('attendance/import/template', ['date' => '2026-10-19']);

        $result->assertOK();
        $result->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $result->getBody();
        $this->assertStringContainsString('nip;nama;tanggal;status;jam_masuk;jam_pulang;keterangan', $body);
        $this->assertStringContainsString('EMP-0014;"Andi Pratama";2026-10-19;hadir', $body);
        $this->assertStringNotContainsString('EMP-0031', $body); // Hadi sudah resign
    }

    public function testImportPreviewValidatesEachRowThenCommits(): void
    {
        $csv = implode("\n", [
            'NIP,Nama,Tanggal,Status,Jam Masuk,Jam Pulang,Keterangan',
            'EMP-0014,Andi,2026-10-17,hadir,08:00,17:00,Lembur sabtu',
            'emp-0004,Dimas,17/10/2026,S,,,Demam',
            'EMP-9999,Siapa,2026-10-17,hadir,,,',
            'EMP-0014,Andi,2026-10-17,izin,,,',
            'EMP-0014,Andi,2026-09-07,hadir,,,',
            'EMP-0014,Andi,2026-10-13,alpha,,,Tanpa kabar',
            'EMP-0005,Putri,2026-10-17,piknik,,,',
        ]);

        $importer = AttendanceImporter::make();
        $rows     = $importer->read($csv);
        $preview  = $importer->preview($rows);

        $this->assertSame(['total' => 7, 'valid' => 3, 'errors' => 4, 'insert' => 2, 'update' => 1, 'same' => 0], $preview['summary']);
        $this->assertStringContainsString('tidak terdaftar', $preview['rows'][2]['errors'][0]);
        $this->assertStringContainsString('Duplikat dengan baris 2', $preview['rows'][3]['errors'][0]);
        $this->assertStringContainsString('September 2026', $preview['rows'][4]['errors'][0]);
        $this->assertStringContainsString('tidak dikenal', $preview['rows'][6]['errors'][0]);

        $this->as('sinta.maharani', ['attendance_import' => ['filename' => 'absensi-oktober.csv', 'rows' => $rows]])
            ->post('attendance/import/commit')
            ->assertRedirect();

        $andi  = $this->employee('EMP-0014');
        $dimas = $this->employee('EMP-0004');
        $this->seeInDatabase('attendances', ['employee_id' => $andi, 'date' => '2026-10-17', 'status' => 'present', 'note' => 'Lembur sabtu']);
        $this->seeInDatabase('attendances', ['employee_id' => $dimas, 'date' => '2026-10-17', 'status' => 'sick', 'note' => 'Demam']);
        $this->seeInDatabase('attendances', ['employee_id' => $andi, 'date' => '2026-10-13', 'status' => 'absent']);
        $this->seeInDatabase('audit_logs', ['action' => 'attendance.import']);
        $this->assertStringContainsString('2 baru, 1 diubah, 4 baris error dilewati', (string) session('success'));
    }

    public function testImportRejectsFileWithoutRequiredColumns(): void
    {
        $this->expectExceptionMessage('Kolom wajib tidak ditemukan: tanggal');

        AttendanceImporter::make()->read("nip;status\nEMP-0014;hadir");
    }

    public function testImportPageShowsPreviewFromSession(): void
    {
        $rows = AttendanceImporter::make()->read("nip;tanggal;status\nEMP-0014;2026-10-18;libur");

        $result = $this->as('sinta.maharani', ['attendance_import' => ['filename' => 'minggu.csv', 'rows' => $rows]])->get('attendance/import');

        $result->assertOK();
        $result->assertSee('Pratinjau: minggu.csv');
        $result->assertSee('Simpan 1 baris');
    }
}
