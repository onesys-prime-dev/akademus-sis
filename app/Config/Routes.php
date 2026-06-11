<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

$routes->group('admin', ['filter' => 'group:admin'], function ($routes) {
    $routes->get('dashboard', 'AdminController::index');
});

$routes->group('registrar', ['filter' => 'group:registrar'], function ($routes) {
    $routes->get('dashboard', 'RegistrarController::index');
});

$routes->group('faculty', ['filter' => 'group:faculty'], function ($routes) {
    $routes->get('dashboard', 'FacultyController::index');
});

$routes->group('student', ['filter' => 'group:student'], function ($routes) {
    $routes->get('dashboard', 'StudentController::index');
});

$routes->get('/dashboard', 'DashboardController::index', ['filter' => 'session']);

$routes->get('/profile', function () {

    $user = auth()->user();

    return json_encode([
        'id' => $user->id,
        'username' => $user->username,
        'groups' => $user->getGroups(),
    ]);

}, ['filter' => 'session']);

// AUTH
$routes->get('forgot-password'      , 'Auth\ForgotPasswordController::showForgotForm');
$routes->post('forgot-password'     , 'Auth\ForgotPasswordController::sendResetLink');

$routes->get('reset-password/(:any)', 'Auth\ForgotPasswordController::showResetForm/$1');
$routes->post('reset-password'      , 'Auth\ForgotPasswordController::resetPassword');