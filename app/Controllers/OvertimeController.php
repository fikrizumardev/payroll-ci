<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\EmployeeModel;
use App\Models\OvertimeModel;
use App\Services\Payroll\OvertimeCalculator;
use App\Services\Attendance\AttendanceService;
use App\Services\PayrollPeriodGuard;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Pengajuan & persetujuan lembur. Lihat: HR & Finance · kelola & setujui: HR.
 * Durasi dihitung otomatis dari jam mulai–selesai; upah lembur dihitung engine payroll.
 */
class OvertimeController extends BaseController
{
    public function index(): string
    {
        $filters = $this->filters();
        [$start, $end] = $this->monthRange($filters['month']);

        $model = $this->filteredQuery($filters, $start, $end);

        if ($filters['status'] !== 'all') {
            $model->where('overtimes.status', $filters['status']);
        }

        $rows  = $model->orderBy('overtimes.date', 'DESC')->orderBy('overtimes.start_time', 'DESC')->paginate(25, 'default');
        $pager = $model->pager;

        $counts = array_fill_keys(array_keys(OvertimeModel::STATUSES), 0);

        $countQuery = $this->applyFilters(
            db_connect()->table('overtimes')->select('overtimes.status, COUNT(*) AS total')->join('employees', 'employees.id = overtimes.employee_id'),
            $filters,
            $start,
            $end,
        );

        foreach ($countQuery->groupBy('overtimes.status')->get()->getResultArray() as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        // Estimasi upah lembur disetujui sebulan (engine payroll: per karyawan per hari)
        $approved = $this->filteredQuery($filters, $start, $end)->where('overtimes.status', OvertimeModel::STATUS_APPROVED)->findAll();
        $wages    = $this->monthlyWages(array_merge(array_column($approved, 'employee_id'), array_column($rows, 'employee_id')));
        $perDay   = [];
        $hours    = 0.0;

        foreach ($approved as $overtime) {
            $perDay[$overtime['employee_id']][$overtime['date']] = ($perDay[$overtime['employee_id']][$overtime['date']] ?? 0) + (float) $overtime['hours'];
            $hours += (float) $overtime['hours'];
        }

        $calculator = new OvertimeCalculator(service('appSettings')->int('payroll.overtime_divisor', 173));
        $cost       = 0;

        foreach ($perDay as $employeeId => $days) {
            $cost += $calculator->calculate($wages[$employeeId] ?? 0, array_values($days))?->amount ?? 0;
        }

        foreach ($rows as &$row) {
            $row['estimate'] = $calculator->calculate($wages[$row['employee_id']] ?? 0, [(float) $row['hours']])?->amount ?? 0;
        }
        unset($row);

        $guard = PayrollPeriodGuard::make();

        return view('overtime/index', [
            'title'       => 'Lembur',
            'breadcrumbs' => [['label' => 'Kehadiran'], ['label' => 'Lembur']],
            'overtimes'   => $rows,
            'pager'       => $pager,
            'filters'     => $filters,
            'counts'      => $counts,
            'stats'       => ['hours' => $hours, 'cost' => $cost, 'employees' => count($perDay)],
            'departments' => (new DepartmentModel())->options(),
            'employees'   => $this->eligibleEmployees(),
            'closedUntil' => $guard->lastClosedDate(),
            'canManage'   => $this->canManage(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $data = $this->payload();

        if (($errors = $this->validateOvertime($data)) !== []) {
            return $this->backWithErrors($this->backUrl(), $errors, 'create');
        }

        $model = new OvertimeModel();

        if (! $model->insert($data + ['status' => OvertimeModel::STATUS_PENDING])) {
            return $this->backWithErrors($this->backUrl(), $model->errors(), 'create');
        }

        $id   = (int) $model->getInsertID();
        $name = $this->employeeName($data['employee_id']);
        $this->audit('overtime.create', 'overtime', $id, sprintf('Mengajukan lembur %s %s (%s jam)', $name, tanggal_id($data['date']), number_id($data['hours'], 2)), null, $data);

        return redirect()->to($this->backUrl($data['date']))->with('success', "Lembur {$name} tanggal " . tanggal_id($data['date'], 'long') . ' (' . number_id($data['hours'], 2) . ' jam) diajukan dan menunggu persetujuan.');
    }

    public function update(int $id): RedirectResponse
    {
        $model  = new OvertimeModel();
        $before = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $data   = $this->payload();

        if ($before['status'] === OvertimeModel::STATUS_APPROVED) {
            return redirect()->to($this->backUrl())->with('error', 'Lembur yang sudah disetujui tidak dapat diubah.');
        }

        if (($error = PayrollPeriodGuard::make()->errorFor($before['date'])) !== null) {
            return redirect()->to($this->backUrl())->with('error', $error);
        }

        if (($errors = $this->validateOvertime($data, $id)) !== []) {
            return $this->backWithErrors($this->backUrl(), $errors, 'edit:' . $id);
        }

        // Pengajuan yang ditolak lalu diperbaiki kembali menunggu persetujuan
        $data += ['status' => OvertimeModel::STATUS_PENDING, 'approved_by' => null, 'approved_at' => null, 'review_note' => null];

        if (! $model->update($id, $data)) {
            return $this->backWithErrors($this->backUrl(), $model->errors(), 'edit:' . $id);
        }

        [$old, $new] = $this->diff($before, $data);

        if ($new !== []) {
            $this->audit('overtime.update', 'overtime', $id, 'Memperbarui lembur ' . $this->employeeName($data['employee_id']) . ' ' . tanggal_id($data['date']), $old, $new);
        }

        return redirect()->to($this->backUrl($data['date']))->with('success', 'Pengajuan lembur berhasil diperbarui.');
    }

    public function delete(int $id): RedirectResponse
    {
        $model    = new OvertimeModel();
        $overtime = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();

        if ($overtime['status'] === OvertimeModel::STATUS_APPROVED) {
            return redirect()->to($this->backUrl())->with('error', 'Lembur yang sudah disetujui tidak dapat dihapus.');
        }

        if (($error = PayrollPeriodGuard::make()->errorFor($overtime['date'])) !== null) {
            return redirect()->to($this->backUrl())->with('error', $error);
        }

        $model->delete($id);
        $this->audit('overtime.delete', 'overtime', $id, 'Menghapus pengajuan lembur ' . $this->employeeName($overtime['employee_id']) . ' ' . tanggal_id($overtime['date']), $overtime);

        return redirect()->to($this->backUrl())->with('success', 'Pengajuan lembur dihapus.');
    }

    /**
     * Setujui / tolak satu atau beberapa pengajuan sekaligus.
     */
    public function review(): RedirectResponse
    {
        $action = (string) $this->request->getPost('action');
        $note   = trim((string) $this->request->getPost('note'));
        $ids    = $this->request->getPost('ids');
        $ids    = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : explode(',', (string) $ids)))));

        if (! in_array($action, ['approve', 'reject'], true) || $ids === []) {
            return redirect()->to($this->backUrl())->with('error', 'Pilih pengajuan lembur dan aksi yang valid.');
        }

        if ($action === 'reject' && $note === '') {
            return redirect()->to($this->backUrl())->with('error', 'Alasan penolakan lembur wajib diisi.');
        }

        $model     = new OvertimeModel();
        $overtimes = $model->withRelations()->whereIn('overtimes.id', $ids)->findAll();
        $guard     = PayrollPeriodGuard::make();
        $user      = $this->currentUser();
        $now       = date('Y-m-d H:i:s');
        $done      = [];
        $skipped   = [];

        foreach ($overtimes as $overtime) {
            $label = $overtime['full_name'] . ' ' . tanggal_id($overtime['date']);

            if ($overtime['status'] !== OvertimeModel::STATUS_PENDING) {
                $skipped[] = "{$label} (sudah " . strtolower(OvertimeModel::STATUSES[$overtime['status']]) . ')';

                continue;
            }

            if ($guard->isClosed($overtime['date'])) {
                $skipped[] = "{$label} (periode payroll sudah final)";

                continue;
            }

            if ($action === 'approve' && ! $overtime['overtime_eligible']) {
                $skipped[] = "{$label} (jabatan tidak berhak lembur)";

                continue;
            }

            $model->update($overtime['id'], [
                'status'      => $action === 'approve' ? OvertimeModel::STATUS_APPROVED : OvertimeModel::STATUS_REJECTED,
                'approved_by' => $user['id'] ?? null,
                'approved_at' => $now,
                'review_note' => $note === '' ? null : mb_substr($note, 0, 255),
            ]);

            $this->audit('overtime.' . $action, 'overtime', $overtime['id'], sprintf(
                '%s lembur %s (%s jam)%s',
                $action === 'approve' ? 'Menyetujui' : 'Menolak',
                $label,
                number_id($overtime['hours'], 2),
                $note !== '' ? ' — ' . $note : '',
            ), ['status' => $overtime['status']], ['status' => $action === 'approve' ? 'approved' : 'rejected']);

            $done[] = $overtime;
        }

        $redirect = redirect()->to($this->backUrl());
        $verb     = $action === 'approve' ? 'disetujui' : 'ditolak';

        if ($done === []) {
            return $redirect->with('error', 'Tidak ada pengajuan yang diproses: ' . implode('; ', $skipped) . '.');
        }

        $message = count($done) . ' pengajuan lembur ' . $verb . ' (' . number_id(array_sum(array_column($done, 'hours')), 2) . ' jam).';

        if ($skipped !== []) {
            $redirect->with('warning', count($skipped) . ' dilewati: ' . implode('; ', $skipped) . '.');
        } elseif ($action === 'approve') {
            $redirect->with('warning', $this->regenerateHint(array_column($done, 'date'), 'lembur ini'));
        }

        return $redirect->with('success', $message);
    }

    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $start = AttendanceService::parseTime((string) $this->request->getPost('start_time'));
        $end   = AttendanceService::parseTime((string) $this->request->getPost('end_time'));

        return [
            'employee_id' => (int) $this->request->getPost('employee_id'),
            'date'        => trim((string) $this->request->getPost('date')),
            'start_time'  => is_string($start) ? $start : '',
            'end_time'    => is_string($end) ? $end : '',
            'hours'       => is_string($start) && is_string($end) ? number_format(OvertimeModel::hoursBetween($start, $end), 2, '.', '') : '0',
            'reason'      => trim(preg_replace('/\s+/', ' ', (string) $this->request->getPost('reason'))),
        ];
    }

    /**
     * Aturan bisnis di luar validasi kolom model.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function validateOvertime(array $data, ?int $ignoreId = null): array
    {
        $model = new OvertimeModel();

        if (! $model->validate($data + ['status' => OvertimeModel::STATUS_PENDING])) {
            return $model->errors();
        }

        $employee = (new EmployeeModel())->withRelations()->find($data['employee_id']);

        if ($employee === null) {
            return ['employee_id' => 'Karyawan tidak ditemukan.'];
        }

        if (! $employee['overtime_eligible']) {
            return ['employee_id' => "Jabatan {$employee['position_name']} tidak berhak atas upah lembur."];
        }

        if ($data['date'] < $employee['join_date'] || ($employee['resign_date'] !== null && $data['date'] > $employee['resign_date'])) {
            return ['date' => 'Karyawan belum/tidak lagi bekerja pada tanggal ini.'];
        }

        if (($error = PayrollPeriodGuard::make()->errorFor($data['date'])) !== null) {
            return ['date' => $error];
        }

        // Rentang jam tidak boleh bertumpuk dengan pengajuan lain (yang belum ditolak) di hari yang sama
        $others = $model->where('employee_id', $data['employee_id'])
            ->where('date', $data['date'])
            ->where('status !=', OvertimeModel::STATUS_REJECTED)
            ->findAll();

        $range = static function (string $start, string $end): array {
            $from = strtotime('2000-01-01 ' . $start);

            return [$from, $from + (int) round(OvertimeModel::hoursBetween($start, $end) * 3600)];
        };
        [$from, $to] = $range($data['start_time'], $data['end_time']);

        foreach ($others as $other) {
            if ((int) $other['id'] === $ignoreId) {
                continue;
            }

            [$otherFrom, $otherTo] = $range($other['start_time'], $other['end_time']);

            if ($from < $otherTo && $otherFrom < $to) {
                return ['start_time' => sprintf('Bertumpuk dengan lembur %s–%s di tanggal yang sama.', substr($other['start_time'], 0, 5), substr($other['end_time'], 0, 5))];
            }
        }

        return [];
    }

    /**
     * Upah sebulan (gaji pokok + tunjangan tetap dasar BPJS) — dasar upah lembur.
     *
     * @param list<int|string> $employeeIds
     *
     * @return array<int, int>
     */
    private function monthlyWages(array $employeeIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($ids === []) {
            return [];
        }

        $profiles = service('payroll')->componentProfiles($ids);
        $wages    = [];

        foreach ((new EmployeeModel())->select('id, basic_salary')->whereIn('id', $ids)->findAll() as $employee) {
            $wage = (int) $employee['basic_salary'];

            foreach ($profiles[$employee['id']]['fixedEarnings'] ?? [] as $earning) {
                $wage += $earning['bpjsBase'] ? $earning['amount'] : 0;
            }

            $wages[(int) $employee['id']] = $wage;
        }

        return $wages;
    }

    /**
     * @return array<int, string>
     */
    private function eligibleEmployees(): array
    {
        $options = [];

        foreach ((new EmployeeModel())->withRelations()->active()->where('positions.overtime_eligible', 1)->orderBy('employees.full_name')->findAll() as $employee) {
            $options[$employee['id']] = $employee['full_name'] . ' · ' . $employee['position_name'];
        }

        return $options;
    }

    private function employeeName(int $employeeId): string
    {
        return (string) ((new EmployeeModel())->withDeleted()->find($employeeId)['full_name'] ?? '#' . $employeeId);
    }

    /**
     * @return array{month: string, status: string, department_id: string, q: string}
     */
    private function filters(): array
    {
        $month  = (string) $this->request->getGet('month');
        $status = (string) ($this->request->getGet('status') ?? 'all');

        return [
            'month'         => preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : date('Y-m'),
            'status'        => array_key_exists($status, OvertimeModel::STATUSES) ? $status : 'all',
            'department_id' => (string) (int) $this->request->getGet('department_id') ?: '',
            'q'             => trim((string) $this->request->getGet('q')),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthRange(string $month): array
    {
        return [$month . '-01', date('Y-m-t', strtotime($month . '-01'))];
    }

    /**
     * @param array{month: string, status: string, department_id: string, q: string} $filters
     */
    private function filteredQuery(array $filters, string $start, string $end): OvertimeModel
    {
        return $this->applyFilters((new OvertimeModel())->withRelations(), $filters, $start, $end);
    }

    /**
     * @template T of OvertimeModel|BaseBuilder
     *
     * @param T                                                                       $query
     * @param array{month: string, status: string, department_id: string, q: string} $filters
     *
     * @return T
     */
    private function applyFilters(OvertimeModel|BaseBuilder $query, array $filters, string $start, string $end): OvertimeModel|BaseBuilder
    {
        $query->where('overtimes.date >=', $start)->where('overtimes.date <=', $end);

        if ($filters['department_id'] !== '') {
            $query->where('employees.department_id', (int) $filters['department_id']);
        }

        if ($filters['q'] !== '') {
            $query->groupStart()->like('employees.full_name', $filters['q'])->orLike('employees.nip', $filters['q'])->groupEnd();
        }

        return $query;
    }

    private function backUrl(?string $date = null): string
    {
        $previous = previous_url();
        $index    = url_to('overtime.index');

        if ($date !== null) {
            return $index . '?month=' . substr($date, 0, 7);
        }

        return str_starts_with($previous, site_url('overtime')) ? $previous : $index;
    }
}
