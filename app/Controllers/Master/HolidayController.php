<?php

declare(strict_types=1);

namespace App\Controllers\Master;

use App\Controllers\BaseController;
use App\Models\HolidayModel;
use App\Services\PayrollPeriodGuard;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

class HolidayController extends BaseController
{
    public function index(): string
    {
        $year     = (int) ($this->request->getGet('year') ?: date('Y'));
        $year     = max(2000, min(2100, $year));
        $holidays = (new HolidayModel())->where('date >=', "{$year}-01-01")->where('date <=', "{$year}-12-31")->orderBy('date')->findAll();
        $years    = array_map('intval', array_column(db_connect()->query('SELECT DISTINCT YEAR(date) AS y FROM holidays ORDER BY y')->getResultArray(), 'y'));

        return view('master/holidays/index', [
            'title'       => 'Hari Libur',
            'breadcrumbs' => [['label' => 'Master Data'], ['label' => 'Hari Libur']],
            'year'        => $year,
            'years'       => array_values(array_unique([...$years, (int) date('Y'), $year])),
            'holidays'    => $holidays,
            'lockedUntil' => PayrollPeriodGuard::make()->lastClosedDate(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $model = new HolidayModel();
        $data  = $this->payload();

        if (($error = $this->lockedPeriodError($data['date'])) !== null) {
            return $this->backWithErrors($this->indexUrl($data['date']), ['date' => $error], 'create');
        }

        if (! $model->insert($data)) {
            return $this->backWithErrors($this->indexUrl($data['date']), $model->errors(), 'create');
        }

        $this->audit('holiday.create', 'holiday', (int) $model->getInsertID(), "Menambahkan hari libur {$data['name']} (" . tanggal_id($data['date']) . ')', null, $data);

        return redirect()->to($this->indexUrl($data['date']))->with('success', 'Hari libur ' . tanggal_id($data['date'], 'long') . ' ditambahkan.');
    }

    public function update(int $id): RedirectResponse
    {
        $model  = new HolidayModel();
        $before = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();
        $data   = $this->payload() + ['id' => $id];

        foreach ([$before['date'], $data['date']] as $date) {
            if (($error = $this->lockedPeriodError($date)) !== null) {
                return $this->backWithErrors($this->indexUrl($before['date']), ['date' => $error], 'edit:' . $id);
            }
        }

        if (! $model->update($id, $data)) {
            return $this->backWithErrors($this->indexUrl($before['date']), $model->errors(), 'edit:' . $id);
        }

        [$old, $new] = $this->diff($before, $data);
        $this->audit('holiday.update', 'holiday', $id, "Memperbarui hari libur {$data['name']}", $old, $new);

        return redirect()->to($this->indexUrl($data['date']))->with('success', 'Hari libur berhasil diperbarui.');
    }

    public function delete(int $id): RedirectResponse
    {
        $model   = new HolidayModel();
        $holiday = $model->find($id) ?? throw PageNotFoundException::forPageNotFound();

        if (($error = $this->lockedPeriodError($holiday['date'])) !== null) {
            return redirect()->to($this->indexUrl($holiday['date']))->with('error', $error);
        }

        $model->delete($id);
        $this->audit('holiday.delete', 'holiday', $id, "Menghapus hari libur {$holiday['name']} (" . tanggal_id($holiday['date']) . ')', $holiday);

        return redirect()->to($this->indexUrl($holiday['date']))->with('success', "Hari libur {$holiday['name']} dihapus.");
    }

    /**
     * Hari libur memengaruhi jumlah hari kerja; tanggal di periode payroll
     * yang sudah disetujui/dibayar/terkunci tidak boleh diubah.
     */
    private function lockedPeriodError(string $date): ?string
    {
        return PayrollPeriodGuard::make()->errorFor($date);
    }

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'date' => (string) $this->request->getPost('date'),
            'name' => trim((string) $this->request->getPost('name')),
            'type' => (string) $this->request->getPost('type'),
        ];
    }

    private function indexUrl(string $date): string
    {
        $year = preg_match('/^\d{4}/', $date) ? substr($date, 0, 4) : date('Y');

        return url_to('holidays.index') . '?year=' . $year;
    }
}
