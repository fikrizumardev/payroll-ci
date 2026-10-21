<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use App\Services\Payroll\PayrollService;
use App\Services\Payroll\PayrollStateMachine;
use App\Services\SettingService;
use CodeIgniter\Database\Seeder;

/**
 * Riwayat payroll Agustus–Oktober 2026, dihitung oleh PayrollService dan
 * dijalankan melalui alur status yang sama dengan aplikasi:
 *
 * - Agustus  : draft → review → approved → paid → locked (cicilan pinjaman dibukukan)
 * - September: draft → review → approved → paid (dengan insentif penjualan)
 * - Oktober  : draft (sudah di-generate, siap untuk demo alur persetujuan)
 *
 * Setelah selesai, cap waktu disesuaikan ke tanggal historis yang realistis.
 */
class PayrollHistorySeeder extends Seeder
{
    /**
     * Jadwal per periode: aksi → waktu historis.
     */
    private const SCHEDULE = [
        8 => [
            'create'   => '2026-08-24 09:00:00',
            'generate' => '2026-08-28 14:10:00',
            'submit'   => '2026-08-28 16:20:00',
            'approve'  => '2026-08-31 09:15:00',
            'pay'      => '2026-08-31 13:30:00',
            'lock'     => '2026-09-01 10:05:00',
        ],
        9 => [
            'create'   => '2026-09-21 09:00:00',
            'generate' => '2026-09-28 14:00:00',
            'submit'   => '2026-09-28 15:45:00',
            'approve'  => '2026-09-29 10:30:00',
            'pay'      => '2026-09-30 13:00:00',
        ],
        10 => [
            'create'   => '2026-10-19 09:00:00',
            'generate' => '2026-10-20 15:30:00',
        ],
    ];

    /** Insentif penjualan Agustus yang dibayarkan di payroll September. */
    private const SEPTEMBER_ADJUSTMENTS = [
        ['EMP-0021', 'Insentif Penjualan Agustus', 1_500_000, 'Pencapaian target 112%'],
        ['EMP-0022', 'Insentif Penjualan Agustus', 1_250_000, 'Pencapaian target 104%'],
        ['EMP-0024', 'Bonus Kampanye Digital', 750_000, 'Kampanye HUT RI ke-81'],
    ];

    private const YEAR = 2026;

    public function run(): void
    {
        $service  = new PayrollService($this->db, new SettingService(), service('auditLogger'));
        $workflow = new PayrollStateMachine($this->db, service('auditLogger'));

        $hr      = $this->user('sinta.maharani');
        $finance = $this->user('budi.santoso');
        $before  = (int) ($this->db->table('audit_logs')->selectMax('id')->get()->getRowArray()['id'] ?? 0);

        foreach (self::SCHEDULE as $month => $steps) {
            $periodId = $service->createPeriod(self::YEAR, $month, $hr);

            if ($month === 9) {
                $this->addAdjustments($periodId, $hr);
            }

            $service->generate($periodId, $hr);

            foreach (['submit' => $hr, 'approve' => $finance, 'pay' => $finance, 'lock' => $finance] as $action => $actor) {
                if (isset($steps[$action])) {
                    $workflow->apply($periodId, $action, $actor);
                }
            }

            $this->backdate($periodId, $steps, $before);
        }
    }

    private function addAdjustments(int $periodId, array $hr): void
    {
        $employees = array_column($this->db->table('employees')->select('id, nip')->get()->getResultArray(), 'id', 'nip');
        $now       = date('Y-m-d H:i:s');

        foreach (self::SEPTEMBER_ADJUSTMENTS as [$nip, $name, $amount, $note]) {
            $this->db->table('payroll_adjustments')->insert([
                'period_id'   => $periodId,
                'employee_id' => $employees[$nip],
                'name'        => $name,
                'type'        => 'earning',
                'amount'      => $amount,
                'taxable'     => 1,
                'note'        => $note,
                'created_by'  => $hr['id'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    /**
     * Ganti cap waktu "sekarang" dengan tanggal historis pada periode, payroll,
     * cicilan pinjaman, dan audit log yang dibuat seeder ini.
     *
     * @param array<string, string> $steps
     */
    private function backdate(int $periodId, array $steps, int $auditIdBefore): void
    {
        $columns = ['created_at' => $steps['create'], 'updated_at' => end($steps)];

        foreach (['generate' => 'generated', 'submit' => 'submitted', 'approve' => 'approved', 'pay' => 'paid', 'lock' => 'locked'] as $action => $stamp) {
            if (isset($steps[$action])) {
                $columns[$stamp . '_at'] = $steps[$action];
            }
        }

        $this->db->table('payroll_periods')->where('id', $periodId)->update($columns);
        $this->db->table('payrolls')->where('period_id', $periodId)->update(['created_at' => $steps['generate'], 'updated_at' => $steps['generate']]);
        $this->db->table('payroll_adjustments')->where('period_id', $periodId)->update(['created_at' => $steps['generate'], 'updated_at' => $steps['generate']]);

        if (isset($steps['lock'])) {
            $payrollIds = array_column($this->db->table('payrolls')->select('id')->where('period_id', $periodId)->get()->getResultArray(), 'id');

            if ($payrollIds !== []) {
                $this->db->table('loan_installments')->whereIn('payroll_id', $payrollIds)
                    ->update(['paid_at' => $steps['pay'], 'created_at' => $steps['lock'], 'updated_at' => $steps['lock']]);

                $loanIds = array_column($this->db->table('loan_installments')->select('loan_id')->whereIn('payroll_id', $payrollIds)->get()->getResultArray(), 'loan_id');

                if ($loanIds !== []) {
                    $this->db->table('loans')->whereIn('id', $loanIds)->update(['updated_at' => $steps['lock']]);
                }
            }
        }

        foreach ($steps as $action => $time) {
            $this->db->table('audit_logs')
                ->where('id >', $auditIdBefore)
                ->where('entity', 'payroll_period')
                ->where('entity_id', (string) $periodId)
                ->where('action', 'payroll.' . $action)
                ->update([
                    'created_at' => $time,
                    // Sinta (HR) dari 10.10.1.14, Budi (Finance) dari 10.10.1.27
                    'ip_address' => in_array($action, ['approve', 'pay', 'lock'], true) ? '10.10.1.27' : '10.10.1.14',
                ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function user(string $username): array
    {
        return $this->db->table('users')->where('username', $username)->get()->getRowArray();
    }
}
