<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Database\Seeds\UserSeeder;
use App\Models\AttendanceModel;
use App\Models\DepartmentModel;
use App\Models\EmployeeComponentModel;
use App\Models\EmployeeModel;
use App\Models\LoanModel;
use App\Models\PositionModel;
use App\Models\PtkpRateModel;
use App\Models\SalaryComponentModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeController extends BaseController
{
    /** Komponen tetap yang diisi langsung dari form karyawan. */
    private const FORM_ALLOWANCES = ['POS_ALW' => 'position_allowance', 'COMM_ALW' => 'communication_allowance'];

    public function index(): string
    {
        $filters = $this->filters();
        $model   = $this->filteredQuery($filters);
        $rows    = $model->orderBy('employees.nip')->paginate(25, 'default');

        $counts = array_map('intval', array_column(
            db_connect()->table('employees')->select('status, COUNT(*) AS total')->where('deleted_at', null)->groupBy('status')->get()->getResultArray(),
            'total',
            'status',
        ));

        return view('master/employees/index', [
            'title'       => 'Karyawan',
            'breadcrumbs' => [['label' => 'Master Data'], ['label' => 'Karyawan']],
            'employees'   => $rows,
            'pager'       => $model->pager,
            'filters'     => $filters,
            'counts'      => $counts,
            'departments' => (new DepartmentModel())->options(),
            'canManage'   => $this->canManage(),
        ]);
    }

    public function export(): ResponseInterface
    {
        $filters   = $this->filters();
        $employees = $this->filteredQuery($filters)->orderBy('employees.nip')->findAll();

        $columns = [
            'NIP', 'Nama Lengkap', 'NIK', 'NPWP', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'No. HP', 'Alamat',
            'Departemen', 'Jabatan', 'Status Kerja', 'Status', 'Tanggal Masuk', 'Tanggal Keluar', 'PTKP', 'Gaji Pokok',
            'Bank', 'No. Rekening', 'Nama Rekening',
        ];

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
        fputcsv($handle, $columns, ';');

        foreach ($employees as $e) {
            fputcsv($handle, [
                $e['nip'], $e['full_name'], "'" . $e['nik'], $e['npwp'] ? "'" . $e['npwp'] : '', EmployeeModel::GENDERS[$e['gender']] ?? $e['gender'],
                $e['birth_place'], $e['birth_date'], $e['phone'] ? "'" . $e['phone'] : '', $e['address'],
                $e['department_name'], $e['position_name'], EmployeeModel::EMPLOYMENT_TYPES[$e['employment_type']] ?? $e['employment_type'],
                EmployeeModel::STATUSES[$e['status']] ?? $e['status'], $e['join_date'], $e['resign_date'], $e['ptkp_status'], $e['basic_salary'],
                $e['bank_name'], $e['bank_account_no'] ? "'" . $e['bank_account_no'] : '', $e['bank_account_name'],
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->audit('employee.export', 'employee', null, 'Mengekspor data ' . count($employees) . ' karyawan ke CSV', null, array_filter($filters));

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="karyawan-' . date('Ymd-His') . '.csv"')
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($csv);
    }

    public function create(): string
    {
        $model = new EmployeeModel();

        return $this->form([
            'nip'                     => $model->nextNip(),
            'gender'                  => 'L',
            'employment_type'         => 'probation',
            'join_date'               => date('Y-m-d'),
            'ptkp_status'             => 'TK/0',
            'bpjs_kes_enrolled'       => true,
            'bpjs_tk_enrolled'        => true,
            'position_allowance'      => 0,
            'communication_allowance' => 0,
        ], 'create');
    }

    public function store(): RedirectResponse
    {
        $model      = new EmployeeModel();
        $data       = $this->payload() + ['nip' => $model->nextNip(), 'status' => EmployeeModel::STATUS_ACTIVE];
        $allowances = $this->allowancePayload();
        $db         = db_connect();

        $db->transStart();

        if (! $model->insert($data)) {
            $db->transRollback();

            return $this->backWithErrors(url_to('employees.create'), $model->errors());
        }

        $id = (int) $model->getInsertID();
        $this->saveAllowances($id, $allowances);

        $account = null;

        if ($this->request->getPost('create_account')) {
            $account = $this->createAccount($id, $data['full_name']);
        }

        $db->transComplete();

        $this->audit('employee.create', 'employee', $id, "Menambahkan karyawan {$data['full_name']} ({$data['nip']})", null, array_diff_key($data, array_flip(['nik', 'npwp', 'bank_account_no'])));

        $message = "Karyawan {$data['full_name']} ({$data['nip']}) berhasil ditambahkan.";

        if ($account !== null) {
            $message .= " Akun login: {$account} dengan password awal " . UserSeeder::DEFAULT_PASSWORD . ' (wajib diganti saat login pertama).';
        }

        return redirect()->route('employees.show', [$id])->with('success', $message);
    }

    public function show(int $id): string
    {
        $employee = (new EmployeeModel())->withRelations()->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $db       = db_connect();

        $components = (new SalaryComponentModel())
            ->where('is_active', 1)
            ->whereIn('calc_type', ['fixed', 'daily'])
            ->whereIn('type', ['earning', 'deduction'])
            ->orderBy('type')->orderBy('sort_order')
            ->findAll();
        $overrides = array_column((new EmployeeComponentModel())->where('employee_id', $id)->findAll(), 'amount', 'salary_component_id');

        $months = [];

        for ($i = 2; $i >= 0; $i--) {
            $start = date('Y-m-01', strtotime("first day of -{$i} month"));
            $end   = date('Y-m-t', strtotime($start));
            $months[]  = [
                'label'   => periode_id((int) date('Y', strtotime($start)), (int) date('n', strtotime($start))),
                'summary' => (new AttendanceModel())->summary($id, $start, $end),
            ];
        }

        $loans = (new LoanModel())->where('employee_id', $id)->orderBy('id', 'DESC')->findAll();

        $payrolls = $db->table('payrolls p')
            ->select('p.id, p.total_earning, p.total_deduction, p.pph21, p.take_home_pay, p.present_days, p.workdays, p.active_workdays, p.overtime_hours, p.ter_category, p.ter_rate')
            ->select('pp.id AS period_id, pp.name AS period_name, pp.status AS period_status, pp.pay_date, pp.year, pp.month')
            ->join('payroll_periods pp', 'pp.id = p.period_id')
            ->where('p.employee_id', $id)
            ->orderBy('pp.year', 'DESC')->orderBy('pp.month', 'DESC')
            ->get()->getResultArray();

        return view('master/employees/show', [
            'title'       => $employee['full_name'],
            'breadcrumbs' => [['label' => 'Master Data'], ['label' => 'Karyawan', 'url' => url_to('employees.index')], ['label' => $employee['full_name']]],
            'employee'    => $employee,
            'components'  => $components,
            'overrides'   => $overrides,
            'months'      => $months,
            'recent'      => (new AttendanceModel())->where('employee_id', $id)->orderBy('date', 'DESC')->findAll(10),
            'loans'       => $loans,
            'payrolls'    => $payrolls,
            'account'     => (new UserModel())->where('employee_id', $id)->first(),
            'isHead'      => $db->table('departments')->where('head_employee_id', $id)->countAllResults() > 0,
            'canManage'   => $this->canManage(),
        ]);
    }

    public function edit(int $id): string
    {
        $employee = (new EmployeeModel())->find($id) ?? throw PageNotFoundException::forPageNotFound();

        foreach ((new EmployeeComponentModel())->forEmployee($id) as $component) {
            if (isset(self::FORM_ALLOWANCES[$component['code']])) {
                $employee[self::FORM_ALLOWANCES[$component['code']]] = $component['amount'];
            }
        }

        return $this->form($employee + ['position_allowance' => 0, 'communication_allowance' => 0], 'edit');
    }

    public function update(int $id): RedirectResponse
    {
        $model  = new EmployeeModel();
        $before = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $data   = $this->payload() + ['id' => $id, 'nip' => $before['nip'], 'status' => $before['status']];
        $db     = db_connect();

        $db->transStart();

        if (! $model->update($id, $data)) {
            $db->transRollback();

            return $this->backWithErrors(url_to('employees.edit', $id), $model->errors());
        }

        $this->saveAllowances($id, $this->allowancePayload());
        $db->transComplete();

        [$old, $new] = $this->diff($before, $data);

        if ($new !== []) {
            // Data identitas sensitif tidak disimpan nilai lengkapnya di audit log
            foreach (['nik', 'npwp', 'bank_account_no'] as $sensitive) {
                if (isset($new[$sensitive])) {
                    $old[$sensitive] = mask_id((string) $old[$sensitive]);
                    $new[$sensitive] = mask_id((string) $new[$sensitive]);
                }
            }

            $this->audit('employee.update', 'employee', $id, "Memperbarui data karyawan {$data['full_name']}", $old, $new);
        }

        return redirect()->route('employees.show', [$id])->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function resign(int $id): RedirectResponse
    {
        $model    = new EmployeeModel();
        $employee = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $date     = (string) $this->request->getPost('resign_date');
        $status   = $this->request->getPost('status') === EmployeeModel::STATUS_TERMINATED ? EmployeeModel::STATUS_TERMINATED : EmployeeModel::STATUS_RESIGNED;
        $note     = trim((string) $this->request->getPost('note'));
        $back     = url_to('employees.show', $id);

        if ($employee['status'] !== EmployeeModel::STATUS_ACTIVE) {
            return redirect()->to($back)->with('error', 'Karyawan ini sudah tidak aktif.');
        }

        if (! $this->validateData(['resign_date' => $date], ['resign_date' => ['label' => 'Tanggal keluar', 'rules' => 'required|valid_date[Y-m-d]']])) {
            return redirect()->to($back)->with('error', $this->validator->getError('resign_date'));
        }

        if ($date < $employee['join_date']) {
            return redirect()->to($back)->with('error', 'Tanggal keluar tidak boleh sebelum tanggal masuk (' . tanggal_id($employee['join_date']) . ').');
        }

        $db      = db_connect();
        $account = (new UserModel())->where(['employee_id' => $id, 'is_active' => 1])->first();
        $db->transStart();

        $model->update($id, ['status' => $status, 'resign_date' => $date]);
        $db->table('users')->where('employee_id', $id)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $db->table('departments')->where('head_employee_id', $id)->update(['head_employee_id' => null, 'updated_at' => date('Y-m-d H:i:s')]);

        $db->transComplete();

        $label = EmployeeModel::STATUSES[$status];
        $this->audit('employee.resign', 'employee', $id, "Mencatat {$label} {$employee['full_name']} per " . tanggal_id($date) . ($note !== '' ? " — {$note}" : ''),
            ['status' => $employee['status'], 'resign_date' => null],
            ['status' => $status, 'resign_date' => $date]);

        if ($account !== null) {
            $this->audit('user.deactivate', 'user', $account['id'], "Menonaktifkan akun {$account['username']} karena karyawan {$label}", ['is_active' => 1], ['is_active' => 0]);
        }

        return redirect()->to($back)->with('success', "{$employee['full_name']} dicatat {$label} per " . tanggal_id($date, 'long') . '. Akun login dinonaktifkan; gaji periode terakhir dihitung prorata.');
    }

    public function updateComponents(int $id): RedirectResponse
    {
        $employee = (new EmployeeModel())->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $amounts  = (array) $this->request->getPost('amounts');
        $model    = new EmployeeComponentModel();
        $existing = array_column($model->where('employee_id', $id)->findAll(), null, 'salary_component_id');
        $allowed  = array_column(
            (new SalaryComponentModel())->whereIn('calc_type', ['fixed', 'daily'])->whereIn('type', ['earning', 'deduction'])->findAll(),
            null,
            'id',
        );

        $before = [];
        $after  = [];
        $db     = db_connect();
        $db->transStart();

        foreach ($amounts as $componentId => $raw) {
            $componentId = (int) $componentId;

            if (! isset($allowed[$componentId])) {
                continue;
            }

            $raw       = trim((string) $raw);
            $amount    = $raw === '' ? null : (int) preg_replace('/\D/', '', $raw);
            $code      = $allowed[$componentId]['code'];
            $current   = isset($existing[$componentId]) ? (int) $existing[$componentId]['amount'] : null;
            // Komponen tetap bernilai 0 dianggap tidak ada; komponen harian kosong = pakai tarif default
            $removable = $amount === null || ($amount === 0 && $allowed[$componentId]['calc_type'] === 'fixed');

            if ($removable) {
                if ($current !== null) {
                    $model->delete($existing[$componentId]['id']);
                    $before[$code] = $current;
                    $after[$code]  = null;
                }

                continue;
            }

            if ($current === $amount) {
                continue;
            }

            if ($current === null) {
                $model->insert(['employee_id' => $id, 'salary_component_id' => $componentId, 'amount' => $amount]);
            } else {
                $model->update($existing[$componentId]['id'], ['amount' => $amount]);
            }

            $before[$code] = $current;
            $after[$code]  = $amount;
        }

        $db->transComplete();

        if ($after === []) {
            return redirect()->to(url_to('employees.show', $id) . '#komponen')->with('info', 'Tidak ada perubahan komponen gaji.');
        }

        $this->audit('employee.components', 'employee', $id, "Memperbarui komponen gaji {$employee['full_name']}", $before, $after);

        return redirect()->to(url_to('employees.show', $id) . '#komponen')->with('success', 'Komponen gaji berhasil diperbarui. Berlaku untuk payroll yang di-generate berikutnya.');
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $employee
     */
    private function form(array $employee, string $mode): string
    {
        $positions = [];

        foreach ((new PositionModel())->withDepartment() as $position) {
            $positions[] = [
                'id'                      => (int) $position['id'],
                'department_id'           => (int) $position['department_id'],
                'name'                    => $position['name'],
                'grade'                   => (int) $position['grade'],
                'base_salary'             => (int) $position['base_salary'],
                'position_allowance'      => (int) $position['position_allowance'],
                'communication_allowance' => (int) $position['communication_allowance'],
            ];
        }

        $title = $mode === 'create' ? 'Tambah Karyawan' : 'Ubah Data Karyawan';

        return view('master/employees/form', [
            'title'       => $title,
            'breadcrumbs' => array_values(array_filter([
                ['label' => 'Master Data'],
                ['label' => 'Karyawan', 'url' => url_to('employees.index')],
                $mode === 'edit' ? ['label' => $employee['full_name'], 'url' => url_to('employees.show', $employee['id'])] : null,
                ['label' => $mode === 'create' ? 'Tambah' : 'Ubah'],
            ])),
            'mode'        => $mode,
            'employee'    => $employee,
            'departments' => (new DepartmentModel())->options(),
            'positions'   => $positions,
            'ptkp'        => (new PtkpRateModel())->options(),
            'hasAccount'  => $mode === 'edit' && (new UserModel())->where('employee_id', $employee['id'])->countAllResults() > 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $post   = fn (string $key): string => trim((string) $this->request->getPost($key));
        $digits = fn (string $key): string => preg_replace('/\D/', '', $post($key));

        return [
            'full_name'         => preg_replace('/\s+/', ' ', $post('full_name')),
            'nik'               => $digits('nik'),
            'npwp'              => $digits('npwp') ?: null,
            'gender'            => $post('gender'),
            'birth_place'       => $post('birth_place') ?: null,
            'birth_date'        => $post('birth_date') ?: null,
            'address'           => $post('address') ?: null,
            'phone'             => $post('phone') ?: null,
            'department_id'     => (int) $post('department_id') ?: null,
            'position_id'       => (int) $post('position_id') ?: null,
            'employment_type'   => $post('employment_type'),
            'join_date'         => $post('join_date'),
            'ptkp_status'       => $post('ptkp_status'),
            'basic_salary'      => (int) $digits('basic_salary'),
            'bank_name'         => $post('bank_name') ?: null,
            'bank_account_no'   => $digits('bank_account_no') ?: null,
            'bank_account_name' => strtoupper($post('bank_account_name')) ?: null,
            'bpjs_kes_enrolled' => $this->request->getPost('bpjs_kes_enrolled') ? 1 : 0,
            'bpjs_tk_enrolled'  => $this->request->getPost('bpjs_tk_enrolled') ? 1 : 0,
        ];
    }

    /**
     * @return array<string, int> kode komponen → nominal
     */
    private function allowancePayload(): array
    {
        $values = [];

        foreach (self::FORM_ALLOWANCES as $code => $field) {
            $values[$code] = (int) preg_replace('/\D/', '', (string) $this->request->getPost($field));
        }

        return $values;
    }

    /**
     * @param array<string, int> $allowances
     */
    private function saveAllowances(int $employeeId, array $allowances): void
    {
        $components = (new SalaryComponentModel())->idsByCode();
        $model      = new EmployeeComponentModel();

        foreach ($allowances as $code => $amount) {
            $componentId = $components[$code] ?? null;

            if ($componentId === null) {
                continue;
            }

            $existing = $model->where(['employee_id' => $employeeId, 'salary_component_id' => $componentId])->first();

            if ($amount <= 0) {
                if ($existing) {
                    $model->delete($existing['id']);
                }
            } elseif ($existing) {
                $model->update($existing['id'], ['amount' => $amount]);
            } else {
                $model->insert(['employee_id' => $employeeId, 'salary_component_id' => $componentId, 'amount' => $amount]);
            }
        }
    }

    private function createAccount(int $employeeId, string $fullName): string
    {
        $users    = new UserModel();
        $base     = substr(UserSeeder::usernameFor($fullName), 0, 26);
        $username = $base;

        for ($i = 2; $users->where('username', $username)->countAllResults() > 0; $i++) {
            $username = $base . $i;
        }

        $users->insert([
            'username'             => $username,
            'password_hash'        => password_hash(UserSeeder::DEFAULT_PASSWORD, PASSWORD_DEFAULT),
            'name'                 => $fullName,
            'role'                 => UserModel::ROLE_EMPLOYEE,
            'employee_id'          => $employeeId,
            'is_active'            => 1,
            'must_change_password' => 1,
        ]);

        $this->audit('user.create', 'user', (int) $users->getInsertID(), "Membuat akun login {$username} untuk {$fullName}", null, [
            'username' => $username, 'name' => $fullName, 'role' => UserModel::ROLE_EMPLOYEE, 'employee_id' => $employeeId,
        ]);

        return $username;
    }

    /**
     * @return array{q: string, department_id: string, status: string, type: string}
     */
    private function filters(): array
    {
        $status = (string) ($this->request->getGet('status') ?? 'active');

        return [
            'q'             => trim((string) $this->request->getGet('q')),
            'department_id' => (string) (int) $this->request->getGet('department_id') ?: '',
            'status'        => in_array($status, ['active', 'resigned', 'terminated', 'all'], true) ? $status : 'active',
            'type'          => array_key_exists((string) $this->request->getGet('type'), EmployeeModel::EMPLOYMENT_TYPES) ? (string) $this->request->getGet('type') : '',
        ];
    }

    /**
     * @param array{q: string, department_id: string, status: string, type: string} $filters
     */
    private function filteredQuery(array $filters): EmployeeModel
    {
        $model = (new EmployeeModel())->withRelations();

        if ($filters['q'] !== '') {
            $model->groupStart()
                ->like('employees.full_name', $filters['q'])
                ->orLike('employees.nip', $filters['q'])
                ->groupEnd();
        }

        if ($filters['department_id'] !== '') {
            $model->where('employees.department_id', (int) $filters['department_id']);
        }

        if ($filters['status'] !== 'all') {
            $model->where('employees.status', $filters['status']);
        }

        if ($filters['type'] !== '') {
            $model->where('employees.employment_type', $filters['type']);
        }

        return $model;
    }
}
