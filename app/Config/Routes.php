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
// Khusus development: katalog komponen UI
// ---------------------------------------------------------------------
if (ENVIRONMENT === 'development') {
    $routes->group('dev', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('ui-kit', 'DevController::uiKit', ['as' => 'dev.ui-kit']);
        $routes->post('ui-kit/confirm', 'DevController::confirmDemo', ['as' => 'dev.ui-kit.confirm']);
    });
}
