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
    $routes->post('curriculum/update/(:num)', 'Admin\CurriculumController::update/$1');

    $routes->post('curriculum/toggle/(:num)', 'Admin\CurriculumController::toggleStatus/$1');

    $routes->get('curriculum-subjects'       , 'Admin\CurriculumSubjectController::index');
    $routes->get('curriculum-subjects/create', 'Admin\CurriculumSubjectController::create');
    $routes->post('curriculum-subjects/store', 'Admin\CurriculumSubjectController::store');

    // departments (colleges, dean-headed)
    $routes->get('departments'                        , 'Admin\DepartmentController::index');
    $routes->post('departments/store'                 , 'Admin\DepartmentController::store');
    $routes->get('departments/(:num)'                 , 'Admin\DepartmentController::show/$1');
    $routes->post('departments/(:num)/update'         , 'Admin\DepartmentController::update/$1');
    $routes->post('departments/(:num)/sub-departments', 'Admin\DepartmentController::storeSubDepartment/$1');
    $routes->post('departments/store-dean/(:num)'     , 'Admin\DepartmentController::storeDean/$1');

    // sub-departments (departments, chairperson-headed, nested under a college)
    $routes->get('sub-departments/(:num)'              , 'Admin\SubDepartmentController::show/$1');
    $routes->post('sub-departments/(:num)/update'      , 'Admin\SubDepartmentController::update/$1');
    $routes->post('sub-departments/(:num)/store-chair' , 'Admin\SubDepartmentController::storeChair/$1');
    $routes->post('sub-departments/(:num)/transfer'    , 'Admin\SubDepartmentController::transfer/$1');
    $routes->post('sub-departments/(:num)/programs'    , 'Admin\SubDepartmentController::addProgram/$1');
    $routes->post('sub-departments/(:num)/toggle-status', 'Admin\SubDepartmentController::toggleStatus/$1');
    $routes->post('sub-departments/(:num)/delete'      , 'Admin\SubDepartmentController::delete/$1');

    // faculty members (teaching staff) within a department
    $routes->post('sub-departments/(:num)/faculty'                       , 'Admin\SubDepartmentController::addFaculty/$1');
    $routes->post('sub-departments/(:num)/faculty/(:num)/update'         , 'Admin\SubDepartmentController::updateFaculty/$1/$2');
    $routes->post('sub-departments/(:num)/faculty/(:num)/toggle-status'  , 'Admin\SubDepartmentController::toggleFacultyStatus/$1/$2');
    $routes->post('sub-departments/(:num)/faculty/(:num)/delete'         , 'Admin\SubDepartmentController::deleteFaculty/$1/$2');

    // rooms
    $routes->get('rooms'                    , 'Admin\RoomController::index');
    $routes->post('rooms/store'             , 'Admin\RoomController::store');
    $routes->post('rooms/update/(:num)'     , 'Admin\RoomController::update/$1');
    $routes->post('rooms/delete/(:num)'     , 'Admin\RoomController::delete/$1');
    $routes->post('rooms/toggle-status/(:num)', 'Admin\RoomController::toggleStatus/$1');

    // academic years
    $routes->get('academic-years'                 , 'Admin\AcademicYearController::index');
    $routes->post('academic-years/store'          , 'Admin\AcademicYearController::store');
    $routes->post('academic-years/set-active/(:num)', 'Admin\AcademicYearController::setActive/$1');

    // user accounts
    $routes->get('users'                 , 'Admin\UserController::index');
    $routes->post('users/store'          , 'Admin\UserController::store');
    $routes->post('users/activate/(:num)', 'Admin\UserController::activate/$1');

    // roles / permissions overview
    $routes->get('roles', 'Admin\RoleController::index');
});

$routes->group('registrar', ['filter' => 'group:registrar'], function ($routes) {
    $routes->get('dashboard', 'RegistrarController::index');
});

$routes->group('faculty', ['filter' => 'group:faculty'], function ($routes) {
    $routes->get('dashboard', 'TeachingLoads::index');
    $routes->get('loads/state', 'TeachingLoads::state');
    $routes->post('loads/claim', 'TeachingLoads::claim');
    $routes->post('loads/swap/request', 'TeachingLoads::requestSwap');
    $routes->post('loads/swap/respond', 'TeachingLoads::respondSwap');
    $routes->post('loads/swap/cancel', 'TeachingLoads::cancelSwap');
});

$routes->group('student', ['filter' => 'group:student'], function ($routes) {
    $routes->get('dashboard', 'StudentController::index');
    $routes->get('profile', 'StudentController::profile');
});

$routes->group('chairperson', ['filter' => 'group:chairperson'], function ($routes) {
    $routes->get('dashboard', 'ChairpersonController::index');
    $routes->post('schedule/sync', 'ChairpersonController::sync');
    $routes->get('schedule/state', 'ChairpersonController::state');
    $routes->post('curriculum-preset', 'ChairpersonController::savePreset');
    $routes->post('swaps/approve', 'ChairpersonController::approveSwap');
    $routes->post('swaps/reject', 'ChairpersonController::rejectSwap');
    $routes->post('subjects/add', 'ChairpersonController::addSubject');
    $routes->post('subjects/update', 'ChairpersonController::updateSubject');
    $routes->post('subjects/delete', 'ChairpersonController::deleteSubject');
});

$routes->get('/dashboard', 'DashboardController::index', ['filter' => 'session']);

/*
$routes->get('/profile', function () {

    $user = auth()->user();

    return json_encode([
        'id' => $user->id,
        'username' => $user->username,
        'groups' => $user->getGroups(),
    ]);

}, ['filter' => 'session']);
*/

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

