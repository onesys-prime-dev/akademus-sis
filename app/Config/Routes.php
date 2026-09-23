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
    $routes->post('curriculum/publish/(:num)', 'Admin\CurriculumController::publish/$1');
    $routes->post('curriculum/new-version/(:num)', 'Admin\CurriculumController::newVersion/$1');

    $routes->get('curriculum-subjects'       , 'Admin\CurriculumSubjectController::index');
    $routes->get('curriculum-subjects/create', 'Admin\CurriculumSubjectController::create');
    $routes->post('curriculum-subjects/store', 'Admin\CurriculumSubjectController::store');

    $routes->get('curriculum-subjects/edit/(:num)'    , 'Admin\CurriculumSubjectController::edit/$1');
    $routes->post('curriculum-subjects/update/(:num)' , 'Admin\CurriculumSubjectController::update/$1');
    $routes->post('curriculum-subjects/toggle/(:num)' , 'Admin\CurriculumSubjectController::toggleStatus/$1');
    $routes->post('curriculum-subjects/delete/(:num)' , 'Admin\CurriculumSubjectController::destroy/$1');

    // departments (colleges, dean-headed)
    $routes->get('departments'                        , 'Admin\DepartmentController::index');
    $routes->post('departments/store'                 , 'Admin\DepartmentController::store');
    $routes->get('departments/(:num)'                 , 'Admin\DepartmentController::show/$1');
    $routes->post('departments/(:num)/update'         , 'Admin\DepartmentController::update/$1');
    $routes->post('departments/(:num)/sub-departments', 'Admin\DepartmentController::storeSubDepartment/$1');
    $routes->post('departments/store-dean/(:num)'     , 'Admin\DepartmentController::storeDean/$1');
    $routes->post('departments/(:num)/delete'         , 'Admin\DepartmentController::delete/$1');

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
    $routes->post('floors/store'            , 'Admin\RoomController::storeFloor');
    $routes->post('rooms/update/(:num)'     , 'Admin\RoomController::update/$1');
    $routes->post('rooms/delete/(:num)'     , 'Admin\RoomController::delete/$1');
    $routes->post('rooms/toggle-status/(:num)', 'Admin\RoomController::toggleStatus/$1');

    // academic years
    $routes->get('academic-years'                 , 'Admin\AcademicYearController::index');
    $routes->post('academic-years/store'          , 'Admin\AcademicYearController::store');
    $routes->post('academic-years/set-active/(:num)', 'Admin\AcademicYearController::setActive/$1');

    // user accounts
    $routes->get('users'                       , 'Admin\UserController::index');
    $routes->post('users/store'                , 'Admin\UserController::store');
    $routes->post('users/update/(:num)'        , 'Admin\UserController::update/$1');
    $routes->post('users/reset-password/(:num)', 'Admin\UserController::resetPassword/$1');
    $routes->post('users/activate/(:num)'      , 'Admin\UserController::activate/$1');
    $routes->post('users/deactivate/(:num)'    , 'Admin\UserController::deactivate/$1');

    // roles / permissions overview
    $routes->get('roles', 'Admin\RoleController::index');

    // academic scheduling rules (unit->hour conversion, workload caps)
    $routes->get('academic-settings'        , 'Admin\AcademicSettingsController::index');
    $routes->post('academic-settings/update', 'Admin\AcademicSettingsController::update');

    // student records
    $routes->get('students'                     , 'Admin\StudentRecordController::index');
    $routes->get('students/(:num)'              , 'Admin\StudentRecordController::show/$1');
    $routes->post('students/(:num)/status'      , 'Admin\StudentRecordController::changeStatus/$1');
    $routes->post('students/(:num)/curriculum'  , 'Admin\StudentRecordController::assignCurriculum/$1');

    // grading scale (numeric GWA / percentage / letter — one active at a time)
    $routes->get('grading-scales'               , 'Admin\GradingScaleController::index');
    $routes->post('grading-scales/store'        , 'Admin\GradingScaleController::store');
    $routes->post('grading-scales/set-active/(:num)', 'Admin\GradingScaleController::setActive/$1');

    // notifications (shared controller, scoped by auth()->id())
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/mark-read', 'NotificationController::markRead');
    $routes->post('notifications/mark-all-read', 'NotificationController::markAllRead');
});

$routes->group('registrar', ['filter' => 'group:registrar'], function ($routes) {
    $routes->get('dashboard', 'RegistrarController::index');
});

$routes->group('faculty', ['filter' => 'group:faculty'], function ($routes) {
    $routes->get('dashboard', 'TeachingLoads::index');
    $routes->get('loads/state', 'TeachingLoads::state');
    $routes->post('loads/swap/request', 'TeachingLoads::requestSwap');
    $routes->post('loads/swap/respond', 'TeachingLoads::respondSwap');
    $routes->post('loads/swap/cancel', 'TeachingLoads::cancelSwap');

    // pre-semester availability & subject preferences
    $routes->get('availability', 'Faculty\AvailabilityController::edit');
    $routes->post('availability/update', 'Faculty\AvailabilityController::update');
    $routes->post('preferences/update', 'Faculty\AvailabilityController::updatePreferences');

    // profile
    $routes->get('profile', 'Faculty\ProfileController::edit');
    $routes->post('profile/update', 'Faculty\ProfileController::updateProfile');
    $routes->post('profile/password', 'Faculty\ProfileController::updatePassword');

    // read-only weekly schedule (this faculty member's own plotted classes)
    $routes->get('schedule', 'Faculty\ScheduleController::index');

    // request for change on an official assignment
    $routes->post('change-requests/store', 'Faculty\ChangeRequestController::store');
    $routes->post('change-requests/cancel', 'Faculty\ChangeRequestController::cancel');

    // notifications
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/mark-read', 'NotificationController::markRead');
    $routes->post('notifications/mark-all-read', 'NotificationController::markAllRead');

    // grading (my classes -> enter/submit grades -> request a correction once locked)
    $routes->get('grading', 'Faculty\GradingController::index');
    $routes->get('grading/class', 'Faculty\GradingController::show');
    $routes->post('grading/save', 'Faculty\GradingController::saveDraft');
    $routes->post('grading/submit', 'Faculty\GradingController::submit');
    $routes->post('grading/request-change', 'Faculty\GradingController::requestChange');
});

$routes->group('student', ['filter' => 'group:student'], function ($routes) {
    $routes->get('dashboard', 'StudentController::index');
    $routes->get('profile', 'StudentController::profile');
    $routes->post('profile/update', 'StudentController::updateProfile');
    $routes->post('profile/password', 'StudentController::updatePassword');
    $routes->get('schedule', 'StudentEnrollment::schedule');
    $routes->post('enroll', 'StudentEnrollment::enroll');
    $routes->post('enroll/block', 'StudentEnrollment::enrollBlock');
    $routes->get('grades', 'StudentGrades::index');

    // notifications (shared controller, scoped by auth()->id())
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/mark-read', 'NotificationController::markRead');
    $routes->post('notifications/mark-all-read', 'NotificationController::markAllRead');
});

$routes->group('chairperson', ['filter' => 'group:chairperson'], function ($routes) {
    $routes->get('dashboard', 'ChairpersonController::index');
    $routes->get('overview', 'ChairpersonController::overview');
    $routes->get('profile', 'ChairpersonController::profile');
    $routes->post('profile/update', 'ChairpersonController::updateProfile');
    $routes->post('profile/password', 'ChairpersonController::updatePassword');
    $routes->post('schedule/sync', 'ChairpersonController::sync');
    $routes->post('schedule/faculty-override', 'ChairpersonController::recordFacultyOverride');
    $routes->get('schedule/state', 'ChairpersonController::state');
    $routes->post('curriculum-preset', 'ChairpersonController::savePreset');
    $routes->post('curriculum-preset/subject-type', 'ChairpersonController::updateSubjectType');
    $routes->post('curriculum-preset/copy', 'ChairpersonController::copyCurriculum');
    $routes->get('curriculum', 'ChairpersonController::curriculum');
    $routes->post('swaps/approve', 'ChairpersonController::approveSwap');
    $routes->post('swaps/reject', 'ChairpersonController::rejectSwap');
    $routes->post('change-requests/approve', 'ChairpersonController::approveChangeRequest');
    $routes->post('change-requests/reject', 'ChairpersonController::rejectChangeRequest');
    $routes->post('change-requests/review', 'ChairpersonController::reviewChangeRequest');

    // notifications
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/mark-read', 'NotificationController::markRead');
    $routes->post('notifications/mark-all-read', 'NotificationController::markAllRead');
    $routes->post('subjects/add', 'ChairpersonController::addSubject');
    $routes->post('subjects/update', 'ChairpersonController::updateSubject');
    $routes->post('subjects/delete', 'ChairpersonController::deleteSubject');
    $routes->post('sections/capacity', 'ChairpersonController::setSectionCapacity');
    $routes->get('sections', 'ChairpersonController::sections');
    $routes->post('sections/create', 'ChairpersonController::createSection');
    $routes->post('sections/delete', 'ChairpersonController::deleteSection');
    $routes->post('sections/assign-student', 'ChairpersonController::assignStudent');
    $routes->post('sections/unassign-student', 'ChairpersonController::unassignStudent');
    $routes->get('faculty-panel', 'ChairpersonController::facultyPanel');

    // section merging (course offerings)
    $routes->get('offerings'                , 'Chairperson\OfferingController::index');
    $routes->post('offerings/create'        , 'Chairperson\OfferingController::create');
    $routes->post('offerings/unmerge'       , 'Chairperson\OfferingController::unmergeSection');

    // grade approvals: lock a faculty-submitted class, decide correction requests
    $routes->get('grade-approvals'                     , 'Chairperson\GradeApprovalController::index');
    $routes->get('grade-approvals/class'                , 'Chairperson\GradeApprovalController::show');
    $routes->post('grade-approvals/lock'                , 'Chairperson\GradeApprovalController::lock');
    $routes->post('grade-approvals/change-requests/approve', 'Chairperson\GradeApprovalController::approveChange');
    $routes->post('grade-approvals/change-requests/reject'  , 'Chairperson\GradeApprovalController::rejectChange');
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
});

