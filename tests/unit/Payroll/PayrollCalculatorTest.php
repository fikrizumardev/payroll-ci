<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Services\Payroll\EmployeePayrollInput;
use App\Services\Payroll\Money;
use App\Services\Payroll\OvertimeCalculator;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\Pph21Calculator;
use App\Services\Payroll\WorkdayCalendar;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Payroll\RatesFixture;

/**
 * Engine payroll murni — angka acuan dari PRD §6.
 *
 * @internal
 */
final class PayrollCalculatorTest extends CIUnitTestCase
{
    private PayrollCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PayrollCalculator(RatesFixture::rates());
    }

    /**
     * PRD §6.5: gaji pokok 8 jt, tunj. jabatan 1 jt, transport + makan 1,2 jt, TK/0.
     */
    public function testPrdReferenceExample(): void
    {
        $input = new EmployeePayrollInput(
            employeeId: 1,
            ptkpStatus: 'TK/0',
            basicSalary: 8_000_000,
            periodWorkdays: 20,
            activeWorkdays: 20,
            month: 10,
            fixedEarnings: [['code' => 'POS_ALW', 'name' => 'Tunjangan Jabatan', 'amount' => 1_000_000, 'taxable' => true, 'bpjsBase' => true]],
            dailyEarnings: [
                ['code' => 'TRANSPORT', 'name' => 'Tunjangan Transport', 'rate' => 30_000, 'taxable' => true],
                ['code' => 'MEAL', 'name' => 'Uang Makan', 'rate' => 30_000, 'taxable' => true],
            ],
            presentDays: 20,
        );

        $result = $this->calculator->calculate($input);

        $this->assertSame(9_000_000, $result->bpjsBase);
        $this->assertSame(360_000, $result->amountOf('BPJS_KES_ER'));
        $this->assertSame(21_600, $result->amountOf('JKK_ER'));
        $this->assertSame(27_000, $result->amountOf('JKM_ER'));
        $this->assertSame(333_000, $result->amountOf('JHT_ER'));
        $this->assertSame(180_000, $result->amountOf('JP_ER'));
        $this->assertSame(90_000, $result->amountOf('BPJS_KES_EE'));
        $this->assertSame(180_000, $result->amountOf('JHT_EE'));
        $this->assertSame(90_000, $result->amountOf('JP_EE'));

        $this->assertSame(10_608_600, $result->brutoPph);
        $this->assertSame('A', $result->terCategory);
        $this->assertSame(2.5, $result->terRate);
        $this->assertSame(265_215, $result->pph21);
        $this->assertSame(10_200_000, $result->totalEarning());
        $this->assertSame(9_574_785, $result->takeHomePay());
        $this->assertSame([], $result->warnings);
    }

    /**
     * Gaji 45 jt (Direktur Utama, K/3): batas upah BPJS Kes 12 jt & JP 10.547.400, TER C.
     */
    public function testWageCapsAndTerCategoryC(): void
    {
        $result = $this->calculator->calculate(RatesFixture::input(45_000_000, 10_000_000, 1_000_000, 'K/3'));

        $this->assertSame(56_000_000, $result->bpjsBase);
        $this->assertSame(480_000, $result->amountOf('BPJS_KES_ER'));   // 4% × 12 jt
        $this->assertSame(120_000, $result->amountOf('BPJS_KES_EE'));   // 1% × 12 jt
        $this->assertSame(210_948, $result->amountOf('JP_ER'));         // 2% × 10.547.400
        $this->assertSame(105_474, $result->amountOf('JP_EE'));         // 1% × 10.547.400
        $this->assertSame(2_072_000, $result->amountOf('JHT_ER'));      // JHT tanpa batas upah

        // 56 jt + 630 rb + 735 rb + 480 rb + 134.400 + 168.000
        $this->assertSame(58_147_400, $result->brutoPph);
        $this->assertSame('C', $result->terCategory);
        $this->assertSame(19.0, $result->terRate);
        $this->assertSame(11_048_006, $result->pph21);
    }

    /**
     * HR Manager K/1 → TER kategori B.
     */
    public function testTerCategoryB(): void
    {
        $result = $this->calculator->calculate(RatesFixture::input(18_000_000, 4_000_000, 500_000, 'K/1'));

        // 22,5 jt + 630 rb + 735 rb + 480 rb (Kes, batas 12 jt) + 54.000 + 67.500
        $this->assertSame(24_466_500, $result->brutoPph);
        $this->assertSame('B', $result->terCategory);
        $this->assertSame(9.0, $result->terRate);
        $this->assertSame(2_201_985, $result->pph21);
    }

    /**
     * Anisa masuk 10 Agu 2026: 14 dari 19 hari kerja Agustus.
     */
    public function testProrationForMidMonthJoiner(): void
    {
        $calendar = new WorkdayCalendar(['2026-08-17', '2026-08-25']);

        $this->assertSame(19, $calendar->count('2026-08-01', '2026-08-31'));
        $active = $calendar->activeWorkdays('2026-08-01', '2026-08-31', '2026-08-10', null);
        $this->assertSame(14, $active);

        $result = $this->calculator->calculate(RatesFixture::input(7_000_000, 750_000, 200_000, 'TK/0', [
            'periodWorkdays' => 19,
            'activeWorkdays' => $active,
            'presentDays'    => 14,
        ]));

        $this->assertSame(Money::ratio(7_000_000, 14, 19), $result->amountOf('BASIC'));
        $this->assertSame(5_157_895, $result->amountOf('BASIC'));
        $this->assertSame(552_632, $result->amountOf('POS_ALW'));
        $this->assertSame(420_000, $result->amountOf('TRANSPORT'));
        // Iuran BPJS tetap dari upah sebulan penuh
        $this->assertSame(7_950_000, $result->bpjsBase);
    }

    /**
     * Hadi resign 15 Sep 2026: 11 dari 22 hari kerja September.
     */
    public function testProrationForResignation(): void
    {
        $calendar = new WorkdayCalendar();

        $this->assertSame(22, $calendar->count('2026-09-01', '2026-09-30'));
        $this->assertSame(11, $calendar->activeWorkdays('2026-09-01', '2026-09-30', '2020-02-03', '2026-09-15'));

        $result = $this->calculator->calculate(RatesFixture::input(13_000_000, 1_500_000, 300_000, 'K/1', [
            'periodWorkdays' => 22,
            'activeWorkdays' => 11,
            'presentDays'    => 11,
        ]));

        $this->assertSame(6_500_000, $result->amountOf('BASIC'));
        $this->assertSame(750_000, $result->amountOf('POS_ALW'));
    }

    /**
     * Lembur 3 jam = 1,5 + 2 + 2 = 5,5 × upah sejam.
     */
    public function testOvertimeMultiplier(): void
    {
        $this->assertSame(55_000, OvertimeCalculator::multiplierUnits([3.0]));
        $this->assertSame(15_000, OvertimeCalculator::multiplierUnits([1.0]));
        $this->assertSame(25_000, OvertimeCalculator::multiplierUnits([1.5]));
        $this->assertSame(70_000, OvertimeCalculator::multiplierUnits([1.0, 3.0]));

        $line = (new OvertimeCalculator(173))->calculate(9_000_000, [3.0]);
        $this->assertSame(286_127, $line->amount); // 9.000.000 / 173 × 5,5

        $eligible = $this->calculator->calculate(RatesFixture::input(8_000_000, 1_000_000, 0, 'TK/0', [
            'overtimeEligible' => true,
            'overtimeHours'    => [3.0],
        ]));
        $this->assertSame(286_127, $eligible->amountOf('OVERTIME'));

        $notEligible = $this->calculator->calculate(RatesFixture::input(8_000_000, 1_000_000, 0, 'TK/0', [
            'overtimeEligible' => false,
            'overtimeHours'    => [3.0],
        ]));
        $this->assertSame(0, $notEligible->amountOf('OVERTIME'));
        $this->assertCount(1, $notEligible->warnings);
    }

    public function testAbsenceAndLatePenaltyReduceTaxableGross(): void
    {
        $base   = $this->calculator->calculate(RatesFixture::input(11_500_000, 1_500_000, 300_000, 'K/0'));
        $result = $this->calculator->calculate(RatesFixture::input(11_500_000, 1_500_000, 300_000, 'K/0', [
            'presentDays' => 19,
            'absentDays'  => 1,
            'lateDays'    => 2,
        ]));

        $this->assertSame(Money::ratio(11_500_000, 1, 21), $result->amountOf('ABSENT_DED'));
        $this->assertSame(50_000, $result->amountOf('LATE_PEN'));
        $this->assertSame(
            $base->brutoPph - 2 * 65_000 - $result->amountOf('ABSENT_DED') - 50_000,
            $result->brutoPph,
        );
    }

    public function testLoanAndManualAdjustments(): void
    {
        $result = $this->calculator->calculate(RatesFixture::input(12_000_000, 1_500_000, 300_000, 'TK/0', [
            'adjustments' => [
                ['name' => 'Bonus Proyek', 'type' => 'earning', 'amount' => 2_000_000, 'taxable' => true],
                ['name' => 'Koreksi Kelebihan Bayar', 'type' => 'deduction', 'amount' => 150_000, 'taxable' => false],
            ],
            'loanInstallments' => [['loanId' => 7, 'loanNo' => 'PJM-2026-0003', 'amount' => 1_000_000]],
        ]));

        $this->assertSame(2_000_000, $result->amountOf('ADJ'));
        $this->assertSame(150_000, $result->amountOf('ADJ_DED'));
        $this->assertSame(1_000_000, $result->amountOf('LOAN'));
        $this->assertSame(
            $result->totalEarning() - $result->totalDeduction(),
            $result->takeHomePay(),
        );
    }

    /**
     * Desember: PPh setahun (Pasal 17) dikurangi PPh Jan–Nov.
     * Karyawan contoh PRD §6.5 selama 12 bulan.
     */
    public function testDecemberAnnualCalculation(): void
    {
        $pph = new Pph21Calculator(RatesFixture::rates());

        // bruto 10.608.600 × 12; iuran pensiun (JHT 180 rb + JP 90 rb) × 12; PPh Jan–Nov 265.215 × 11
        $annual = $pph->annual(127_303_200, 3_240_000, 12, 'TK/0', 2_917_365);

        $this->assertSame(6_000_000, $annual['position_cost']);   // min(5% bruto, 6 jt)
        $this->assertSame(118_063_200, $annual['net_income']);
        $this->assertSame(64_063_000, $annual['taxable_income']); // dibulatkan ke bawah ribuan
        $this->assertSame(3_609_450, $annual['pph21_annual']);    // 5% × 60 jt + 15% × 4.063.000
        $this->assertSame(692_085, $annual['pph21']);

        // Lewat engine: hasil bulan Desember = PPh setahun − PPh yang sudah dipotong
        $december = $this->calculator->calculate(new EmployeePayrollInput(
            employeeId: 1,
            ptkpStatus: 'TK/0',
            basicSalary: 8_000_000,
            periodWorkdays: 20,
            activeWorkdays: 20,
            month: 12,
            fixedEarnings: [['code' => 'POS_ALW', 'name' => 'Tunjangan Jabatan', 'amount' => 1_000_000, 'taxable' => true, 'bpjsBase' => true]],
            dailyEarnings: [['code' => 'TRANSPORT', 'name' => 'Transport + Makan', 'rate' => 60_000, 'taxable' => true]],
            presentDays: 20,
            isFinalTaxMonth: true,
            yearToDate: ['months' => 11, 'gross' => 116_694_600, 'pension' => 2_970_000, 'pph21' => 2_917_365],
        ));

        $this->assertTrue($december->annualized);
        $this->assertSame(692_085, $december->pph21);
        $this->assertSame(692_085, $december->amountOf('PPH21'));
    }

    public function testOverpaidTaxIsRefundedInFinalMonth(): void
    {
        $result = $this->calculator->calculate(RatesFixture::input(6_000_000, 500_000, 150_000, 'TK/0', [
            'isFinalTaxMonth' => true,
            'yearToDate'      => ['months' => 2, 'gross' => 15_000_000, 'pension' => 400_000, 'pph21' => 300_000],
        ]));

        // Penghasilan 3 bulan jauh di bawah PTKP → seluruh PPh yang dipotong dikembalikan
        $this->assertSame(-300_000, $result->pph21);
        $this->assertSame(300_000, $result->amountOf('PPH21_REFUND'));
        $this->assertSame(0, $result->amountOf('PPH21'));
    }

    public function testArticle17Brackets(): void
    {
        $this->assertSame(0, Pph21Calculator::article17(0));
        $this->assertSame(3_000_000, Pph21Calculator::article17(60_000_000));
        $this->assertSame(31_500_000, Pph21Calculator::article17(250_000_000));
        $this->assertSame(94_000_000, Pph21Calculator::article17(500_000_000));
        $this->assertSame(1_444_000_000, Pph21Calculator::article17(5_000_000_000));
        $this->assertSame(1_479_000_000, Pph21Calculator::article17(5_100_000_000));
    }

    public function testMoneyRoundingIsExact(): void
    {
        $this->assertSame(21_600, Money::percent(9_000_000, 0.24));
        $this->assertSame(3, Money::divRound(5, 2));   // 2,5 → 3 (half-up)
        $this->assertSame(-3, Money::divRound(-5, 2));
        $this->assertSame(64_063_000, Money::floorThousand(64_063_200));
        $this->assertSame(0, Money::floorThousand(-5_000));
    }
}
