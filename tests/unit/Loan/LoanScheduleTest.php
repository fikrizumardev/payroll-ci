<?php

declare(strict_types=1);

namespace Tests\Unit\Loan;

use App\Models\LoanModel;
use App\Services\Loan\LoanSchedule;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class LoanScheduleTest extends CIUnitTestCase
{
    public function testInstallmentIsRoundedUpSoLoanEndsOnTenor(): void
    {
        $this->assertSame(666_667, LoanModel::installmentFor(2_000_000, 3));
        $this->assertSame(1_000_000, LoanModel::installmentFor(12_000_000, 12));

        $schedule = LoanSchedule::build(['amount' => 2_000_000, 'installment' => 666_667, 'start_period' => '2026-11'], []);

        $this->assertSame(['2026-11', '2026-12', '2027-01'], array_column($schedule, 'period'));
        $this->assertSame([666_667, 666_667, 666_666], array_column($schedule, 'amount'));
        $this->assertSame(0, end($schedule)['balance']);
        $this->assertSame('2027-01', LoanSchedule::endPeriod($schedule));
    }

    public function testCombinesPostedRunningAndProjectedInstallments(): void
    {
        $loan = ['amount' => 6_000_000, 'installment' => 1_000_000, 'start_period' => '2026-07'];

        $schedule = LoanSchedule::build(
            $loan,
            [
                ['period' => '2026-08', 'amount' => 1_000_000, 'paid_at' => '2026-08-31 13:30:00'],
                ['period' => '2026-07', 'amount' => 1_000_000, 'paid_at' => '2026-07-31 17:00:00'],
            ],
            [
                ['period' => '2026-10', 'amount' => 1_000_000, 'status' => 'draft'],
                ['period' => '2026-09', 'amount' => 1_000_000, 'status' => 'paid'],
                ['period' => '2026-08', 'amount' => 1_000_000, 'status' => 'locked'], // sudah dibukukan → diabaikan
            ],
        );

        $this->assertSame(['2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12'], array_column($schedule, 'period'));
        $this->assertSame(
            [LoanSchedule::PAID, LoanSchedule::PAID, LoanSchedule::DEDUCTING, LoanSchedule::DEDUCTING, LoanSchedule::SCHEDULED, LoanSchedule::SCHEDULED],
            array_column($schedule, 'state'),
        );
        $this->assertSame([5_000_000, 4_000_000, 3_000_000, 2_000_000, 1_000_000, 0], array_column($schedule, 'balance'));
        $this->assertSame('paid', $schedule[2]['period_status']);
    }

    public function testSkippedMonthShiftsProjection(): void
    {
        // Cicilan pertama baru terpotong Oktober (mis. September cuti di luar tanggungan)
        $schedule = LoanSchedule::build(
            ['amount' => 3_000_000, 'installment' => 1_000_000, 'start_period' => '2026-08'],
            [['period' => '2026-10', 'amount' => 1_000_000]],
        );

        $this->assertSame(['2026-10', '2026-11', '2026-12'], array_column($schedule, 'period'));
    }

    public function testPaidOffLoanHasNoProjection(): void
    {
        $schedule = LoanSchedule::build(
            ['amount' => 2_000_000, 'installment' => 1_000_000, 'start_period' => '2026-05'],
            [['period' => '2026-05', 'amount' => 1_000_000], ['period' => '2026-06', 'amount' => 1_000_000]],
        );

        $this->assertCount(2, $schedule);
        $this->assertSame([LoanSchedule::PAID, LoanSchedule::PAID], array_column($schedule, 'state'));
    }
}
