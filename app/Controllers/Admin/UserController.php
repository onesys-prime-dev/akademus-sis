<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Entities\User;

class UserController extends BaseController
{
    protected DepartmentModel $departmentModel;

    private const ROLES = ['admin', 'dean', 'chairperson', 'faculty', 'student'];

    /** Chairpersons are assigned via a specific Department (see Admin\SubDepartmentController::storeChair). */
    private const CREATABLE_ROLES = ['admin', 'dean', 'faculty', 'student'];

    public function __construct()
    {
        $this->departmentModel = new DepartmentModel();
    }

    public function index()
    {
        $roleFilter = $this->request->getGet('role') ?: 'all';

        $builder = db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email, u.active, u.department_id, g.group, d.code as dept_code, d.name as dept_name')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('departments d', 'd.dept_id = u.department_id', 'left')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('u.deleted_at', null)
            ->orderBy('u.username', 'asc');

        if ($roleFilter !== 'all') {
            $builder->where('g.group', $roleFilter);
        } else {
            $builder->whereIn('g.group', self::ROLES);
        }

        $users = $builder->get()->getResultArray();

        return view('admin/users/index', [
            'pageTitle'   => 'User Accounts',
            'activeAdmin' => 'users',
            'users'       => $users,
            'roleFilter'  => $roleFilter,
            'roles'       => self::ROLES,
            'departments' => $this->departmentModel->findAll(),
        ]);
    }

    public function store()
    {
        $rules = [
            'username'      => 'required|max_length[100]',
            'email'         => 'required|valid_email',
            'role'          => 'required|in_list[' . implode(',', self::CREATABLE_ROLES) . ']',
            'department_id' => 'permit_empty|integer',
            'section_id'    => 'permit_empty|integer',
            'password'      => 'permit_empty|min_length[8]',
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
        $departmentId = $role === 'admin' ? null : $this->request->getPost('department_id');

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

        if ($departmentId || $sectionId) {
            db_connect()->table('users')
                ->where('id', $user->id)
                ->update(array_filter([
                    'department_id' => $departmentId,
                    'section_id'    => $sectionId,
                ]));
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
            'username'      => 'required|max_length[100]',
            'email'         => 'required|valid_email',
            'department_id' => 'permit_empty|integer',
            'section_id'    => 'permit_empty|integer',
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
            $departmentId = $role === 'admin' ? null : $this->request->getPost('department_id');
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
                    'department_id' => $departmentId,
                    'section_id'    => $sectionId,
                ]);
        }

        return redirect()
            ->to('/admin/users')
            ->with('message', "{$username}'s account updated.");
    }

    /**
     * Generate a fresh temporary password for an account that forgot theirs.
     * Shown once in the same reveal modal used at creation time.
     */
    public function resetPassword($id)
    {
        $users = auth()->getProvider();
        $user  = $users->findById($id);

        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $tempPassword = bin2hex(random_bytes(6));
        $user->password = $tempPassword;
        $users->save($user);

        $email = db_connect()->table('auth_identities')
            ->where('user_id', $id)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray()['secret'] ?? '';

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
