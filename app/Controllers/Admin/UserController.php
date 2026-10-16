<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcademicYearModel;
use App\Models\CurriculumModel;
use App\Models\DepartmentModel;
use App\Models\EnrollmentModel;
use App\Models\NotificationModel;
use App\Models\SectionModel;
use App\Models\StudentModel;
use App\Models\SubDepartmentModel;
use CodeIgniter\Shield\Entities\User;

class UserController extends BaseController
{
    protected DepartmentModel $departmentModel;

    private const ROLES = ['admin', 'registrar', 'dean', 'chairperson', 'faculty', 'student'];

    /** Chairpersons are assigned via a specific Department (see Admin\SubDepartmentController::storeChair). */
    private const CREATABLE_ROLES = ['admin', 'registrar', 'dean', 'faculty', 'student'];

    public function __construct()
    {
        $this->departmentModel = new DepartmentModel();
    }

    public function index()
    {
        $roleFilter = $this->request->getGet('role') ?: 'all';

        $builder = db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email, u.active, u.department_id, u.section_id, u.employment_status, g.group, d.code as dept_code, d.name as dept_name,
                      s.date_of_birth, s.gender, s.home_address, s.city, s.emergency_contact_name, s.emergency_contact_number')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('departments d', 'd.dept_id = u.department_id', 'left')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->join('students s', 's.user_id = u.id', 'left')
            ->where('u.deleted_at', null)
            ->orderBy('u.username', 'asc');

        if ($roleFilter !== 'all') {
            $builder->where('g.group', $roleFilter);
        } else {
            $builder->whereIn('g.group', self::ROLES);
        }

        $allUsers = $builder->get()->getResultArray();

        $perPage    = 20;
        $totalUsers = count($allUsers);
        $totalPages = max(1, (int) ceil($totalUsers / $perPage));
        $page       = (int) ($this->request->getGet('page') ?: 1);
        $page       = max(1, min($page, $totalPages));
        $users      = array_slice($allUsers, ($page - 1) * $perPage, $perPage);

        $subDepartments = (new SubDepartmentModel())->where('is_active', 1)->orderBy('name')->findAll();
        $codeToSubDeptId = array_column($subDepartments, 'sub_dept_id', 'code');

        // Sections carry a program DISPLAY NAME (string), not a sub-department
        // FK, and some programs are stored under a legacy display alias that
        // doesn't equal programs.program_name (see
        // DepartmentModel::LEGACY_PROGRAM_NAMES) — resolve via
        // DepartmentModel::programDept() rather than a raw SQL join, which
        // would silently drop those sections.
        //
        // Scoped to the currently active academic year/semester only — this
        // dropdown previously listed every section from every past and
        // future term (every prior Summer/2nd Semester cohort piling up
        // alongside this term's), since nothing filtered by academic_year.
        // A student should only ever be block-assigned into a section that
        // actually exists for the term that's live right now.
        //
        // Still includes whatever section_id any existing user (not just
        // this page's slice) is currently sitting in, even from an older
        // term — otherwise editing a student already assigned to a
        // since-closed term's section would silently blank out their real
        // department/year/section in the Edit modal (its id simply
        // wouldn't exist in the filtered list for the preselect lookup to find).
        $assignedSectionIds = array_values(array_unique(array_filter(array_map(
            static fn (array $u): ?int => $u['section_id'] !== null ? (int) $u['section_id'] : null,
            $allUsers
        ))));

        $activeTerm   = (new AcademicYearModel())->activeFormatted();
        $sectionModel = (new SectionModel())->orderBy('program')->orderBy('year_level')->orderBy('section');

        if ($activeTerm !== null) {
            $sectionModel->groupStart()
                ->where('academic_year', $activeTerm)
                ->orWhereIn('section_id', $assignedSectionIds === [] ? [0] : $assignedSectionIds)
            ->groupEnd();
        } elseif ($assignedSectionIds !== []) {
            $sectionModel->whereIn('section_id', $assignedSectionIds);
        }

        $sections = array_map(
            static function (array $s) use ($codeToSubDeptId) {
                $code            = DepartmentModel::programDept($s['program']);
                $s['sub_dept_id'] = $codeToSubDeptId[$code] ?? null;

                return $s;
            },
            ($activeTerm !== null || $assignedSectionIds !== []) ? $sectionModel->findAll() : []
        );

        return view('admin/users/index', [
            'pageTitle'      => 'User Accounts',
            'activeAdmin'    => 'users',
            'users'          => $users,
            'roleFilter'     => $roleFilter,
            'roles'          => self::ROLES,
            'departments'    => $this->departmentModel->findAll(),
            'subDepartments' => $subDepartments,
            'sections'       => $sections,
            'page'           => $page,
            'totalPages'     => $totalPages,
            'totalUsers'     => $totalUsers,
            'perPage'        => $perPage,
        ]);
    }

    public function store()
    {
        $rules = [
            'username'          => 'required|max_length[100]',
            'email'             => 'required|valid_email',
            'role'              => 'required|in_list[' . implode(',', self::CREATABLE_ROLES) . ']',
            'department_id'     => 'permit_empty|integer',
            'section_id'        => 'permit_empty|integer',
            'password'          => 'permit_empty|min_length[8]',
            'employment_status' => 'permit_empty|in_list[full_time,part_time]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $username     = $this->request->getPost('username');
        $email        = $this->request->getPost('email');
        $role         = $this->request->getPost('role');
        $departmentId = in_array($role, ['admin', 'registrar'], true) ? null : $this->request->getPost('department_id');

        // The minimum student->cohort link (block enrollment reads this
        // server-side — see StudentEnrollment::enrollBlock).
        $sectionId = null;

        if ($role === 'student' && $this->request->getPost('section_id')) {
            $sectionId = (int) $this->request->getPost('section_id');

            if ((new SectionModel())->find($sectionId) === null) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('errors', ['section_id' => 'That section does not exist.']);
            }
        }

        $users = auth()->getProvider();

        if ($users->findByCredentials(['email' => $email])) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', ['email' => 'An account with that email already exists.']);
        }

        if (db_connect()->table('users')->where('username', $username)->countAllResults() > 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', ['username' => 'An account with that name already exists.']);
        }

        $tempPassword = (string) $this->request->getPost('password') ?: bin2hex(random_bytes(6));

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => $tempPassword,
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($role);

        $employmentStatus = $role === 'faculty' ? ($this->request->getPost('employment_status') ?: null) : null;

        if ($departmentId || $sectionId || $employmentStatus) {
            db_connect()->table('users')
                ->where('id', $user->id)
                ->update(array_filter([
                    'department_id'     => $departmentId,
                    'section_id'        => $sectionId,
                    'employment_status' => $employmentStatus,
                ]));
        }

        // A student account is useless without its `students` row — every
        // other piece of the system (tuition assessment, the curriculum-
        // driven enlistment list, grades, dashboard counts) is keyed off
        // students.student_id/curriculum_id, none of which existed before
        // this: creating a student here only ever touched `users`. Mirrors
        // StudentModel::promoteFromApplicant()'s own program -> curriculum
        // resolution so both creation paths end up in the same shape.
        if ($role === 'student') {
            $section = $sectionId !== null ? (new SectionModel())->find($sectionId) : null;

            $programId = $section !== null
                ? ($section['program_id'] !== null ? (int) $section['program_id'] : DepartmentModel::programIdOfName($section['program']))
                : null;

            $curriculumId = $programId !== null
                ? ((new CurriculumModel())->liveFor($programId)['curriculum_id'] ?? null)
                : null;

            $dateOfBirth = trim((string) $this->request->getPost('date_of_birth'));

            (new StudentModel())->insert([
                'user_id'                  => $user->id,
                'student_number'           => (new StudentModel())->nextStudentNumber(),
                'program_id'               => $programId,
                'curriculum_id'            => $curriculumId,
                'admission_date'           => date('Y-m-d H:i:s'),
                'student_status'           => 'active',
                'date_of_birth'            => $dateOfBirth !== '' ? $dateOfBirth : null,
                'gender'                   => trim((string) $this->request->getPost('gender')) ?: null,
                'home_address'             => trim((string) $this->request->getPost('home_address')) ?: null,
                'city'                     => trim((string) $this->request->getPost('city')) ?: null,
                'emergency_contact_name'   => trim((string) $this->request->getPost('emergency_contact_name')) ?: null,
                'emergency_contact_number' => trim((string) $this->request->getPost('emergency_contact_number')) ?: null,
            ]);

            // Assigning a student straight to a section here (rather than
            // through the self-service Enlistment cart) is meant to drop
            // them directly into that cohort's classes — block-enroll them
            // into everything plotted for it now, same core logic the
            // student's own "Enroll Now" action uses. Best-effort: a
            // section with nothing plotted for it yet is a normal,
            // expected state (the chairperson hasn't scheduled it), not a
            // reason to fail account creation.
            if ($section !== null) {
                (new EnrollmentModel())->enrollBlock($user->id, $sectionId);
            }
        }

        return redirect()
            ->to('/admin/users')
            ->with('credential', [
                'heading'  => 'Account created',
                'username' => $username,
                'email'    => $email,
                'password' => $tempPassword,
            ]);
    }

    /**
     * Edit an existing account's profile fields. Role can only move between
     * CREATABLE_ROLES — chairpersons stay locked to their department's own
     * assignment flow (Admin\SubDepartmentController::storeChair).
     */
    public function update($id)
    {
        $users = auth()->getProvider();
        $user  = $users->findById($id);

        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'username'          => 'required|max_length[100]',
            'email'             => 'required|valid_email',
            'department_id'     => 'permit_empty|integer',
            'section_id'        => 'permit_empty|integer',
            'employment_status' => 'permit_empty|in_list[full_time,part_time]',
        ];

        $isChair = $user->inGroup('chairperson');

        if (! $isChair) {
            $rules['role'] = 'required|in_list[' . implode(',', self::CREATABLE_ROLES) . ']';
        }

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $username = $this->request->getPost('username');
        $email    = $this->request->getPost('email');

        $emailTaken = db_connect()->table('auth_identities')
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->where('user_id !=', $id)
            ->countAllResults() > 0;

        if ($emailTaken) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', ['email' => 'An account with that email already exists.']);
        }

        $usernameTaken = db_connect()->table('users')
            ->where('username', $username)
            ->where('id !=', $id)
            ->countAllResults() > 0;

        if ($usernameTaken) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', ['username' => 'An account with that name already exists.']);
        }

        $user->username = $username;
        $user->email    = $email;
        $users->save($user);

        if (! $isChair) {
            $role         = $this->request->getPost('role');
            $departmentId = in_array($role, ['admin', 'registrar'], true) ? null : $this->request->getPost('department_id');
            $sectionId    = $role === 'student' ? $this->request->getPost('section_id') ?: null : null;

            if ($sectionId !== null && (new SectionModel())->find((int) $sectionId) === null) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('errors', ['section_id' => 'That section does not exist.']);
            }

            $user->syncGroups($role);

            db_connect()->table('users')
                ->where('id', $id)
                ->update([
                    'department_id'     => $departmentId,
                    'section_id'        => $sectionId,
                    'employment_status' => $role === 'faculty' ? ($this->request->getPost('employment_status') ?: null) : null,
                ]);

            if ($role === 'student') {
                $student = (new StudentModel())->where('user_id', $id)->first();

                if ($student !== null) {
                    $dateOfBirth = trim((string) $this->request->getPost('date_of_birth'));

                    (new StudentModel())->update((int) $student['student_id'], [
                        'date_of_birth'            => $dateOfBirth !== '' ? $dateOfBirth : null,
                        'gender'                   => trim((string) $this->request->getPost('gender')) ?: null,
                        'home_address'             => trim((string) $this->request->getPost('home_address')) ?: null,
                        'city'                     => trim((string) $this->request->getPost('city')) ?: null,
                        'emergency_contact_name'   => trim((string) $this->request->getPost('emergency_contact_name')) ?: null,
                        'emergency_contact_number' => trim((string) $this->request->getPost('emergency_contact_number')) ?: null,
                    ]);
                }
            }
        }

        return redirect()
            ->to('/admin/users')
            ->with('message', "{$username}'s account updated.");
    }

    /**
     * Sets a fresh password for an account that forgot theirs — either a
     * specific one the admin types in, or an auto-generated one when the
     * field is left blank (same optional-password pattern as store()).
     * Shown once in the same reveal modal used at creation time.
     */
    public function resetPassword($id)
    {
        $users = auth()->getProvider();
        $user  = $users->findById($id);

        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $manualPassword = trim((string) $this->request->getPost('password'));

        $reopenModal = ['type' => 'reset-password', 'id' => (int) $id, 'username' => $user->username];

        if ($manualPassword !== '' && mb_strlen($manualPassword) < 8) {
            return redirect()->back()->with('errors', ['password' => 'Password must be at least 8 characters.'])->with('modal', $reopenModal);
        }

        $tempPassword    = $manualPassword !== '' ? $manualPassword : bin2hex(random_bytes(6));
        $user->password  = $tempPassword;

        try {
            $users->save($user);
        } catch (\Throwable $e) {
            return redirect()->back()->with('errors', ['password' => 'That password was rejected. Use at least 8 characters that are not easy to guess.'])->with('modal', $reopenModal);
        }

        $email = db_connect()->table('auth_identities')
            ->where('user_id', $id)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray()['secret'] ?? '';

        (new NotificationModel())->notify(
            (int) $id,
            'password_reset',
            'Your password was reset by an administrator. If you did not request this, contact the registrar immediately.',
            null
        );

        return redirect()
            ->to('/admin/users')
            ->with('credential', [
                'heading'  => 'Password reset',
                'username' => $user->username,
                'email'    => $email,
                'password' => $tempPassword,
            ]);
    }

    public function activate($id)
    {
        $users = auth()->getProvider();
        $user  = $users->findById($id);

        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $user->active = 1;
        $users->save($user);

        return redirect()
            ->to('/admin/users')
            ->with('message', 'Account activated.');
    }

    public function deactivate($id)
    {
        $users = auth()->getProvider();
        $user  = $users->findById($id);

        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $user->active = 0;
        $users->save($user);

        return redirect()
            ->to('/admin/users')
            ->with('message', 'Account deactivated.');
    }
}
