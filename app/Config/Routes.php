<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', static fn () => redirect()->route('dashboard'));

// ---------------------------------------------------------------------
// Autentikasi (username + password)
// ---------------------------------------------------------------------
$routes->group('', ['filter' => 'guest'], static function (RouteCollection $routes): void {
    $routes->get('login', 'AuthController::login', ['as' => 'login']);
    $routes->post('login', 'AuthController::attempt', ['as' => 'login.attempt']);
});

$routes->post('logout', 'AuthController::logout', ['as' => 'logout', 'filter' => 'auth']);

// ---------------------------------------------------------------------
// Area yang wajib login
// ---------------------------------------------------------------------
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'DashboardController::index', ['as' => 'dashboard']);

    $routes->get('account/password', 'AccountController::password', ['as' => 'account.password']);
    $routes->post('account/password', 'AccountController::updatePassword', ['as' => 'account.password.update']);
});

// ---------------------------------------------------------------------
// Master data — lihat: HR & Finance · ubah: HR (Super Admin selalu boleh)
// ---------------------------------------------------------------------
$viewMaster   = ['filter' => ['auth', 'role:hr,finance']];
$manageMaster = ['filter' => ['auth', 'role:hr']];

$routes->group('departments', $viewMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'Master\DepartmentController::index', ['as' => 'departments.index']);
});
$routes->group('departments', $manageMaster, static function (RouteCollection $routes): void {
    $routes->post('/', 'Master\DepartmentController::store', ['as' => 'departments.store']);
    $routes->post('(:num)', 'Master\DepartmentController::update/$1', ['as' => 'departments.update']);
    $routes->post('(:num)/delete', 'Master\DepartmentController::delete/$1', ['as' => 'departments.delete']);
});

$routes->group('positions', $viewMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'Master\PositionController::index', ['as' => 'positions.index']);
});
$routes->group('positions', $manageMaster, static function (RouteCollection $routes): void {
    $routes->post('/', 'Master\PositionController::store', ['as' => 'positions.store']);
    $routes->post('(:num)', 'Master\PositionController::update/$1', ['as' => 'positions.update']);
    $routes->post('(:num)/delete', 'Master\PositionController::delete/$1', ['as' => 'positions.delete']);
});

$routes->group('employees', $manageMaster, static function (RouteCollection $routes): void {
    $routes->get('create', 'Master\EmployeeController::create', ['as' => 'employees.create']);
    $routes->post('/', 'Master\EmployeeController::store', ['as' => 'employees.store']);
    $routes->get('(:num)/edit', 'Master\EmployeeController::edit/$1', ['as' => 'employees.edit']);
    $routes->post('(:num)', 'Master\EmployeeController::update/$1', ['as' => 'employees.update']);
    $routes->post('(:num)/resign', 'Master\EmployeeController::resign/$1', ['as' => 'employees.resign']);
    $routes->post('(:num)/components', 'Master\EmployeeController::updateComponents/$1', ['as' => 'employees.components']);
});
$routes->group('employees', $viewMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'Master\EmployeeController::index', ['as' => 'employees.index']);
    $routes->get('export', 'Master\EmployeeController::export', ['as' => 'employees.export']);
    $routes->get('(:num)', 'Master\EmployeeController::show/$1', ['as' => 'employees.show']);
});

$routes->group('salary-components', $viewMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'Master\SalaryComponentController::index', ['as' => 'salary-components.index']);
});
$routes->group('salary-components', $manageMaster, static function (RouteCollection $routes): void {
    $routes->post('/', 'Master\SalaryComponentController::store', ['as' => 'salary-components.store']);
    $routes->post('(:num)', 'Master\SalaryComponentController::update/$1', ['as' => 'salary-components.update']);
    $routes->post('(:num)/delete', 'Master\SalaryComponentController::delete/$1', ['as' => 'salary-components.delete']);
});

$routes->group('holidays', $manageMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'Master\HolidayController::index', ['as' => 'holidays.index']);
    $routes->post('/', 'Master\HolidayController::store', ['as' => 'holidays.store']);
    $routes->post('(:num)', 'Master\HolidayController::update/$1', ['as' => 'holidays.update']);
    $routes->post('(:num)/delete', 'Master\HolidayController::delete/$1', ['as' => 'holidays.delete']);
});

// ---------------------------------------------------------------------
// Kehadiran, lembur, pinjaman — lihat: HR & Finance · kelola: HR
// (persetujuan pinjaman: Finance). Super Admin selalu boleh.
// ---------------------------------------------------------------------
$routes->group('attendance', $manageMaster, static function (RouteCollection $routes): void {
    $routes->post('/', 'AttendanceController::save', ['as' => 'attendance.save']);
    $routes->get('import', 'AttendanceController::import', ['as' => 'attendance.import']);
    $routes->get('import/template', 'AttendanceController::template', ['as' => 'attendance.import.template']);
    $routes->post('import', 'AttendanceController::upload', ['as' => 'attendance.import.upload']);
    $routes->post('import/commit', 'AttendanceController::commit', ['as' => 'attendance.import.commit']);
    $routes->post('import/cancel', 'AttendanceController::cancel', ['as' => 'attendance.import.cancel']);
});
$routes->group('attendance', $viewMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'AttendanceController::index', ['as' => 'attendance.index']);
    $routes->get('recap', 'AttendanceController::recap', ['as' => 'attendance.recap']);
});

$routes->group('overtime', $viewMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'OvertimeController::index', ['as' => 'overtime.index']);
});
$routes->group('overtime', $manageMaster, static function (RouteCollection $routes): void {
    $routes->post('/', 'OvertimeController::store', ['as' => 'overtime.store']);
    $routes->post('review', 'OvertimeController::review', ['as' => 'overtime.review']);
    $routes->post('(:num)', 'OvertimeController::update/$1', ['as' => 'overtime.update']);
    $routes->post('(:num)/delete', 'OvertimeController::delete/$1', ['as' => 'overtime.delete']);
});

$routes->group('loans', $viewMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'LoanController::index', ['as' => 'loans.index']);
    $routes->get('(:num)', 'LoanController::show/$1', ['as' => 'loans.show']);
});
$routes->group('loans', $manageMaster, static function (RouteCollection $routes): void {
    $routes->post('/', 'LoanController::store', ['as' => 'loans.store']);
    $routes->post('(:num)', 'LoanController::update/$1', ['as' => 'loans.update']);
    $routes->post('(:num)/delete', 'LoanController::delete/$1', ['as' => 'loans.delete']);
});
$routes->group('loans', ['filter' => ['auth', 'role:finance']], static function (RouteCollection $routes): void {
    $routes->post('(:num)/approve', 'LoanController::approve/$1', ['as' => 'loans.approve']);
    $routes->post('(:num)/reject', 'LoanController::reject/$1', ['as' => 'loans.reject']);
});

// ---------------------------------------------------------------------
// Payroll — lihat: HR & Finance · generate/ajukan/penyesuaian: HR ·
// setujui/tolak/bayar/kunci/ekspor bank: Finance. Super Admin selalu boleh.
// ---------------------------------------------------------------------
$routes->group('payroll', $viewMaster, static function (RouteCollection $routes): void {
    $routes->get('/', 'PayrollController::index', ['as' => 'payroll.index']);
    $routes->get('(:num)', 'PayrollController::show/$1', ['as' => 'payroll.show']);
    $routes->get('(:num)/employees/(:num)', 'PayrollController::employee/$1/$2', ['as' => 'payroll.employee']);
});
$routes->group('payroll', $manageMaster, static function (RouteCollection $routes): void {
    $routes->post('/', 'PayrollController::store', ['as' => 'payroll.store']);
    $routes->post('(:num)/generate', 'PayrollController::generate/$1', ['as' => 'payroll.generate']);
    $routes->post('(:num)/submit', 'PayrollController::submit/$1', ['as' => 'payroll.submit']);
    $routes->post('(:num)/employees/(:num)/adjustments', 'PayrollController::storeAdjustment/$1/$2', ['as' => 'payroll.adjustments.store']);
    $routes->post('(:num)/adjustments/(:num)/delete', 'PayrollController::deleteAdjustment/$1/$2', ['as' => 'payroll.adjustments.delete']);
});
$routes->group('payroll', ['filter' => ['auth', 'role:finance']], static function (RouteCollection $routes): void {
    $routes->post('(:num)/approve', 'PayrollController::approve/$1', ['as' => 'payroll.approve']);
    $routes->post('(:num)/reject', 'PayrollController::reject/$1', ['as' => 'payroll.reject']);
    $routes->post('(:num)/pay', 'PayrollController::pay/$1', ['as' => 'payroll.pay']);
    $routes->post('(:num)/lock', 'PayrollController::lock/$1', ['as' => 'payroll.lock']);
    $routes->get('(:num)/export/bank', 'PayrollController::exportBank/$1', ['as' => 'payroll.export.bank']);
});

// Pengaturan — lihat: HR & Finance · ubah: Super Admin
$routes->get('settings', 'SettingController::index', ['as' => 'settings.index', 'filter' => ['auth', 'role:hr,finance']]);
$routes->post('settings/(:segment)', 'SettingController::update/$1', ['as' => 'settings.update', 'filter' => ['auth', 'role:superadmin']]);

// ---------------------------------------------------------------------
// Khusus development: katalog komponen UI
// ---------------------------------------------------------------------
if (ENVIRONMENT === 'development') {
    $routes->group('dev', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('ui-kit', 'DevController::uiKit', ['as' => 'dev.ui-kit']);
        $routes->post('ui-kit/confirm', 'DevController::confirmDemo', ['as' => 'dev.ui-kit.confirm']);
    });
}
