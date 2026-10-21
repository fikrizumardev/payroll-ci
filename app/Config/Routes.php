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
