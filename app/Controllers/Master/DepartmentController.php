<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use App\Models\EmployeeModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class DepartmentController extends BaseController
{
    public function index(): string
    {
        $departments = (new DepartmentModel())->withSummary();
        $positions   = array_column(
            db_connect()->table('positions')->select('department_id, COUNT(*) AS total')->groupBy('department_id')->get()->getResultArray(),
            'total',
            'department_id',
        );

        foreach ($departments as &$department) {
            $department['position_count'] = (int) ($positions[$department['id']] ?? 0);
        }
        unset($department);

        return view('master/departments/index', [
            'title'       => 'Departemen',
            'breadcrumbs' => [['label' => 'Master Data'], ['label' => 'Departemen']],
            'departments' => $departments,
            'employees'   => $this->employeeOptions(),
            'canManage'   => $this->canManage(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $model = new DepartmentModel();
        $data  = $this->payload();

        if (! $model->insert($data)) {
            return $this->backWithErrors(url_to('departments.index'), $model->errors(), 'create');
        }

        $id = (int) $model->getInsertID();
        $this->audit('department.create', 'department', $id, "Menambahkan departemen {$data['name']} ({$data['code']})", null, $data);

        return redirect()->route('departments.index')->with('success', "Departemen {$data['name']} berhasil ditambahkan.");
    }

    public function update(int $id): RedirectResponse
    {
        $model  = new DepartmentModel();
        $before = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $data   = $this->payload() + ['id' => $id];

        if (! $model->update($id, $data)) {
            return $this->backWithErrors(url_to('departments.index'), $model->errors(), 'edit:' . $id);
        }

        [$old, $new] = $this->diff($before, $data);

        if ($new !== []) {
            $this->audit('department.update', 'department', $id, "Memperbarui departemen {$data['name']}", $old, $new);
        }

        return redirect()->route('departments.index')->with('success', "Departemen {$data['name']} berhasil diperbarui.");
    }

    public function delete(int $id): RedirectResponse
    {
        $model      = new DepartmentModel();
        $department = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $db         = db_connect();

        $employees = $db->table('employees')->where('department_id', $id)->countAllResults();
        $positions = $db->table('positions')->where('department_id', $id)->countAllResults();

        if ($employees > 0 || $positions > 0) {
            return redirect()->route('departments.index')->with('error', sprintf(
                'Departemen %s tidak bisa dihapus karena masih memiliki %d jabatan dan %d karyawan.',
                $department['name'],
                $positions,
                $employees,
            ));
        }

        $model->delete($id);
        $this->audit('department.delete', 'department', $id, "Menghapus departemen {$department['name']}", $department);

        return redirect()->route('departments.index')->with('success', "Departemen {$department['name']} telah dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $head = (string) $this->request->getPost('head_employee_id');

        return [
            'code'             => strtoupper(trim((string) $this->request->getPost('code'))),
            'name'             => trim((string) $this->request->getPost('name')),
            'description'      => trim((string) $this->request->getPost('description')) ?: null,
            'head_employee_id' => $head === '' ? null : (int) $head,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function employeeOptions(): array
    {
        $options = [];

        foreach ((new EmployeeModel())->active()->orderBy('full_name')->findAll() as $employee) {
            $options[$employee['id']] = $employee['full_name'] . ' · ' . $employee['nip'];
        }

        return $options;
    }
}
