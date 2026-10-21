<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\EmployeeModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Halaman bantu developer. Route hanya didaftarkan di environment development.
 */
class DevController extends BaseController
{
    public function uiKit(): string
    {
        $this->ensureDevelopment();

        $employees = new EmployeeModel();
        $rows      = $employees->withRelations()
            ->orderBy('employees.nip')
            ->paginate(8, 'default');

        return view('dev/ui_kit', [
            'title'       => 'UI Kit',
            'breadcrumbs' => [['label' => 'Developer'], ['label' => 'UI Kit']],
            'employees'   => $rows,
            'pager'       => $employees->pager,
            'departments' => (new DepartmentModel())->options(),
        ]);
    }

    public function confirmDemo(): RedirectResponse
    {
        $this->ensureDevelopment();

        $note = trim((string) $this->request->getPost('note'));

        return redirect()->route('dev.ui-kit')->with('success', 'Aksi demo dikonfirmasi' . ($note !== '' ? ' dengan catatan: “' . $note . '”' : '') . '.');
    }

    private function ensureDevelopment(): void
    {
        if (ENVIRONMENT !== 'development') {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
