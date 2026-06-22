<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

$routes->group('admin', ['filter' => 'group:admin'], function ($routes) {
    $routes->get('dashboard', 'Admin\AdminController::index');

    // create programs
    $routes->get('programs'               , 'Admin\ProgramController::index');
    $routes->get('programs/create'        , 'Admin\ProgramController::create');
    $routes->post('programs/store'        , 'Admin\ProgramController::store');

    $routes->get('programs/edit/(:num)'   , 'Admin\ProgramController::edit/$1');
    $routes->post('programs/update/(:num)', 'Admin\ProgramController::update/$1');

    // $routes->post('programs/delete/(:num)', 'Admin\ProgramController::delete/$1');

    $routes->post('programs/toggle-status/(:num)' , 'Admin\ProgramController::toggleStatus/$1');

    // create subjects
    $routes->get('subjects'               , 'Admin\SubjectController::index');
    $routes->get('subjects/create'        , 'Admin\SubjectController::create');
    $routes->post('subjects/store'        , 'Admin\SubjectController::store');

    $routes->get('subjects/edit/(:num)'   , 'Admin\SubjectController::edit/$1');
    $routes->post('subjects/update/(:num)', 'Admin\SubjectController::update/$1');

    // $routes->post('subjects/delete/(:num)', 'Admin\SubjectController::delete/$1');

    $routes->post('subjects/toggle-status/(:num)', 'Admin\SubjectController::toggleStatus/$1');

    $routes->get('curriculum'               , 'Admin\CurriculumController::index');
    $routes->get('curriculum/create'        , 'Admin\CurriculumController::create');
    $routes->post('curriculum/store'        , 'Admin\CurriculumController::store');

    $routes->get('curriculum/edit/(:num)'   , 'Admin\CurriculumController::edit/$1');
    $routes->get('curriculum/update(:num)'  , 'Admin\CurriculumController::update/$1');

    $routes->post('curriculum/toggle/(:num)', 'Admin\CurriculumController::toggleStatus/$1');
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

$routes->group('api', function($routes) {
    $routes->post('students/promote'   , 'API\StudentAPIController::promote');

    // DEV ONLY
    $routes->get('students/promote', 'API\StudentAPIController::promote');
});

