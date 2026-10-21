<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Models\PayrollPeriodModel;
use App\Services\AuditLogger;
use CodeIgniter\Database\BaseConnection;

/**
 * Alur status periode payroll:
 *
 *   draft ──submit──▶ review ──approve──▶ approved ──pay──▶ paid ──lock──▶ locked
 *     ▲                  │
 *     └─────reject───────┘ (wajib catatan)
 *
 * Saat dikunci, potongan cicilan pinjaman dibukukan ke loan_installments
 * dan sisa pinjaman diperbarui. Periode terkunci tidak bisa diubah lagi.
 */
class PayrollStateMachine
{
    use ChecksRole;

    /**
     * @var array<string, array{from: string, to: string, roles: list<string>, label: string, stamp: string|null}>
     */
    public const TRANSITIONS = [
        'submit'  => ['from' => 'draft', 'to' => 'review', 'roles' => ['hr'], 'label' => 'Ajukan Review', 'stamp' => 'submitted'],
        'approve' => ['from' => 'review', 'to' => 'approved', 'roles' => ['finance'], 'label' => 'Setujui', 'stamp' => 'approved'],
        'reject'  => ['from' => 'review', 'to' => 'draft', 'roles' => ['finance'], 'label' => 'Tolak', 'stamp' => null],
        'pay'     => ['from' => 'approved', 'to' => 'paid', 'roles' => ['finance'], 'label' => 'Tandai Dibayar', 'stamp' => 'paid'],
        'lock'    => ['from' => 'paid', 'to' => 'locked', 'roles' => ['finance'], 'label' => 'Kunci Periode', 'stamp' => 'locked'],
    ];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditLogger $audit,
    ) {
    }

    public static function make(): self
    {
        return new self(db_connect(), service('auditLogger'));
    }

    /**
     * Aksi yang tersedia untuk periode & pelaku tersebut.
     *
     * @param array<string, mixed> $period
     * @param array<string, mixed> $actor
     *
     * @return list<string>
     */
    public function availableActions(array $period, array $actor): array
    {
        $actions = [];

        foreach (self::TRANSITIONS as $action => $transition) {
            if ($transition['from'] === $period['status'] && self::actorHasRole($actor, ...$transition['roles'])) {
                $actions[] = $action;
            }
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $actor
     *
     * @return array<string, mixed> periode setelah transisi
     */
    public function apply(int $periodId, string $action, array $actor, ?string $note = null): array
    {
        $transition = self::TRANSITIONS[$action] ?? throw new PayrollException("Aksi '{$action}' tidak dikenal.");
        $periods    = new PayrollPeriodModel();
        $period     = $periods->find($periodId) ?? throw new PayrollException('Periode payroll tidak ditemukan.');

        if ($period['status'] !== $transition['from']) {
            throw new PayrollException(sprintf(
                'Aksi "%s" tidak dapat dilakukan: periode %s berstatus %s (harus %s).',
                $transition['label'],
                $period['name'],
                PayrollPeriodModel::STATUSES[$period['status']] ?? $period['status'],
                PayrollPeriodModel::STATUSES[$transition['from']],
            ));
        }

        self::ensureRole($actor, $transition['label'], ...$transition['roles']);

        $note = $note === null ? null : trim($note);

        if ($action === 'reject' && ($note === null || $note === '')) {
            throw new PayrollException('Alasan penolakan wajib diisi.');
        }

        if ($action === 'submit' && $this->db->table('payrolls')->where('period_id', $periodId)->countAllResults() === 0) {
            throw new PayrollException('Payroll belum di-generate. Generate terlebih dahulu sebelum diajukan.');
        }

        $now  = date('Y-m-d H:i:s');
        $data = ['status' => $transition['to'], 'updated_at' => $now];

        if ($transition['stamp'] !== null) {
            $data[$transition['stamp'] . '_by'] = $actor['id'] ?? null;
            $data[$transition['stamp'] . '_at'] = $now;
        }

        if ($action === 'reject') {
            $data['rejection_note'] = $note;
            $data['submitted_by']   = null;
            $data['submitted_at']   = null;
        } elseif ($note !== null && $note !== '') {
            $data['notes'] = $note;
        }

        $this->db->transException(true)->transStart();

        $this->db->table('payroll_periods')->where('id', $periodId)->update($data);

        $installments = $action === 'lock' ? $this->postLoanInstallments($periodId, $now) : 0;

        $this->audit->log(
            'payroll.' . $action,
            'payroll_period',
            $periodId,
            sprintf('%s payroll %s%s', $transition['label'], $period['name'], $note ? ' — ' . $note : '')
                . ($installments > 0 ? " ({$installments} cicilan pinjaman dibukukan)" : ''),
            ['status' => $period['status']],
            ['status' => $transition['to']],
            $actor,
        );

        $this->db->transComplete();

        return $periods->find($periodId);
    }

    /**
     * Bukukan potongan LOAN pada periode ini ke riwayat cicilan dan kurangi sisa pinjaman.
     *
     * @return int jumlah cicilan yang dibukukan
     */
    private function postLoanInstallments(int $periodId, string $now): int
    {
        $period = $this->db->table('payroll_periods')->where('id', $periodId)->get()->getRowArray();
        $key    = sprintf('%04d-%02d', $period['year'], $period['month']);
        $items  = $this->db->table('payroll_items pi')
            ->select("pi.payroll_id, pi.amount, JSON_UNQUOTE(JSON_EXTRACT(pi.meta, '$.loan_id')) AS loan_id", false)
            ->join('payrolls p', 'p.id = pi.payroll_id')
            ->where('p.period_id', $periodId)
            ->where('pi.code', 'LOAN')
            ->get()->getResultArray();

        $paidAt = ($period['paid_at'] ?? null) ?: $now;

        foreach ($items as $item) {
            $loanId = (int) $item['loan_id'];
            $loan   = $this->db->table('loans')->where('id', $loanId)->get()->getRowArray();

            if ($loan === null) {
                continue;
            }

            $installmentNo = $this->db->table('loan_installments')->where('loan_id', $loanId)->countAllResults() + 1;
            $amount        = min((int) $item['amount'], (int) $loan['remaining']);
            $remaining     = (int) $loan['remaining'] - $amount;

            $this->db->table('loan_installments')->insert([
                'loan_id'        => $loanId,
                'payroll_id'     => (int) $item['payroll_id'],
                'installment_no' => $installmentNo,
                'period'         => $key,
                'amount'         => $amount,
                'paid_at'        => $paidAt,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);

            $this->db->table('loans')->where('id', $loanId)->update([
                'remaining'  => $remaining,
                'status'     => $remaining === 0 ? 'paid_off' : 'active',
                'updated_at' => $now,
            ]);
        }

        return count($items);
    }
}
