<?php

declare(strict_types=1);

namespace Tests\Unit\Attendance;

use App\Models\OvertimeModel;
use App\Services\Attendance\AttendanceImporter;
use App\Services\Attendance\AttendanceService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Parser nilai import CSV & perhitungan durasi lembur.
 *
 * @internal
 */
final class AttendanceParsingTest extends CIUnitTestCase
{
    public function testParseStatusAcceptsCodesLabelsAndLetters(): void
    {
        $this->assertSame('present', AttendanceImporter::parseStatus('Hadir'));
        $this->assertSame('late', AttendanceImporter::parseStatus(' T '));
        $this->assertSame('absent', AttendanceImporter::parseStatus('alpha'));
        $this->assertSame('permit', AttendanceImporter::parseStatus('ijin'));
        $this->assertSame('holiday', AttendanceImporter::parseStatus('holiday'));
        $this->assertNull(AttendanceImporter::parseStatus('piknik'));
    }

    public function testParseDate(): void
    {
        $this->assertSame('2026-10-05', AttendanceImporter::parseDate('2026-10-05'));
        $this->assertSame('2026-10-05', AttendanceImporter::parseDate('5/10/2026'));
        $this->assertSame('2026-10-05', AttendanceImporter::parseDate('05-10-2026'));
        $this->assertNull(AttendanceImporter::parseDate('2026-02-30'));
        $this->assertNull(AttendanceImporter::parseDate('Okt 5'));
    }

    public function testParseTime(): void
    {
        $this->assertSame('08:05:00', AttendanceService::parseTime('8:05'));
        $this->assertSame('17:30:00', AttendanceService::parseTime('17.30'));
        $this->assertSame('07:59:30', AttendanceService::parseTime('07:59:30'));
        $this->assertNull(AttendanceService::parseTime(' '));
        $this->assertFalse(AttendanceService::parseTime('25:00'));
        $this->assertFalse(AttendanceService::parseTime('jam 8'));
    }

    public function testOvertimeHoursIncludingPastMidnight(): void
    {
        $this->assertSame(3.5, OvertimeModel::hoursBetween('18:00:00', '21:30:00'));
        $this->assertSame(3.5, OvertimeModel::hoursBetween('22:00', '01:30'));
        $this->assertSame(0.25, OvertimeModel::hoursBetween('17:00', '17:15'));
    }
}
