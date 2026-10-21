<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PayrollAdjustmentModel;
use App\Models\PayrollItemModel;
use App\Models\PayrollModel;
use App\Models\PayrollPeriodModel;
use App\Models\UserModel;
use App\Services\Export\BankTransferExporter;
use App\Services\Payroll\PayrollException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;

/**
 * Proses payroll: periode, generate, review, persetujuan, penyesuaian manual, dan ekspor bank.
 * Lihat: HR & Finance · generate/ajukan/penyesuaian: HR · setujui/bayar/kunci/ekspor: Finance.
 * Aturan status & peran ditegakkan oleh PayrollService dan PayrollStateMachine.
 */
class PayrollController extends BaseController
{
    /** Status periode yang hasilnya sudah final dan boleh diekspor ke bank. */
    private const EXPORTABLE = [PayrollPeriodModel::STATUS_APPROVED, PayrollPeriodModel::STATUS_PAID, PayrollPeriodModel::STATUS_LOCKED];

    // ------------------------------------------------------------------
    // Daftar & pembuatan periode
    // ------------------------------------------------------------------

    public function index(): string
    {
        $periods = (new PayrollPeriodModel())->orderBy('year', 'DESC')->orderBy('month', 'DESC')->findAll();
        $stats   = [];

        $rows = db_connect()->table('payrolls')
            ->select('period_id, COUNT(*) AS employees, SUM(take_home_pay) AS thp, SUM(total_earning) AS gross, SUM(pph21) AS pph21')
            ->select('SUM(bpjs_company_total) AS company, SUM(CASE WHEN warnings IS NULL THEN 0 ELSE 1 END) AS warned', false)
            ->groupBy('period_id')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $stats[(int) $row['period_id']] = array_map('intval', $row);
        }

        // Bulan default periode baru: bulan setelah periode terakhir (atau bulan berjalan)
        $latest  = $periods[0] ?? null;
        $default = $latest === null
            ? new DateTimeImmutable(date('Y-m-01'))
            : (new DateTimeImmutable(sprintf('%04d-%02d-01', $latest['year'], $latest['month'])))->modify('+1 month');

        $payDates = [];

        for ($i = -6; $i <= 12; $i++) {
            $month = $default->modify("{$i} month");
            $payDates[$month->format('Y-m')] = service('payroll')->payDate((int) $month->format('Y'), (int) $month->format('n'));
        }

        return view('payroll/index', [
            'title'        => 'Periode Payroll',
            'breadcrumbs'  => [['label' => 'Payroll'], ['label' => 'Periode Payroll']],
            'periods'      => $periods,
            'stats'        => $stats,
            'defaultMonth' => $default->format('Y-m'),
            'payDates'     => $payDates,
            'existing'     => array_map(static fn (array $p): string => sprintf('%04d-%02d', $p['year'], $p['month']), $periods),
            'canCreate'    => $this->auth->hasRole('hr'),
        ]);
    }

    public function store(): RedirectResponse
    {
        $month   = (string) $this->request->getPost('month');
        $payDate = trim((string) $this->request->getPost('pay_date'));
        $notes   = trim((string) $this->request->getPost('notes'));

        if (! preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $m)) {
            return $this->backWithErrors(url_to('payroll.index'), ['month' => 'Pilih bulan periode.'], 'create');
        }

        try {
            $id = service('payroll')->createPeriod((int) $m[1], (int) $m[2], $this->currentUser(), $notes === '' ? null : mb_substr($notes, 0, 500), $payDate === '' ? null : $payDate);
        } catch (PayrollException $e) {
            return $this->backWithErrors(url_to('payroll.index'), [str_contains($e->getMessage(), 'Tanggal bayar') ? 'pay_date' : 'month' => $e->getMessage()], 'create');
        }

        return redirect()->route('payroll.show', [$id])->with('success', 'Periode ' . periode_id((int) $m[1], (int) $m[2]) . ' dibuat. Klik Generate untuk menghitung gaji seluruh karyawan.');
    }

    // ------------------------------------------------------------------
    // Detail / review periode
    // ------------------------------------------------------------------

    public function show(int $id): string
    {
        $period   = $this->period($id);
        $user     = $this->currentUser();
        $payrolls = (new PayrollModel())->withEmployee()->where('payrolls.period_id', $id)->orderBy('employees.nip')->findAll();

        $totals = ['employees' => count($payrolls), 'gross' => 0, 'deduction' => 0, 'pph21' => 0, 'company' => 0, 'bpjs_employee' => 0, 'thp' => 0, 'warned' => 0];

        foreach ($payrolls as $payroll) {
            $totals['gross'] += $payroll['total_earning'];
            $totals['deduction'] += $payroll['total_deduction'];
            $totals['pph21'] += $payroll['pph21'];
            $totals['company'] += $payroll['bpjs_company_total'];
            $totals['bpjs_employee'] += $payroll['bpjs_employee_total'];
            $totals['thp'] += $payroll['take_home_pay'];
            $totals['warned'] += empty($payroll['warnings']) ? 0 : 1;
        }

        $adjustments = (new PayrollAdjustmentModel())
            ->select('payroll_adjustments.*, employees.full_name, employees.nip, users.name AS creator_name')
            ->join('employees', 'employees.id = payroll_adjustments.employee_id')
            ->join('users', 'users.id = payroll_adjustments.created_by', 'left')
            ->where('payroll_adjustments.period_id', $id)
            ->orderBy('payroll_adjustments.id')
            ->findAll();

        $canExport = in_array($period['status'], self::EXPORTABLE, true) && $this->auth->hasRole('finance');

        return view('payroll/show', [
            'title'       => 'Payroll ' . $period['name'],
            'breadcrumbs' => [['label' => 'Payroll'], ['label' => 'Periode Payroll', 'url' => url_to('payroll.index')], ['label' => $period['name']]],
            'period'      => $period,
            'payrolls'    => $payrolls,
            'totals'      => $totals,
            'adjustments' => $adjustments,
            'actors'      => $this->actorNames($period),
            'actions'     => service('payrollWorkflow')->availableActions($period, $user),
            'canGenerate' => $period['status'] === PayrollPeriodModel::STATUS_DRAFT && $this->auth->hasRole('hr'),
            'canExport'   => $canExport,
            'bank'        => $canExport ? BankTransferExporter::summary(BankTransferExporter::make()->recipients($id)) : null,
            'departments' => array_values(array_unique(array_filter(array_map(static fn (array $p): ?string => $p['snapshot']['department'] ?? null, $payrolls)))),
        ]);
    }

    public function generate(int $id): RedirectResponse
    {
        $period = $this->period($id);
        $back   = url_to('payroll.show', $id);

        try {
            $summary = service('payroll')->generate($id, $this->currentUser());
        } catch (PayrollException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $redirect = redirect()->to($back)->with('success', sprintf(
            'Payroll %s di-generate: %d karyawan, total take home pay %s (%s detik).',
            $period['name'],
            $summary['employees'],
            rupiah($summary['take_home_pay']),
            number_id($summary['duration_ms'] / 1000, 1),
        ));

        if ($summary['warnings'] !== []) {
            $redirect->with('warning', count($summary['warnings']) . ' karyawan memiliki peringatan — periksa baris yang disorot sebelum mengajukan review.');
        }

        return $redirect;
    }

    public function submit(int $id): RedirectResponse
    {
        return $this->transition($id, 'submit');
    }

    public function approve(int $id): RedirectResponse
    {
        return $this->transition($id, 'approve');
    }

    public function reject(int $id): RedirectResponse
    {
        return $this->transition($id, 'reject');
    }

    public function pay(int $id): RedirectResponse
    {
        return $this->transition($id, 'pay');
    }

    public function lock(int $id): RedirectResponse
    {
        return $this->transition($id, 'lock');
    }

    // ------------------------------------------------------------------
    // Rincian gaji karyawan & penyesuaian manual
    // ------------------------------------------------------------------

    public function employee(int $periodId, int $employeeId): string
    {
        $period  = $this->period($periodId);
        $payroll = (new PayrollModel())->where(['period_id' => $periodId, 'employee_id' => $employeeId])->first()
            ?? throw PageNotFoundException::forPageNotFound('Karyawan ini tidak termasuk payroll ' . $period['name'] . '.');

        $order = array_map('intval', array_column(
            db_connect()->table('payrolls p')->select('p.employee_id')->join('employees e', 'e.id = p.employee_id')
                ->where('p.period_id', $periodId)->orderBy('e.nip')->get()->getResultArray(),
            'employee_id',
        ));
        $position = array_search($employeeId, $order, true);

        $adjustments = (new PayrollAdjustmentModel())
            ->select('payroll_adjustments.*, users.name AS creator_name')
            ->join('users', 'users.id = payroll_adjustments.created_by', 'left')
            ->where(['payroll_adjustments.period_id' => $periodId, 'payroll_adjustments.employee_id' => $employeeId])
            ->orderBy('payroll_adjustments.id')
            ->findAll();

        $name = $payroll['snapshot']['full_name'] ?? 'Karyawan';

        return view('payroll/employee', [
            'title'       => $name . ' · ' . $period['name'],
            'breadcrumbs' => [['label' => 'Payroll'], ['label' => $period['name'], 'url' => url_to('payroll.show', $periodId)], ['label' => $name]],
            'period'      => $period,
            'payroll'     => $payroll,
            'items'       => (new PayrollItemModel())->groupedForPayroll((int) $payroll['id']),
            'adjustments' => $adjustments,
            'ptkp'        => service('payroll')->rates()->ptkpAmount($payroll['snapshot']['ptkp_status'] ?? 'TK/0'),
            'prevId'      => $position > 0 ? $order[$position - 1] : null,
            'nextId'      => $position !== false && $position < count($order) - 1 ? $order[$position + 1] : null,
            'position'    => $position === false ? null : $position + 1,
            'count'       => count($order),
            'canAdjust'   => $period['status'] === PayrollPeriodModel::STATUS_DRAFT && $this->auth->hasRole('hr'),
        ]);
    }

    public function storeAdjustment(int $periodId, int $employeeId): RedirectResponse
    {
        $period = $this->period($periodId);
        $back   = url_to('payroll.employee', $periodId, $employeeId);

        if ($period['status'] !== PayrollPeriodModel::STATUS_DRAFT) {
            return redirect()->to($back)->with('error', 'Penyesuaian hanya dapat ditambahkan saat periode berstatus Draft.');
        }

        if ((new PayrollModel())->where(['period_id' => $periodId, 'employee_id' => $employeeId])->countAllResults() === 0) {
            return redirect()->to($back)->with('error', 'Karyawan ini tidak termasuk payroll periode ini.');
        }

        $user = $this->currentUser();
        $data = [
            'period_id'   => $periodId,
            'employee_id' => $employeeId,
            'name'        => trim(preg_replace('/\s+/', ' ', (string) $this->request->getPost('name'))),
            'type'        => (string) $this->request->getPost('type'),
            'amount'      => (int) preg_replace('/\D/', '', (string) $this->request->getPost('amount')),
            'taxable'     => $this->request->getPost('taxable') ? 1 : 0,
            'note'        => trim((string) $this->request->getPost('note')) ?: null,
            'created_by'  => $user['id'] ?? null,
        ];

        $model = new PayrollAdjustmentModel();

        if (! $model->insert($data)) {
            return $this->backWithErrors($back, $model->errors(), 'adjustment');
        }

        $adjustmentId = (int) $model->getInsertID();
        $label        = ($data['type'] === 'earning' ? 'pendapatan' : 'potongan') . " {$data['name']} " . rupiah($data['amount']);
        $this->audit('payroll.adjustment.create', 'payroll_period', $periodId, "Menambahkan penyesuaian {$label} untuk " . $this->employeeName($periodId, $employeeId) . " ({$period['name']})", null, $data + ['id' => $adjustmentId]);

        return $this->regenerateAfterAdjustment($periodId, $employeeId, 'Penyesuaian ' . $label . ' ditambahkan.');
    }

    public function deleteAdjustment(int $periodId, int $adjustmentId): RedirectResponse
    {
        $period     = $this->period($periodId);
        $model      = new PayrollAdjustmentModel();
        $adjustment = $model->where(['id' => $adjustmentId, 'period_id' => $periodId])->first() ?? throw PageNotFoundException::forPageNotFound();
        $back       = url_to('payroll.employee', $periodId, $adjustment['employee_id']);

        if ($period['status'] !== PayrollPeriodModel::STATUS_DRAFT) {
            return redirect()->to($back)->with('error', 'Penyesuaian hanya dapat dihapus saat periode berstatus Draft.');
        }

        $model->delete($adjustmentId);
        $this->audit('payroll.adjustment.delete', 'payroll_period', $periodId, "Menghapus penyesuaian {$adjustment['name']} " . rupiah($adjustment['amount']) . ' untuk ' . $this->employeeName($periodId, $adjustment['employee_id']) . " ({$period['name']})", $adjustment);

        return $this->regenerateAfterAdjustment($periodId, $adjustment['employee_id'], "Penyesuaian {$adjustment['name']} dihapus.");
    }

    // ------------------------------------------------------------------
    // Ekspor transfer bank
    // ------------------------------------------------------------------

    public function exportBank(int $id): ResponseInterface
    {
        $period = $this->period($id);

        if (! in_array($period['status'], self::EXPORTABLE, true)) {
            return redirect()->route('payroll.show', [$id])->with('error', 'File transfer bank tersedia setelah payroll disetujui.');
        }

        $bank       = trim((string) $this->request->getGet('bank')) ?: null;
        $recipients = BankTransferExporter::make()->recipients($id);
        $summary    = BankTransferExporter::summary($recipients);

        if ($bank !== null && ! array_key_exists($bank, $summary['banks'])) {
            return redirect()->route('payroll.show', [$id])->with('error', "Tidak ada penerima dengan bank {$bank} pada periode ini.");
        }

        $count = $bank === null ? array_sum(array_column($summary['banks'], 'count')) : $summary['banks'][$bank]['count'];
        $total = $bank === null ? array_sum(array_column($summary['banks'], 'total')) : $summary['banks'][$bank]['total'];

        $this->audit('payroll.export_bank', 'payroll_period', $id, sprintf(
            'Mengekspor file transfer %s payroll %s: %d penerima, %s',
            $bank ?? 'semua bank',
            $period['name'],
            $count,
            rupiah($total),
        ));

        $filename = sprintf('transfer-gaji-%04d-%02d-%s.csv', $period['year'], $period['month'], $bank === null ? 'semua-bank' : strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $bank)));

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody(BankTransferExporter::csv($recipients, $period, $bank));
    }

    // ------------------------------------------------------------------

    private function transition(int $id, string $action): RedirectResponse
    {
        $period = $this->period($id);
        $back   = url_to('payroll.show', $id);

        try {
            service('payrollWorkflow')->apply($id, $action, $this->currentUser(), $this->request->getPost('note'));
        } catch (PayrollException $e) {
            return redirect()->to($back)->with('error', $e->getMessage());
        }

        $messages = [
            'submit'  => "Payroll {$period['name']} diajukan untuk review Finance.",
            'approve' => "Payroll {$period['name']} disetujui. Ekspor file transfer bank lalu tandai dibayar.",
            'reject'  => "Payroll {$period['name']} dikembalikan ke HR (Draft) beserta catatan penolakan.",
            'pay'     => "Payroll {$period['name']} ditandai sudah dibayar.",
            'lock'    => "Payroll {$period['name']} dikunci. Data periode kini read-only dan cicilan pinjaman sudah dibukukan.",
        ];

        return redirect()->to($back)->with('success', $messages[$action]);
    }

    private function regenerateAfterAdjustment(int $periodId, int $employeeId, string $message): RedirectResponse
    {
        $back = url_to('payroll.employee', $periodId, $employeeId);

        try {
            service('payroll')->generate($periodId, $this->currentUser());
        } catch (PayrollException $e) {
            return redirect()->to($back)->with('warning', $message . ' Generate ulang gagal: ' . $e->getMessage());
        }

        $thp = (new PayrollModel())->where(['period_id' => $periodId, 'employee_id' => $employeeId])->first()['take_home_pay'] ?? 0;

        return redirect()->to($back)->with('success', $message . ' Payroll di-generate ulang; take home pay kini ' . rupiah($thp) . '.');
    }

    /**
     * @return array<string, mixed>
     */
    private function period(int $id): array
    {
        return (new PayrollPeriodModel())->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }

    /**
     * Nama pelaku tiap tahap (dibuat, generate, ajukan, setujui, bayar, kunci).
     *
     * @param array<string, mixed> $period
     *
     * @return array<string, string|null>
     */
    private function actorNames(array $period): array
    {
        $stamps = ['created', 'generated', 'submitted', 'approved', 'paid', 'locked'];
        $ids    = array_filter(array_map(static fn (string $s): ?int => isset($period[$s . '_by']) ? (int) $period[$s . '_by'] : null, $stamps));
        $names  = $ids === [] ? [] : array_column((new UserModel())->select('id, name')->whereIn('id', array_values($ids))->findAll(), 'name', 'id');
        $result = [];

        foreach ($stamps as $stamp) {
            $result[$stamp] = isset($period[$stamp . '_by']) ? ($names[(int) $period[$stamp . '_by']] ?? null) : null;
        }

        return $result;
    }

    private function employeeName(int $periodId, int $employeeId): string
    {
        $payroll = (new PayrollModel())->where(['period_id' => $periodId, 'employee_id' => $employeeId])->first();

        return (string) ($payroll['snapshot']['full_name'] ?? '#' . $employeeId);
    }
}
