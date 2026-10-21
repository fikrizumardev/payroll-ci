<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmployeeModel;
use App\Models\LoanInstallmentModel;
use App\Models\LoanModel;
use App\Models\PayrollPeriodModel;
use App\Services\Loan\LoanSchedule;
use App\Services\PayrollPeriodGuard;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pinjaman / kasbon karyawan.
 * Lihat: HR & Finance · ajukan/ubah: HR · setujui/tolak: Finance (Super Admin selalu boleh).
 * Cicilan dipotong otomatis saat generate payroll dan dibukukan saat periode dikunci.
 */
class LoanController extends BaseController
{
    public function index(): string
    {
        $status = (string) ($this->request->getGet('status') ?? 'open');
        $status = $status === 'open' || $status === 'all' || array_key_exists($status, LoanModel::STATUSES) ? $status : 'open';
        $q      = trim((string) $this->request->getGet('q'));
        $model  = (new LoanModel())->withRelations();

        if ($status === 'open') {
            $model->whereIn('loans.status', LoanModel::OPEN_STATUSES);
        } elseif ($status !== 'all') {
            $model->where('loans.status', $status);
        }

        if ($q !== '') {
            $model->groupStart()->like('employees.full_name', $q)->orLike('employees.nip', $q)->orLike('loans.loan_no', $q)->groupEnd();
        }

        $loans = $model->orderBy("FIELD(loans.status, 'pending', 'approved', 'active', 'paid_off', 'rejected')", '', false)
            ->orderBy('loans.id', 'DESC')
            ->paginate(25, 'default');

        $counts = array_fill_keys(array_keys(LoanModel::STATUSES), 0);
        $totals = ['outstanding' => 0, 'principal' => 0];

        foreach (db_connect()->table('loans')->select('status, COUNT(*) AS total, SUM(remaining) AS remaining, SUM(amount) AS amount')->groupBy('status')->get()->getResultArray() as $row) {
            $counts[$row['status']] = (int) $row['total'];

            if (in_array($row['status'], [LoanModel::STATUS_APPROVED, LoanModel::STATUS_ACTIVE], true)) {
                $totals['outstanding'] += (int) $row['remaining'];
                $totals['principal'] += (int) $row['amount'];
            }
        }

        return view('loans/index', [
            'title'       => 'Pinjaman Karyawan',
            'breadcrumbs' => [['label' => 'Kehadiran'], ['label' => 'Pinjaman']],
            'loans'       => $loans,
            'pager'       => $model->pager,
            'status'      => $status,
            'q'           => $q,
            'counts'      => $counts,
            'totals'      => $totals,
            'employees'   => $this->employeeOptions(),
            'firstPeriod' => $this->firstOpenPeriod(),
            'canManage'   => $this->canManage(),
            'canApprove'  => $this->auth->hasRole('finance'),
        ]);
    }

    public function show(int $id): string
    {
        $loan = (new LoanModel())->withRelations()->find($id) ?? throw PageNotFoundException::forPageNotFound();

        $installments = (new LoanInstallmentModel())
            ->select('loan_installments.*, payroll_periods.name AS period_name')
            ->join('payrolls', 'payrolls.id = loan_installments.payroll_id', 'left')
            ->join('payroll_periods', 'payroll_periods.id = payrolls.period_id', 'left')
            ->where('loan_id', $id)
            ->orderBy('installment_no')
            ->findAll();

        $deductions = db_connect()->table('payroll_items pi')
            ->select('pi.amount, pp.year, pp.month, pp.status, pp.id AS period_id')
            ->join('payrolls p', 'p.id = pi.payroll_id')
            ->join('payroll_periods pp', 'pp.id = p.period_id')
            ->where('pi.code', 'LOAN')
            ->where("JSON_UNQUOTE(JSON_EXTRACT(pi.meta, '$.loan_id'))", (string) $id)
            ->where('pp.status !=', PayrollPeriodModel::STATUS_LOCKED)
            ->get()->getResultArray();

        $periodIds = [];

        foreach ($deductions as &$deduction) {
            $deduction['period'] = sprintf('%04d-%02d', $deduction['year'], $deduction['month']);
            $periodIds[$deduction['period']] = (int) $deduction['period_id'];
        }
        unset($deduction);

        $schedule = in_array($loan['status'], [LoanModel::STATUS_REJECTED], true)
            ? []
            : LoanSchedule::build($loan, $installments, $deductions);

        return view('loans/show', [
            'title'        => $loan['loan_no'],
            'breadcrumbs'  => [['label' => 'Kehadiran'], ['label' => 'Pinjaman', 'url' => url_to('loans.index')], ['label' => $loan['loan_no']]],
            'loan'         => $loan,
            'installments' => $installments,
            'schedule'     => $schedule,
            'periodIds'    => $periodIds,
            'employees'    => $this->employeeOptions($loan['employee_id']),
            'firstPeriod'  => $this->firstOpenPeriod(),
            'canManage'    => $this->canManage(),
            'canApprove'   => $this->auth->hasRole('finance'),
        ]);
    }

    public function store(): RedirectResponse
    {
        $data = $this->payload();

        if (($errors = $this->validateLoan($data)) !== []) {
            return $this->backWithErrors(url_to('loans.index'), $errors, 'create');
        }

        $model = new LoanModel();
        $data += [
            'loan_no'   => $model->nextLoanNo((int) date('Y')),
            'remaining' => $data['amount'],
            'status'    => LoanModel::STATUS_PENDING,
        ];

        if (! $model->insert($data)) {
            return $this->backWithErrors(url_to('loans.index'), $model->errors(), 'create');
        }

        $id   = (int) $model->getInsertID();
        $name = $this->employeeName($data['employee_id']);
        $this->audit('loan.create', 'loan', $id, "Mengajukan pinjaman {$data['loan_no']} untuk {$name} sebesar " . rupiah($data['amount']), null, $data);

        return redirect()->route('loans.show', [$id])->with('success', "Pinjaman {$data['loan_no']} untuk {$name} diajukan dan menunggu persetujuan Finance.");
    }

    public function update(int $id): RedirectResponse
    {
        $model  = new LoanModel();
        $before = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $back   = url_to('loans.show', $id);

        if ($before['status'] !== LoanModel::STATUS_PENDING) {
            return redirect()->to($back)->with('error', 'Hanya pinjaman yang menunggu persetujuan yang dapat diubah.');
        }

        $data = $this->payload();

        if (($errors = $this->validateLoan($data, $id)) !== []) {
            return $this->backWithErrors($back, $errors, 'edit:' . $id);
        }

        $data += ['id' => $id, 'loan_no' => $before['loan_no'], 'remaining' => $data['amount'], 'status' => LoanModel::STATUS_PENDING];

        if (! $model->update($id, $data)) {
            return $this->backWithErrors($back, $model->errors(), 'edit:' . $id);
        }

        [$old, $new] = $this->diff($before, $data);

        if ($new !== []) {
            $this->audit('loan.update', 'loan', $id, "Memperbarui pengajuan pinjaman {$before['loan_no']}", $old, $new);
        }

        return redirect()->to($back)->with('success', 'Pengajuan pinjaman berhasil diperbarui.');
    }

    public function delete(int $id): RedirectResponse
    {
        $model = new LoanModel();
        $loan  = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();

        if (! in_array($loan['status'], [LoanModel::STATUS_PENDING, LoanModel::STATUS_REJECTED], true)) {
            return redirect()->route('loans.show', [$id])->with('error', 'Pinjaman yang sudah disetujui tidak dapat dihapus.');
        }

        $model->delete($id);
        $this->audit('loan.delete', 'loan', $id, "Menghapus pengajuan pinjaman {$loan['loan_no']}", $loan);

        return redirect()->route('loans.index')->with('success', "Pengajuan pinjaman {$loan['loan_no']} dihapus.");
    }

    public function approve(int $id): RedirectResponse
    {
        return $this->review($id, 'approve');
    }

    public function reject(int $id): RedirectResponse
    {
        return $this->review($id, 'reject');
    }

    // ------------------------------------------------------------------

    private function review(int $id, string $action): RedirectResponse
    {
        $model = new LoanModel();
        $loan  = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $back  = url_to('loans.show', $id);
        $note  = trim((string) $this->request->getPost('note'));

        if ($loan['status'] !== LoanModel::STATUS_PENDING) {
            return redirect()->to($back)->with('error', 'Pinjaman ini sudah ' . strtolower(LoanModel::STATUSES[$loan['status']]) . '.');
        }

        if ($action === 'reject' && $note === '') {
            return redirect()->to($back)->with('error', 'Alasan penolakan wajib diisi.');
        }

        $status  = $action === 'approve' ? LoanModel::STATUS_APPROVED : LoanModel::STATUS_REJECTED;
        $user    = $this->currentUser();
        $changes = [
            'status'      => $status,
            'approved_by' => $user['id'] ?? null,
            'approved_at' => date('Y-m-d H:i:s'),
            'review_note' => $note === '' ? null : mb_substr($note, 0, 255),
        ];

        // Pengajuan lama yang bulan mulai potongnya sudah lewat/final digeser ke bulan terbuka pertama
        if ($action === 'approve' && $loan['start_period'] < ($first = $this->firstOpenPeriod())) {
            $changes['start_period'] = $first;
            $loan['start_period']    = $first;
        }

        $model->update($id, $changes);

        $name = $this->employeeName($loan['employee_id']);
        $this->audit('loan.' . $action, 'loan', $id, sprintf(
            '%s pinjaman %s %s (%s)%s',
            $action === 'approve' ? 'Menyetujui' : 'Menolak',
            $loan['loan_no'],
            $name,
            rupiah($loan['amount']),
            $note !== '' ? ' — ' . $note : '',
        ), ['status' => $loan['status']], ['status' => $status]);

        $redirect = redirect()->to($back);

        if ($action === 'approve') {
            $start = $loan['start_period'] . '-01';
            $redirect->with('warning', $this->regenerateHint([$start], 'cicilan pinjaman ini'));

            return $redirect->with('success', "Pinjaman {$loan['loan_no']} disetujui. Cicilan " . rupiah($loan['installment']) . ' mulai dipotong pada payroll ' . periode_id((int) substr($loan['start_period'], 0, 4), (int) substr($loan['start_period'], 5, 2)) . '.');
        }

        return $redirect->with('success', "Pinjaman {$loan['loan_no']} ditolak.");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $amount = (int) preg_replace('/\D/', '', (string) $this->request->getPost('amount'));
        $tenor  = (int) $this->request->getPost('tenor_months');

        return [
            'employee_id'  => (int) $this->request->getPost('employee_id'),
            'amount'       => $amount,
            'tenor_months' => $tenor,
            'installment'  => LoanModel::installmentFor($amount, max(1, $tenor)),
            'start_period' => trim((string) $this->request->getPost('start_period')),
            'purpose'      => trim(preg_replace('/\s+/', ' ', (string) $this->request->getPost('purpose'))),
            'notes'        => trim((string) $this->request->getPost('notes')) ?: null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function validateLoan(array $data, ?int $ignoreId = null): array
    {
        $model = new LoanModel();

        if (! $model->validate($data + ['loan_no' => 'PJM-CHECK', 'remaining' => $data['amount'], 'status' => LoanModel::STATUS_PENDING])) {
            return array_diff_key($model->errors(), ['loan_no' => true]);
        }

        $employee = (new EmployeeModel())->find($data['employee_id']);

        if ($employee === null || $employee['status'] !== EmployeeModel::STATUS_ACTIVE) {
            return ['employee_id' => 'Pinjaman hanya dapat diajukan untuk karyawan aktif.'];
        }

        $open = $model->where('employee_id', $data['employee_id'])->whereIn('status', LoanModel::OPEN_STATUSES);

        if ($ignoreId !== null) {
            $open->where('id !=', $ignoreId);
        }

        if (($existing = $open->first()) !== null) {
            return ['employee_id' => "{$employee['full_name']} masih memiliki pinjaman {$existing['loan_no']} (" . strtolower(LoanModel::STATUSES[$existing['status']]) . ').'];
        }

        $limit = (int) floor($employee['basic_salary'] * LoanModel::MAX_INSTALLMENT_RATIO);

        if ($data['installment'] > $limit) {
            return ['tenor_months' => 'Cicilan ' . rupiah($data['installment']) . ' melebihi 50% gaji pokok (' . rupiah($limit) . '). Perpanjang tenor atau kurangi nominal.'];
        }

        if ($data['start_period'] < $this->firstOpenPeriod()) {
            return ['start_period' => 'Mulai potong paling cepat ' . $this->periodLabel($this->firstOpenPeriod()) . ' (payroll bulan sebelumnya sudah lewat/final).'];
        }

        return [];
    }

    /**
     * Bulan paling awal yang masih bisa dipotong: bulan berjalan, atau sesudah periode payroll final terakhir.
     */
    private function firstOpenPeriod(): string
    {
        $first = date('Y-m');

        foreach (PayrollPeriodGuard::make()->closedMonths() as $month) {
            if ($month >= $first) {
                $first = LoanSchedule::nextMonth($month);
            }
        }

        return $first;
    }

    private function periodLabel(string $period): string
    {
        return periode_id((int) substr($period, 0, 4), (int) substr($period, 5, 2));
    }

    /**
     * Karyawan aktif + gaji pokok (untuk simulasi rasio cicilan di form).
     *
     * @return array<int, array{label: string, salary: int}>
     */
    private function employeeOptions(?int $include = null): array
    {
        $model = (new EmployeeModel())->withRelations();

        if ($include !== null) {
            $model->groupStart()->where('employees.status', EmployeeModel::STATUS_ACTIVE)->orWhere('employees.id', $include)->groupEnd();
        } else {
            $model->active();
        }

        $options = [];

        foreach ($model->orderBy('employees.full_name')->findAll() as $employee) {
            $options[(int) $employee['id']] = ['label' => $employee['full_name'] . ' · ' . $employee['nip'], 'salary' => (int) $employee['basic_salary']];
        }

        return $options;
    }

    private function employeeName(int $employeeId): string
    {
        return (string) ((new EmployeeModel())->withDeleted()->find($employeeId)['full_name'] ?? '#' . $employeeId);
    }
}
