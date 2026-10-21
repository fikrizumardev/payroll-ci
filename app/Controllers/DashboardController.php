<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmployeeModel;

class DashboardController extends BaseController
{
    public function index(): string
    {
        return view('dashboard/index', [
            'title'          => 'Dashboard',
            'user'           => $this->currentUser(),
            'activeEmployee' => (new EmployeeModel())->active()->countAllResults(),
        ]);
    }
}
