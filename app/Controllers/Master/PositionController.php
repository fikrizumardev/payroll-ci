<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class PositionController extends BaseController
{
    public function index(): string
    {
        $positions = (new PositionModel())->withDepartment();

        // Rentang gaji pokok aktual karyawan aktif per jabatan
        $stats = [];

        foreach (db_connect()->table('employees')
            ->select('position_id, COUNT(*) AS total, MIN(basic_salary) AS min_salary, MAX(basic_salary) AS max_salary')
            ->where('status', 'active')->where('deleted_at', null)
            ->groupBy('position_id')->get()->getResultArray() as $row) {
            $stats[(int) $row['position_id']] = $row;
        }

        $grouped = [];

        foreach ($positions as $position) {
            $position['employee_count'] = (int) ($stats[$position['id']]['total'] ?? 0);
            $position['min_salary']     = isset($stats[$position['id']]) ? (int) $stats[$position['id']]['min_salary'] : null;
            $position['max_salary']     = isset($stats[$position['id']]) ? (int) $stats[$position['id']]['max_salary'] : null;

            $grouped[$position['department_id']]['name']    = $position['department_name'];
            $grouped[$position['department_id']]['code']    = $position['department_code'];
            $grouped[$position['department_id']]['items'][] = $position;
        }

        return view('master/positions/index', [
            'title'       => 'Jabatan',
            'breadcrumbs' => [['label' => 'Master Data'], ['label' => 'Jabatan']],
            'grouped'     => $grouped,
            'total'       => count($positions),
            'departments' => (new DepartmentModel())->options(),
            'canManage'   => $this->canManage(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $model = new PositionModel();
        $data  = $this->payload();

        if (! $model->insert($data)) {
            return $this->backWithErrors(url_to('positions.index'), $model->errors(), 'create');
        }

        $id = (int) $model->getInsertID();
        $this->audit('position.create', 'position', $id, "Menambahkan jabatan {$data['name']} ({$data['code']})", null, $data);

        return redirect()->route('positions.index')->with('success', "Jabatan {$data['name']} berhasil ditambahkan.");
    }

    public function update(int $id): RedirectResponse
    {
        $model  = new PositionModel();
        $before = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $data   = $this->payload() + ['id' => $id];

        if (! $model->update($id, $data)) {
            return $this->backWithErrors(url_to('positions.index'), $model->errors(), 'edit:' . $id);
        }

        [$old, $new] = $this->diff($before, $data);

        if ($new !== []) {
            $this->audit('position.update', 'position', $id, "Memperbarui jabatan {$data['name']}", $old, $new);
        }

        return redirect()->route('positions.index')->with('success', "Jabatan {$data['name']} berhasil diperbarui. Tunjangan karyawan yang sudah ada tidak berubah otomatis.");
    }

    public function delete(int $id): RedirectResponse
    {
        $model    = new PositionModel();
        $position = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $used     = db_connect()->table('employees')->where('position_id', $id)->countAllResults();

        if ($used > 0) {
            return redirect()->route('positions.index')->with('error', "Jabatan {$position['name']} masih dipakai oleh {$used} karyawan sehingga tidak bisa dihapus.");
        }

        $model->delete($id);
        $this->audit('position.delete', 'position', $id, "Menghapus jabatan {$position['name']}", $position);

        return redirect()->route('positions.index')->with('success', "Jabatan {$position['name']} telah dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $digits = fn (string $field): int => (int) preg_replace('/\D/', '', (string) $this->request->getPost($field));

        return [
            'department_id'           => (int) $this->request->getPost('department_id') ?: null,
            'code'                    => strtoupper(trim((string) $this->request->getPost('code'))),
            'name'                    => trim((string) $this->request->getPost('name')),
            'grade'                   => (int) $this->request->getPost('grade') ?: null,
            'base_salary'             => $digits('base_salary'),
            'position_allowance'      => $digits('position_allowance'),
            'communication_allowance' => $digits('communication_allowance'),
            'overtime_eligible'       => $this->request->getPost('overtime_eligible') ? 1 : 0,
        ];
    }
}
