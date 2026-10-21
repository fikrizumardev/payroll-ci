<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Services\Payslip\PayslipService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Aturan akses slip gaji & penamaan berkas PDF.
 *
 * @internal
 */
final class PayslipAccessTest extends CIUnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function slip(int $employeeId, string $status): array
    {
        return [
            'payroll'  => ['id' => 45, 'employee_id' => $employeeId],
            'period'   => ['year' => 2026, 'month' => 9, 'status' => $status],
            'employee' => ['nip' => 'EMP-0014'],
            'final'    => in_array($status, PayslipService::FINAL_STATUSES, true),
        ];
    }

    public function testEmployeeOnlySeesOwnFinalSlip(): void
    {
        $andi = ['role' => 'employee', 'employee_id' => 14];

        $this->assertTrue(PayslipService::canView($andi, $this->slip(14, 'paid')));
        $this->assertTrue(PayslipService::canView($andi, $this->slip(14, 'locked')));
        $this->assertFalse(PayslipService::canView($andi, $this->slip(14, 'approved')));
        $this->assertFalse(PayslipService::canView($andi, $this->slip(14, 'draft')));
        $this->assertFalse(PayslipService::canView($andi, $this->slip(11, 'paid')));
        $this->assertFalse(PayslipService::canView(['role' => 'employee', 'employee_id' => null], $this->slip(14, 'paid')));
    }

    public function testStaffSeesEverySlip(): void
    {
        foreach (['superadmin', 'hr', 'finance'] as $role) {
            $this->assertTrue(PayslipService::canView(['role' => $role, 'employee_id' => null], $this->slip(11, 'draft')), $role);
        }
    }

    public function testFilename(): void
    {
        $this->assertSame('Slip-Gaji-EMP-0014-2026-09.pdf', PayslipService::filename($this->slip(14, 'paid')));
    }
}
