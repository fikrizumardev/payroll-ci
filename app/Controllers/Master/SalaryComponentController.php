<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\SalaryComponentModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Komponen gaji. Komponen sistem (is_system) dipakai langsung oleh engine payroll:
 * tipe, cara hitung, dan sifat pajaknya terkunci, serta tidak bisa dihapus/nonaktif.
 */
class SalaryComponentController extends BaseController
{
    /** Cara hitung yang boleh dipilih untuk komponen buatan pengguna. */
    private const USER_CALC_TYPES = ['fixed', 'daily', 'manual'];

    public function index(): string
    {
        $components = (new SalaryComponentModel())->orderBy('type')->orderBy('sort_order')->findAll();
        $usage      = array_map('intval', array_column(
            db_connect()->table('employee_components')->select('salary_component_id, COUNT(*) AS total')->groupBy('salary_component_id')->get()->getResultArray(),
            'total',
            'salary_component_id',
        ));

        $grouped = array_fill_keys(array_keys(SalaryComponentModel::TYPES), []);

        foreach ($components as $component) {
            $component['usage']            = $usage[$component['id']] ?? 0;
            $grouped[$component['type']][] = $component;
        }

        return view('master/salary_components/index', [
            'title'       => 'Komponen Gaji',
            'breadcrumbs' => [['label' => 'Master Data'], ['label' => 'Komponen Gaji']],
            'grouped'     => $grouped,
            'canManage'   => $this->canManage(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $model = new SalaryComponentModel();
        $data  = $this->payload() + ['is_system' => 0];

        if (! in_array($data['type'], ['earning', 'deduction'], true) || ! in_array($data['calc_type'], self::USER_CALC_TYPES, true)) {
            return $this->backWithErrors(url_to('salary-components.index'), ['calc_type' => 'Komponen baru hanya boleh bertipe pendapatan/potongan dengan cara hitung tetap, harian, atau manual.'], 'create');
        }

        if (! $model->insert($data)) {
            return $this->backWithErrors(url_to('salary-components.index'), $model->errors(), 'create');
        }

        $id = (int) $model->getInsertID();
        $this->audit('salary_component.create', 'salary_component', $id, "Menambahkan komponen gaji {$data['name']} ({$data['code']})", null, $data);

        return redirect()->route('salary-components.index')->with('success', "Komponen {$data['name']} berhasil ditambahkan.");
    }

    public function update(int $id): RedirectResponse
    {
        $model  = new SalaryComponentModel();
        $before = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $data   = $this->payload();

        if ($before['is_system']) {
            // Hanya atribut tampilan & nominal default yang boleh diubah
            $data = array_intersect_key($data, array_flip(['name', 'default_amount', 'sort_order', 'description'])) + [
                'code'      => $before['code'],
                'type'      => $before['type'],
                'calc_type' => $before['calc_type'],
            ];
        } elseif (! in_array($data['calc_type'], self::USER_CALC_TYPES, true)) {
            return $this->backWithErrors(url_to('salary-components.index'), ['calc_type' => 'Cara hitung tidak valid.'], 'edit:' . $id);
        }

        $data['id'] = $id;

        if (! $model->update($id, $data)) {
            return $this->backWithErrors(url_to('salary-components.index'), $model->errors(), 'edit:' . $id);
        }

        [$old, $new] = $this->diff($before, $data);

        if ($new !== []) {
            $this->audit('salary_component.update', 'salary_component', $id, "Memperbarui komponen gaji {$before['name']}", $old, $new);
        }

        return redirect()->route('salary-components.index')->with('success', "Komponen {$data['name']} berhasil diperbarui.");
    }

    public function delete(int $id): RedirectResponse
    {
        $model     = new SalaryComponentModel();
        $component = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();

        if ($component['is_system']) {
            return redirect()->route('salary-components.index')->with('error', "{$component['name']} adalah komponen sistem dan tidak bisa dihapus.");
        }

        $used = db_connect()->table('employee_components')->where('salary_component_id', $id)->countAllResults();

        if ($used > 0) {
            return redirect()->route('salary-components.index')->with('error', "{$component['name']} masih dipakai oleh {$used} karyawan. Nonaktifkan komponen bila tidak ingin dipakai lagi.");
        }

        $model->delete($id);
        $this->audit('salary_component.delete', 'salary_component', $id, "Menghapus komponen gaji {$component['name']}", $component);

        return redirect()->route('salary-components.index')->with('success', "Komponen {$component['name']} telah dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $type = (string) $this->request->getPost('type');

        return [
            'code'           => strtoupper(trim((string) $this->request->getPost('code'))),
            'name'           => trim((string) $this->request->getPost('name')),
            'type'           => $type,
            'calc_type'      => (string) $this->request->getPost('calc_type'),
            'default_amount' => (int) preg_replace('/\D/', '', (string) $this->request->getPost('default_amount')),
            'taxable'        => $this->request->getPost('taxable') ? 1 : 0,
            'is_bpjs_base'   => $type === 'earning' && $this->request->getPost('is_bpjs_base') ? 1 : 0,
            'is_active'      => $this->request->getPost('is_active') ? 1 : 0,
            'sort_order'     => (int) $this->request->getPost('sort_order'),
            'description'    => trim((string) $this->request->getPost('description')) ?: null,
        ];
    }
}
