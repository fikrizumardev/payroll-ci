<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Seeds\UserSeeder;
use App\Models\EmployeeModel;
use App\Models\UserModel;
use App\Models\UserTokenModel;
use App\Services\Auth\PasswordGenerator;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Manajemen akun login (khusus Super Admin): tambah/ubah, reset password,
 * aktif/nonaktif, dan buka kunci. Password sementara dibuat otomatis, ditampilkan
 * sekali, dan wajib diganti saat login berikutnya.
 *
 * Penjagaan: Super Admin tidak bisa menonaktifkan/menurunkan perannya sendiri, dan
 * minimal satu Super Admin aktif harus selalu tersisa.
 */
class UserController extends BaseController
{
    private const STATUSES = [
        'active'      => 'Aktif',
        'inactive'    => 'Nonaktif',
        'locked'      => 'Terkunci',
        'must_change' => 'Wajib ganti password',
    ];

    public function index(): string
    {
        $filters = $this->filters();
        $model   = $this->filteredQuery($filters);
        $users   = $model->orderBy('users.role = \'superadmin\'', 'DESC', false)->orderBy('users.username')->paginate(25, 'default');

        $roleCounts = array_map('intval', array_column(
            db_connect()->table('users')->select('role, COUNT(*) AS total')->groupBy('role')->get()->getResultArray(),
            'total',
            'role',
        ));

        return view('users/index', [
            'title'       => 'Pengguna',
            'breadcrumbs' => [['label' => 'Sistem'], ['label' => 'Pengguna']],
            'users'       => $users,
            'pager'       => $model->pager,
            'filters'     => $filters,
            'statuses'    => self::STATUSES,
            'roleCounts'  => $roleCounts,
            'employees'   => $this->employeeChoices(),
            'currentId'   => (int) $this->currentUser()['id'],
            'credential'  => session('credential'),
        ]);
    }

    public function store(): RedirectResponse
    {
        $data   = $this->payload();
        $errors = $this->validateAccount($data, null);

        if ($errors !== []) {
            return $this->backWithErrors(url_to('users.index'), $errors, 'create');
        }

        $password = PasswordGenerator::temporary($data['username']);
        $model    = new UserModel();

        $saved = $model->insert($data + [
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'is_active'            => 1,
            'must_change_password' => 1,
        ]);

        if (! $saved) {
            return $this->backWithErrors(url_to('users.index'), $model->errors(), 'create');
        }

        $id = (int) $model->getInsertID();
        $this->audit('user.create', 'user', $id, "Membuat akun login {$data['username']} ({$data['name']}, " . role_label($data['role']) . ')', null, $data);

        return redirect()->route('users.index')
            ->with('success', "Akun {$data['username']} berhasil dibuat.")
            ->with('credential', ['username' => $data['username'], 'name' => $data['name'], 'password' => $password, 'reason' => 'create']);
    }

    public function update(int $id): RedirectResponse
    {
        $model  = new UserModel();
        $before = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $data   = $this->payload();
        $errors = $this->validateAccount($data, $before);

        if ($errors !== []) {
            return $this->backWithErrors(url_to('users.index'), $errors, 'edit:' . $id);
        }

        if (! $model->update($id, $data + ['id' => $id])) {
            return $this->backWithErrors(url_to('users.index'), $model->errors(), 'edit:' . $id);
        }

        [$old, $new] = $this->diff($before, $data);

        if ($new !== []) {
            $this->audit('user.update', 'user', $id, "Memperbarui akun {$data['username']}", $old, $new);
        }

        if ($id === (int) $this->currentUser()['id']) {
            $this->auth->refresh();
        }

        return redirect()->route('users.index')->with('success', "Akun {$data['username']} berhasil diperbarui.");
    }

    public function resetPassword(int $id): RedirectResponse
    {
        $user = $this->user($id);

        if ($this->isSelf($user)) {
            return redirect()->route('users.index')->with('error', 'Untuk akun Anda sendiri, gunakan menu Ganti Password.');
        }

        $password = PasswordGenerator::temporary($user['username']);

        (new UserModel())->update($id, [
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => 1,
            'failed_attempts'      => 0,
            'locked_until'         => null,
        ]);
        $this->revokeSessions($id);

        $this->audit('user.reset_password', 'user', $id, "Reset password {$user['username']}; wajib ganti saat login berikutnya");

        return redirect()->route('users.index')
            ->with('success', "Password {$user['username']} telah direset. Semua sesi \"Ingat saya\" akun ini dicabut.")
            ->with('credential', ['username' => $user['username'], 'name' => $user['name'], 'password' => $password, 'reason' => 'reset']);
    }

    public function activate(int $id): RedirectResponse
    {
        $user = $this->user($id);

        if ($user['is_active']) {
            return redirect()->route('users.index')->with('warning', "Akun {$user['username']} sudah aktif.");
        }

        if ($user['employee_id'] !== null) {
            $employee = (new EmployeeModel())->find($user['employee_id']);

            if ($employee === null || $employee['status'] !== EmployeeModel::STATUS_ACTIVE) {
                return redirect()->route('users.index')->with('error', "Akun {$user['username']} tidak bisa diaktifkan karena karyawan terkait sudah tidak aktif.");
            }
        }

        (new UserModel())->update($id, ['is_active' => 1, 'failed_attempts' => 0, 'locked_until' => null]);
        $this->audit('user.activate', 'user', $id, "Mengaktifkan akun {$user['username']}", ['is_active' => 0], ['is_active' => 1]);

        return redirect()->route('users.index')->with('success', "Akun {$user['username']} diaktifkan kembali.");
    }

    public function deactivate(int $id): RedirectResponse
    {
        $user = $this->user($id);

        if ($this->isSelf($user)) {
            return redirect()->route('users.index')->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        if (! $user['is_active']) {
            return redirect()->route('users.index')->with('warning', "Akun {$user['username']} sudah nonaktif.");
        }

        if ($this->isLastActiveSuperadmin($user)) {
            return redirect()->route('users.index')->with('error', 'Minimal harus ada satu Super Admin yang aktif.');
        }

        (new UserModel())->update($id, ['is_active' => 0]);
        $this->revokeSessions($id);
        $this->audit('user.deactivate', 'user', $id, "Menonaktifkan akun {$user['username']}", ['is_active' => 1], ['is_active' => 0]);

        return redirect()->route('users.index')->with('success', "Akun {$user['username']} dinonaktifkan. Sesi yang sedang berjalan otomatis berakhir.");
    }

    public function unlock(int $id): RedirectResponse
    {
        $user = $this->user($id);

        if ($user['locked_until'] === null || strtotime($user['locked_until']) <= time()) {
            return redirect()->route('users.index')->with('warning', "Akun {$user['username']} tidak sedang terkunci.");
        }

        (new UserModel())->update($id, ['failed_attempts' => 0, 'locked_until' => null]);
        $this->audit('user.unlock', 'user', $id, "Membuka kunci akun {$user['username']}", ['locked_until' => $user['locked_until']], ['locked_until' => null]);

        return redirect()->route('users.index')->with('success', "Kunci akun {$user['username']} dibuka.");
    }

    // ------------------------------------------------------------------

    /**
     * @return array{username: string, name: string, role: string, employee_id: int|null}
     */
    private function payload(): array
    {
        $employee = (string) $this->request->getPost('employee_id');

        return [
            'username'    => UserModel::normalize((string) $this->request->getPost('username')),
            'name'        => trim(preg_replace('/\s+/', ' ', (string) $this->request->getPost('name'))),
            'role'        => (string) $this->request->getPost('role'),
            'employee_id' => $employee === '' ? null : (int) $employee,
        ];
    }

    /**
     * Aturan bisnis di luar validasi model (format, unik) — dicek sebelum menyimpan.
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $before null = akun baru
     *
     * @return array<string, string>
     */
    private function validateAccount(array $data, ?array $before): array
    {
        $errors = [];

        if (! array_key_exists($data['role'], UserModel::ROLES)) {
            $errors['role'] = 'Pilih peran pengguna.';
        }

        if ($data['role'] === UserModel::ROLE_EMPLOYEE && $data['employee_id'] === null) {
            $errors['employee_id'] = 'Akun peran Karyawan wajib terhubung ke data karyawan.';
        }

        if ($data['employee_id'] !== null) {
            $employee = (new EmployeeModel())->find($data['employee_id']);

            if ($employee === null) {
                $errors['employee_id'] = 'Data karyawan tidak ditemukan.';
            } elseif ($employee['status'] !== EmployeeModel::STATUS_ACTIVE && (int) ($before['employee_id'] ?? 0) !== $data['employee_id']) {
                $errors['employee_id'] = "{$employee['full_name']} sudah tidak aktif bekerja.";
            }
        }

        if ($before !== null && $before['role'] !== $data['role']) {
            if ($this->isSelf($before)) {
                $errors['role'] = 'Anda tidak dapat mengubah peran akun Anda sendiri.';
            } elseif ($this->isLastActiveSuperadmin($before)) {
                $errors['role'] = 'Minimal harus ada satu Super Admin yang aktif.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isLastActiveSuperadmin(array $user): bool
    {
        return $user['role'] === UserModel::ROLE_SUPERADMIN && $user['is_active']
            && (new UserModel())->where(['role' => UserModel::ROLE_SUPERADMIN, 'is_active' => 1])->countAllResults() <= 1;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isSelf(array $user): bool
    {
        return (int) $user['id'] === (int) $this->currentUser()['id'];
    }

    /**
     * Cabut semua token "Ingat saya". Sesi aktif ikut berakhir karena AuthService
     * memeriksa ulang status akun di setiap request.
     */
    private function revokeSessions(int $userId): void
    {
        (new UserTokenModel())->where('user_id', $userId)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function user(int $id): array
    {
        return (new UserModel())->find($id) ?? throw PageNotFoundException::forPageNotFound();
    }

    /**
     * @return array{q: string, role: string, status: string}
     */
    private function filters(): array
    {
        $role   = (string) $this->request->getGet('role');
        $status = (string) $this->request->getGet('status');

        return [
            'q'      => mb_substr(trim((string) $this->request->getGet('q')), 0, 50),
            'role'   => array_key_exists($role, UserModel::ROLES) ? $role : '',
            'status' => array_key_exists($status, self::STATUSES) ? $status : '',
        ];
    }

    /**
     * @param array{q: string, role: string, status: string} $filters
     */
    private function filteredQuery(array $filters): UserModel
    {
        $model = (new UserModel())
            ->select('users.*, employees.full_name AS employee_name, employees.nip AS employee_nip, employees.status AS employee_status')
            ->select('departments.name AS department_name, positions.name AS position_name')
            ->join('employees', 'employees.id = users.employee_id', 'left')
            ->join('departments', 'departments.id = employees.department_id', 'left')
            ->join('positions', 'positions.id = employees.position_id', 'left');

        if ($filters['q'] !== '') {
            $model->groupStart()
                ->like('users.username', $filters['q'])
                ->orLike('users.name', $filters['q'])
                ->orLike('employees.nip', $filters['q'])
                ->groupEnd();
        }

        if ($filters['role'] !== '') {
            $model->where('users.role', $filters['role']);
        }

        $now = date('Y-m-d H:i:s');

        match ($filters['status']) {
            'active'      => $model->where('users.is_active', 1),
            'inactive'    => $model->where('users.is_active', 0),
            'locked'      => $model->where('users.locked_until >', $now),
            'must_change' => $model->where('users.must_change_password', 1),
            default       => null,
        };

        return $model;
    }

    /**
     * Karyawan untuk dropdown akun: id → nama, NIP, saran username, status, dan pemilik akun.
     *
     * @return array<int, array{label: string, name: string, username: string, active: bool, user_id: int|null}>
     */
    private function employeeChoices(): array
    {
        $rows = db_connect()->table('employees e')
            ->select('e.id, e.nip, e.full_name, e.status, u.id AS user_id')
            ->join('users u', 'u.employee_id = e.id', 'left')
            ->where('e.deleted_at', null)
            ->orderBy('e.full_name')
            ->get()->getResultArray();

        $choices = [];

        foreach ($rows as $row) {
            $choices[(int) $row['id']] = [
                'label'    => $row['full_name'] . ' · ' . $row['nip'] . ($row['status'] !== EmployeeModel::STATUS_ACTIVE ? ' (' . (EmployeeModel::STATUSES[$row['status']] ?? $row['status']) . ')' : ''),
                'name'     => $row['full_name'],
                'username' => substr(UserSeeder::usernameFor($row['full_name']), 0, 30),
                'active'   => $row['status'] === EmployeeModel::STATUS_ACTIVE,
                'user_id'  => $row['user_id'] === null ? null : (int) $row['user_id'],
            ];
        }

        return $choices;
    }
}
