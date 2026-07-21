<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use CodeIgniter\Shield\Entities\User;

class UserController extends BaseController
{
    protected DepartmentModel $departmentModel;

    private const ROLES = ['admin', 'chairperson', 'faculty', 'student'];

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
            'role'          => 'required|in_list[' . implode(',', self::ROLES) . ']',
            'department_id' => 'permit_empty|integer',
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

        $users = auth()->getProvider();

        if ($users->findByCredentials(['email' => $email])) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', ['email' => 'An account with that email already exists.']);
        }

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => bin2hex(random_bytes(16)),
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($role);

        if ($departmentId) {
            db_connect()->table('users')
                ->where('id', $user->id)
                ->update(['department_id' => $departmentId]);
        }

        return redirect()
            ->to('/admin/users')
            ->with('message', "Account created for {$username}. Invitation pending activation.");
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
}
