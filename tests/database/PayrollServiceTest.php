<?php

declare(strict_types=1);

namespace Tests\Database;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\PayrollPeriodModel;
use App\Models\UserModel;
use App\Services\Payroll\PayrollException;
use App\Services\Payroll\PayrollService;
use App\Services\Payroll\PayrollStateMachine;
use App\Services\SettingService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Generate payroll & alur status dengan data seed lengkap.
 *
 * @internal
 */
final class PayrollServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = DatabaseSeeder::class;
    protected $seedOnce    = true;

    private PayrollService $service;
    private PayrollStateMachine $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service  = new PayrollService($this->db, new SettingService(), service('auditLogger'));
        $this->workflow = new PayrollStateMachine($this->db, service('auditLogger'));
    }

    public function testSeededHistoryFollowsWorkflow(): void
    {
        $periods = array_column((new PayrollPeriodModel())->orderBy('month')->findAll(), 'status', 'month');

        $this->assertSame([8 => 'locked', 9 => 'paid', 10 => 'draft'], $periods);
        $this->assertSame(31, $this->payrollCount(8));
        $this->assertSame(31, $this->payrollCount(9));
        $this->assertSame(30, $this->payrollCount(10)); // Hadi sudah resign
    }

    public function testRegenerateIsIdempotentAndFast(): void
    {
        $october = $this->period(10);
        $before  = $this->totals($october['id']);

        $summary = $this->service->generate($october['id'], $this->user('sinta.maharani'));

        $this->assertSame(30, $summary['employees']);
        $this->assertSame($before, $this->totals($october['id']));
        $this->assertLessThan(2000, $summary['duration_ms'], 'Generate 30 karyawan harus < 2 detik');
        $this->assertNotEmpty($summary['warnings']); // kehadiran Oktober belum lengkap
    }

    public function testHeaderTotalsMatchLineItems(): void
    {
        $mismatch = $this->db->query(<<<'SQL'
            SELECT p.id
            FROM payrolls p
            JOIN payroll_items pi ON pi.payroll_id = p.id
            GROUP BY p.id, p.total_earning, p.total_deduction, p.take_home_pay
            HAVING SUM(CASE WHEN pi.type = 'earning' THEN pi.amount ELSE 0 END) <> p.total_earning
                OR SUM(CASE WHEN pi.type = 'deduction' THEN pi.amount ELSE 0 END) <> p.total_deduction
                OR p.total_earning - p.total_deduction <> p.take_home_pay
            SQL)->getResultArray();

        $this->assertSame([], $mismatch);
    }

    public function testSeedScenarios(): void
    {
        // Anisa masuk 10 Agu: prorata 14/19 hari kerja
        $anisa = $this->payrollOf('EMP-0006', 8);
        $this->assertSame(14, (int) $anisa['active_workdays']);
        $this->assertSame(19, (int) $anisa['workdays']);
        $this->assertSame(5_157_895, $this->itemAmount($anisa['id'], 'BASIC'));

        // Hadi resign 15 Sep: prorata 11/22 dan PPh dihitung setahun (tanpa tarif TER)
        $hadi = $this->payrollOf('EMP-0031', 9);
        $this->assertSame(6_500_000, $this->itemAmount($hadi['id'], 'BASIC'));
        $this->assertNull($hadi['ter_rate']);

        // Galih 1 hari alpha di September
        $galih = $this->payrollOf('EMP-0015', 9);
        $this->assertSame(1, (int) $galih['absent_days']);
        $this->assertSame(522_727, $this->itemAmount($galih['id'], 'ABSENT_DED'));

        // Insentif penjualan September
        $this->assertSame(1_500_000, $this->itemAmount($this->payrollOf('EMP-0021', 9)['id'], 'ADJ'));
    }

    public function testLockPostsLoanInstallments(): void
    {
        // Agustus dikunci saat seeding: cicilan Kevin (#2) & Rizky (#1) tercatat
        $this->seeInDatabase('loans', ['loan_no' => 'PJM-2026-0002', 'remaining' => 10_000_000, 'status' => 'active']);
        $this->seeInDatabase('loans', ['loan_no' => 'PJM-2026-0003', 'remaining' => 5_000_000, 'status' => 'active']);
        $this->seeInDatabase('loan_installments', ['period' => '2026-08', 'installment_no' => 2, 'amount' => 1_000_000]);

        // September (belum terkunci) dan Oktober tetap memotong cicilan, tanpa melebihi sisa
        $this->assertSame(1_000_000, $this->itemAmount($this->payrollOf('EMP-0013', 10)['id'], 'LOAN'));
    }

    public function testFullWorkflowForNewPeriod(): void
    {
        $hr      = $this->user('sinta.maharani');
        $finance = $this->user('budi.santoso');

        $id = $this->service->createPeriod(2026, 11, $hr);
        $this->seeInDatabase('payroll_periods', ['id' => $id, 'name' => 'November 2026', 'pay_date' => '2026-11-30', 'status' => 'draft']);

        $this->service->generate($id, $hr);

        $this->assertSame(['submit'], $this->workflow->availableActions($this->period(11), $hr));
        $this->workflow->apply($id, 'submit', $hr);

        $this->workflow->apply($id, 'reject', $finance, 'Lembur Engineering perlu dicek');
        $this->seeInDatabase('payroll_periods', ['id' => $id, 'status' => 'draft', 'rejection_note' => 'Lembur Engineering perlu dicek']);

        $this->workflow->apply($id, 'submit', $hr);
        $this->workflow->apply($id, 'approve', $finance);
        $this->workflow->apply($id, 'pay', $finance);
        $locked = $this->workflow->apply($id, 'lock', $finance);

        $this->assertSame('locked', $locked['status']);
        $this->assertNotNull($locked['locked_at']);
        $this->seeInDatabase('loan_installments', ['period' => '2026-11']);
        $this->seeInDatabase('audit_logs', ['action' => 'payroll.lock', 'entity_id' => (string) $id]);
    }

    public function testIllegalTransitionsAreRejected(): void
    {
        $hr      = $this->user('sinta.maharani');
        $finance = $this->user('budi.santoso');
        $october = $this->period(10);

        $this->assertPayrollException(fn () => $this->workflow->apply($october['id'], 'approve', $finance), 'harus Review');
        $this->assertPayrollException(fn () => $this->workflow->apply($october['id'], 'lock', $finance), 'harus Dibayar');
        $this->assertPayrollException(fn () => $this->service->generate($this->period(8)['id'], $hr), 'Draft');
        $this->assertPayrollException(fn () => $this->service->generate($october['id'], $finance), 'HR');
        $this->assertPayrollException(fn () => $this->service->createPeriod(2026, 10, $hr), 'sudah ada');

        $this->workflow->apply($october['id'], 'submit', $hr);
        $this->assertPayrollException(fn () => $this->workflow->apply($october['id'], 'approve', $hr), 'Finance');
        $this->assertPayrollException(fn () => $this->workflow->apply($october['id'], 'reject', $finance, '  '), 'Alasan penolakan');

        $employee = $this->user('andi.pratama');
        $this->assertSame([], $this->workflow->availableActions($this->period(10), $employee));
    }

    public function testSubmitRequiresGeneratedPayroll(): void
    {
        $hr = $this->user('sinta.maharani');
        $id = $this->service->createPeriod(2026, 12, $hr);

        $this->assertPayrollException(fn () => $this->workflow->apply($id, 'submit', $hr), 'belum di-generate');
    }

    private function assertPayrollException(callable $callback, string $messagePart): void
    {
        try {
            $callback();
            $this->fail('PayrollException tidak dilempar.');
        } catch (PayrollException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function user(string $username): array
    {
        return (new UserModel())->findByUsername($username);
    }

    /**
     * @return array<string, mixed>
     */
    private function period(int $month): array
    {
        return (new PayrollPeriodModel())->where(['year' => 2026, 'month' => $month])->first();
    }

    private function payrollCount(int $month): int
    {
        return $this->db->table('payrolls')->where('period_id', $this->period($month)['id'])->countAllResults();
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollOf(string $nip, int $month): array
    {
        return $this->db->table('payrolls p')->select('p.*')
            ->join('employees e', 'e.id = p.employee_id')
            ->where('e.nip', $nip)
            ->where('p.period_id', $this->period($month)['id'])
            ->get()->getRowArray();
    }

    private function itemAmount(int|string $payrollId, string $code): int
    {
        return (int) ($this->db->table('payroll_items')->selectSum('amount')->where(['payroll_id' => $payrollId, 'code' => $code])->get()->getRowArray()['amount'] ?? 0);
    }

    /**
     * @return array<string, int>
     */
    private function totals(int $periodId): array
    {
        $row = $this->db->table('payrolls')
            ->select('COUNT(*) AS n, SUM(total_earning) AS earning, SUM(total_deduction) AS deduction, SUM(pph21) AS pph21, SUM(take_home_pay) AS thp')
            ->where('period_id', $periodId)
            ->get()->getRowArray();

        return array_map('intval', $row);
    }
}
