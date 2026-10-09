<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Login is routed to our subclass (see App\Controllers\Auth\LoginController).
service('auth')->routes($routes, ['except' => ['login']]);
$routes->get('login', '\App\Controllers\Auth\LoginController::loginView', ['as' => 'login']);
$routes->post('login', '\App\Controllers\Auth\LoginController::loginAction');

// Student records are served under both /admin and /registrar from the same
// controller. Each route carries its own permission on top of the role group
// (deny-by-default): reading needs students.view, every change needs
// students.manage.
$studentRecordRoutes = static function ($routes): void {
    $view       = ['filter' => 'permission:students.view'];
    $manage     = ['filter' => 'permission:students.manage'];
    $feesManage = ['filter' => 'permission:fees.manage'];

    $routes->get('students'                     , 'Admin\StudentRecordController::index', $view);
    $routes->get('students/(:num)'              , 'Admin\StudentRecordController::show/$1', $view);
    $routes->post('students/(:num)/status'      , 'Admin\StudentRecordController::changeStatus/$1', $manage);
    $routes->post('students/(:num)/curriculum'  , 'Admin\StudentRecordController::assignCurriculum/$1', $manage);
    $routes->post('students/(:num)/enrollments/(:num)/drop', 'Admin\StudentRecordController::dropEnrollment/$1/$2', $manage);
    $routes->post('students/(:num)/enroll'      , 'Admin\StudentRecordController::enrollStudent/$1', $manage);
    $routes->post('students/(:num)/tuition/payment', 'Admin\StudentRecordController::recordPayment/$1', $feesManage);
};

$tuitionRateRoutes = static function ($routes): void {
    $view   = ['filter' => 'permission:fees.view'];
    $manage = ['filter' => 'permission:fees.manage'];

    $routes->get('tuition-rates'                       , 'Admin\TuitionController::index', $view);
    $routes->get('tuition-rates/edit/(:num)/(:any)'     , 'Admin\TuitionController::edit/$1/$2', $view);
    $routes->post('tuition-rates/store'                 , 'Admin\TuitionController::store', $manage);

    $routes->get('fee-types'          , 'Admin\FeeTypeController::index', $view);
    $routes->post('fee-types/store'   , 'Admin\FeeTypeController::store', $manage);
    $routes->post('fee-types/(:num)/update'        , 'Admin\FeeTypeController::update/$1', $manage);
    $routes->post('fee-types/(:num)/toggle-active' , 'Admin\FeeTypeController::toggleActive/$1', $manage);

    $routes->get('payment-proofs'                  , 'Admin\PaymentProofController::index', $view);
    $routes->post('payment-proofs/(:num)/approve'  , 'Admin\PaymentProofController::approve/$1', $manage);
    $routes->post('payment-proofs/(:num)/reject'   , 'Admin\PaymentProofController::reject/$1', $manage);
};

$routes->group('admin', ['filter' => 'group:admin'], function ($routes) use ($studentRecordRoutes, $tuitionRateRoutes) {
    $routes->get('dashboard', 'Admin\AdminController::index');

    // create programs
    $routes->get('programs'               , 'Admin\ProgramController::index');
    $routes->get('programs/create'        , 'Admin\ProgramController::create');
    $routes->post('programs/store'        , 'Admin\ProgramController::store');

    $routes->get('programs/edit/(:num)'   , 'Admin\ProgramController::edit/$1');
    $routes->post('programs/update/(:num)', 'Admin\ProgramController::update/$1');

    // $routes->post('programs/delete/(:num)', 'Admin\ProgramController::delete/$1');

    $routes->post('programs/toggle-status/(:num)' , 'Admin\ProgramController::toggleStatus/$1');

    // transfer a program to a different department
    $routes->post('programs/(:num)/transfer/preview', 'Admin\ProgramController::transferPreview/$1');
    $routes->post('programs/(:num)/transfer'        , 'Admin\ProgramController::transfer/$1');

    // create subjects
    $routes->get('subjects'               , 'Admin\SubjectController::index');
    $routes->get('subjects/create'        , 'Admin\SubjectController::create');
    $routes->post('subjects/store'        , 'Admin\SubjectController::store');

    $routes->get('subjects/edit/(:num)'   , 'Admin\SubjectController::edit/$1');
    $routes->post('subjects/update/(:num)', 'Admin\SubjectController::update/$1');
    $routes->post('subjects/update-owner/(:num)', 'Admin\SubjectController::updateOwner/$1');
    $routes->post('subjects/bulk-delete', 'Admin\SubjectController::bulkDestroy');

    $routes->post('subjects/toggle-status/(:num)', 'Admin\SubjectController::toggleStatus/$1');
    $routes->post('subjects/(:num)/prerequisites', 'Admin\SubjectController::addPrerequisite/$1');
    $routes->post('subjects/(:num)/prerequisites/(:num)/remove', 'Admin\SubjectController::removePrerequisite/$1/$2');

    // Step 9: subject component templates (Lecture/Laboratory/etc. split)
    $routes->post('subjects/(:num)/components', 'Admin\SubjectController::addComponentTemplate/$1');
    $routes->post('subjects/(:num)/components/(:num)/deactivate', 'Admin\SubjectController::deactivateComponentTemplate/$1/$2');

    $routes->get('subject-substitutions'            , 'Admin\SubjectSubstitutionController::index');
    $routes->post('subject-substitutions/store'     , 'Admin\SubjectSubstitutionController::store');
    $routes->post('subject-substitutions/deactivate/(:num)', 'Admin\SubjectSubstitutionController::deactivate/$1');

    $routes->get('audit-logs', 'Admin\AuditLogController::index');
    $routes->get('academic-health', 'Admin\AcademicHealthController::index');

    $routes->get('curriculum'               , 'Admin\CurriculumController::index');
    $routes->get('curriculum/create'        , 'Admin\CurriculumController::create');
    $routes->post('curriculum/store'        , 'Admin\CurriculumController::store');

    $routes->get('curriculum/edit/(:num)'   , 'Admin\CurriculumController::edit/$1');
    $routes->post('curriculum/update/(:num)', 'Admin\CurriculumController::update/$1');

    $routes->post('curriculum/toggle/(:num)', 'Admin\CurriculumController::toggleStatus/$1');
    $routes->post('curriculum/delete/(:num)', 'Admin\CurriculumController::delete/$1');

    // Step 1: list every curriculum. Step 2: click one into the
    // builder-style workspace (year level + semester tabs, add/remove
    // subjects inline) — mirrors the Chairperson Curriculum Builder's UX.
    $routes->get('curriculum-subjects'              , 'Admin\CurriculumSubjectController::index');
    $routes->get('curriculum-subjects/manage/(:num)', 'Admin\CurriculumSubjectController::manage/$1');
    $routes->get('curriculum-subjects/search'                        , 'Admin\CurriculumSubjectController::searchSubjects');
    $routes->post('curriculum-subjects/add'                          , 'Admin\CurriculumSubjectController::addSubject');
    $routes->post('curriculum-subjects/subject/(:num)/remove'        , 'Admin\CurriculumSubjectController::removeSubject/$1');
    $routes->post('curriculum-subjects/subject/(:num)/classification', 'Admin\CurriculumSubjectController::updateClassification/$1');

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
    $routes->post('sub-departments/(:num)/chair/(:num)/remove', 'Admin\SubDepartmentController::removeChair/$1/$2');
    $routes->post('sub-departments/(:num)/transfer/preview', 'Admin\SubDepartmentController::transferPreview/$1');
    $routes->post('sub-departments/(:num)/transfer'    , 'Admin\SubDepartmentController::transfer/$1');
    $routes->post('sub-departments/(:num)/programs'    , 'Admin\SubDepartmentController::addProgram/$1');
    $routes->post('sub-departments/(:num)/toggle-status', 'Admin\SubDepartmentController::toggleStatus/$1');
    $routes->post('sub-departments/(:num)/delete'      , 'Admin\SubDepartmentController::delete/$1');

    // faculty members (teaching staff) within a department
    $routes->post('sub-departments/(:num)/faculty'                       , 'Admin\SubDepartmentController::addFaculty/$1');
    $routes->post('sub-departments/(:num)/faculty/(:num)/update'         , 'Admin\SubDepartmentController::updateFaculty/$1/$2');
    $routes->post('sub-departments/(:num)/faculty/(:num)/toggle-status'  , 'Admin\SubDepartmentController::toggleFacultyStatus/$1/$2');
    $routes->post('sub-departments/(:num)/faculty/(:num)/delete'         , 'Admin\SubDepartmentController::deleteFaculty/$1/$2');

    // faculty <-> department affiliations (many-to-many; a faculty member may
    // be affiliated with departments beyond their primary/home one)
    $routes->post('sub-departments/(:num)/faculty/(:num)/affiliations/add'         , 'Admin\SubDepartmentController::addAffiliation/$1/$2');
    $routes->post('sub-departments/(:num)/faculty/(:num)/affiliations/remove'      , 'Admin\SubDepartmentController::removeAffiliation/$1/$2');
    $routes->post('sub-departments/(:num)/faculty/(:num)/affiliations/set-primary' , 'Admin\SubDepartmentController::setPrimaryAffiliation/$1/$2');

    // rooms
    $routes->get('rooms'                    , 'Admin\RoomController::index');
    $routes->post('rooms/store'             , 'Admin\RoomController::store');
    $routes->post('floors/store'            , 'Admin\RoomController::storeFloor');
    $routes->post('floors/delete'           , 'Admin\RoomController::deleteFloor');
    $routes->post('rooms/update/(:num)'     , 'Admin\RoomController::update/$1');
    $routes->post('rooms/delete/(:num)'     , 'Admin\RoomController::delete/$1');
    $routes->post('rooms/toggle-status/(:num)', 'Admin\RoomController::toggleStatus/$1');

    // academic years
    $routes->get('academic-years'                 , 'Admin\AcademicYearController::index');
    $routes->post('academic-years/store'          , 'Admin\AcademicYearController::store');
    $routes->post('academic-years/set-active/(:num)', 'Admin\AcademicYearController::setActive/$1');
    $routes->post('academic-years/sync-active'    , 'Admin\AcademicYearController::syncActive');

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

    // student records (shared with the registrar — see $studentRecordRoutes)
    $studentRecordRoutes($routes);

    // tuition rates (shared with the registrar — see $tuitionRateRoutes)
    $tuitionRateRoutes($routes);

    // grading scale (numeric GWA / percentage / letter — one active at a time)
    $routes->get('grading-scales'               , 'Admin\GradingScaleController::index');
    $routes->post('grading-scales/store'        , 'Admin\GradingScaleController::store');
    $routes->post('grading-scales/set-active/(:num)', 'Admin\GradingScaleController::setActive/$1');

    // notifications (shared controller, scoped by auth()->id())
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/mark-read', 'NotificationController::markRead');
    $routes->post('notifications/mark-all-read', 'NotificationController::markAllRead');
});

$routes->group('registrar', ['filter' => 'group:registrar'], function ($routes) use ($studentRecordRoutes, $tuitionRateRoutes) {
    $routes->get('dashboard', 'RegistrarController::index');

    // student records (same controller/permissions as the admin console)
    $studentRecordRoutes($routes);

    // tuition rates (same controller/permissions as the admin console)
    $tuitionRateRoutes($routes);

    // profile
    $routes->get('profile', 'Registrar\ProfileController::edit');
    $routes->post('profile/update', 'Registrar\ProfileController::updateProfile');
    $routes->post('profile/password', 'Registrar\ProfileController::updatePassword');

    // notifications (shared controller, scoped by auth()->id())
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/mark-read', 'NotificationController::markRead');
    $routes->post('notifications/mark-all-read', 'NotificationController::markAllRead');
});

$routes->group('faculty', ['filter' => 'group:faculty'], function ($routes) {
    $routes->get('dashboard', 'TeachingLoads::index');
    $routes->get('loads/state', 'TeachingLoads::state');
    $routes->post('loads/swap/request', 'TeachingLoads::requestSwap');
    $routes->post('loads/swap/respond', 'TeachingLoads::respondSwap');
    $routes->post('loads/swap/cancel', 'TeachingLoads::cancelSwap');

    // pre-semester availability & subject preferences
    $routes->get('availability', 'Faculty\AvailabilityController::edit');
    $routes->get('availability/for-term/(:num)', 'Faculty\AvailabilityController::forTermJson/$1');
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
    $routes->post('enrollments/drop', 'StudentEnrollment::drop');
    $routes->get('grades', 'StudentGrades::index');
    $routes->get('tuition', 'StudentController::tuition');
    $routes->post('tuition/proof/upload', 'StudentController::uploadProof');

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
    $routes->get('schedule/suggest', 'ChairpersonController::suggest');
    $routes->post('schedule/assistant-rules', 'ChairpersonController::saveAssistantRules');
    // Curriculum Builder (formal curriculum/curriculum_subjects, draft-scoped —
    // the sole supported curriculum source of truth)
    $routes->get('curriculum', 'Chairperson\CurriculumBuilderController::index');
    $routes->post('curriculum-builder/add-subject', 'Chairperson\CurriculumBuilderController::addSubject');
    $routes->post('curriculum-builder/subject/(:num)/remove', 'Chairperson\CurriculumBuilderController::removeSubject/$1');
    $routes->post('curriculum-builder/subject/(:num)/classification', 'Chairperson\CurriculumBuilderController::updateClassification/$1');

    // Subject Ownership
    $routes->get('subject-ownership', 'Chairperson\SubjectOwnershipController::index');
    $routes->post('subject-ownership/store', 'Chairperson\SubjectOwnershipController::store');
    $routes->post('subject-ownership/(:num)/update', 'Chairperson\SubjectOwnershipController::update/$1');
    $routes->post('subject-ownership/(:num)/toggle-status', 'Chairperson\SubjectOwnershipController::toggleStatus/$1');
    $routes->post('swaps/approve', 'ChairpersonController::approveSwap');
    $routes->post('swaps/reject', 'ChairpersonController::rejectSwap');
    $routes->post('change-requests/approve', 'ChairpersonController::approveChangeRequest');
    $routes->post('change-requests/reject', 'ChairpersonController::rejectChangeRequest');
    $routes->post('change-requests/review', 'ChairpersonController::reviewChangeRequest');

    // notifications
    $routes->get('notifications', 'NotificationController::index');
    $routes->post('notifications/mark-read', 'NotificationController::markRead');
    $routes->post('notifications/mark-all-read', 'NotificationController::markAllRead');
    $routes->get('subjects/search', 'ChairpersonController::searchSubjects');
    $routes->post('sections/capacity', 'ChairpersonController::setSectionCapacity');
    $routes->get('sections', 'ChairpersonController::sections');
    $routes->post('sections/create', 'ChairpersonController::createSection');
    $routes->post('sections/ensure', 'ChairpersonController::ensureSection');
    $routes->post('sections/delete', 'ChairpersonController::deleteSection');
    $routes->post('sections/assign-student', 'ChairpersonController::assignStudent');
    $routes->post('sections/unassign-student', 'ChairpersonController::unassignStudent');
    $routes->post('sections/rebalance/preview', 'ChairpersonController::rebalancePreview');
    $routes->post('sections/rebalance/apply', 'ChairpersonController::rebalanceApply');
    $routes->post('sections/state', 'ChairpersonController::setSectionState');
    $routes->get('faculty-panel', 'ChairpersonController::facultyPanel');

    // section merging (course offerings)
    $routes->get('offerings'                , 'Chairperson\OfferingController::index');
    $routes->post('offerings/create'        , 'Chairperson\OfferingController::create');
    $routes->post('offerings/plot-demand'   , 'Chairperson\OfferingController::plotDemand');
    $routes->post('offerings/unmerge'       , 'Chairperson\OfferingController::unmergeSection');
    $routes->get('offerings/schedule'       , 'Chairperson\OfferingController::scheduleState');
    $routes->post('offerings/schedule/sync' , 'Chairperson\OfferingController::syncSchedule');

    // Step 9: offering components (Lecture/Laboratory split scheduling + roster)
    $routes->post('offerings/components/schedule/sync', 'Chairperson\OfferingController::syncComponentSchedule');
    $routes->post('offerings/components/roster/add',    'Chairperson\OfferingController::assignComponentStudent');
    $routes->post('offerings/components/roster/remove', 'Chairperson\OfferingController::removeComponentStudent');
    $routes->post('offerings/components/deactivate',    'Chairperson\OfferingController::deactivateComponent');
    $routes->post('offerings/components/reactivate',    'Chairperson\OfferingController::reactivateComponent');

    // shared-subject dashboard (owning scheduler's operational overview)
    $routes->get('shared-subjects', 'Chairperson\SharedSubjectDashboardController::index');

    // shared-subject coverage (non-owning scheduler's "are my students covered?" view)
    $routes->get('shared-subject-coverage', 'Chairperson\SharedSubjectCoverageController::index');

    // shared-subject placement requests
    $routes->get('placement-requests'          , 'Chairperson\PlacementRequestController::index');
    $routes->post('placement-requests/create'  , 'Chairperson\PlacementRequestController::create');
    $routes->post('placement-requests/cancel'  , 'Chairperson\PlacementRequestController::cancel');
    $routes->post('placement-requests/approve' , 'Chairperson\PlacementRequestController::approve');
    $routes->post('placement-requests/deny'    , 'Chairperson\PlacementRequestController::deny');
    $routes->post('placement-requests/fulfill' , 'Chairperson\PlacementRequestController::fulfill');

    // grade approvals: lock a faculty-submitted class, decide correction requests
    $routes->get('grade-approvals'                     , 'Chairperson\GradeApprovalController::index');
    $routes->get('grade-approvals/class'                , 'Chairperson\GradeApprovalController::show');
    $routes->post('grade-approvals/lock'                , 'Chairperson\GradeApprovalController::lock');
    $routes->post('grade-approvals/change-requests/approve', 'Chairperson\GradeApprovalController::approveChange');
    $routes->post('grade-approvals/change-requests/reject'  , 'Chairperson\GradeApprovalController::rejectChange');

    // student academic progress monitoring + graduation candidate review (one page, filtered by "view")
    $routes->get('student-progress'        , 'Chairperson\StudentProgressController::index');
    $routes->get('student-progress/detail' , 'Chairperson\StudentProgressController::detail');
});

$routes->get('/dashboard', 'DashboardController::index', ['filter' => 'session']);

// Proof-of-payment file serving — any signed-in role; StudentController::
// viewProof() itself enforces ownership (the student) or fees.view/manage
// (staff reviewing via the Payment Proofs queue), since the requester could
// be either.
$routes->get('/student/tuition/proof/(:num)', 'StudentController::viewProof/$1', ['filter' => 'session']);

// Forced password change (temp-password first login) — any signed-in role.
$routes->post('/account/set-temp-password', 'AccountController::setTempPassword', ['filter' => 'session']);

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

$routes->group('api', ['filter' => 'apikey'], function($routes) {
    $routes->post('students/promote'   , 'API\StudentAPIController::promote');
});

