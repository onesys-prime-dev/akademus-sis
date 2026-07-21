<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use CodeIgniter\Shield\Entities\User;

class DepartmentController extends BaseController
{
    protected DepartmentModel $departmentModel;

    public function __construct()
    {
        $this->departmentModel = new DepartmentModel();
    }

    public function index()
    {
        $departments = $this->departmentModel->findAll();
        $db          = db_connect();

        foreach ($departments as &$dept) {
            $chair = $db->table('users u')
                ->select('u.id, u.username, i.secret as email')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'")
                ->where('g.group', 'chairperson')
                ->where('u.department_id', $dept['dept_id'])
                ->get()
                ->getRowArray();

            $dept['chair'] = $chair;

            $dept['faculty_count'] = $db->table('users u')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->where('g.group', 'faculty')
                ->where('u.department_id', $dept['dept_id'])
                ->countAllResults();
        }
        unset($dept);

        return view('admin/departments/index', [
            'pageTitle'   => 'Departments',
            'activeAdmin' => 'departments',
            'departments' => $departments,
        ]);
    }

    public function store()
    {
        $rules = [
            'code' => 'required|max_length[10]|is_unique[departments.code]',
            'name' => 'required|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->departmentModel->insert([
            'code' => strtoupper($this->request->getPost('code')),
            'name' => $this->request->getPost('name'),
        ]);

        return redirect()
            ->to('/admin/departments')
            ->with('message', 'Department created successfully.');
    }

    public function storeChair($id)
    {
        $department = $this->departmentModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'username' => 'required|max_length[100]',
            'email'    => 'required|valid_email',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $username = $this->request->getPost('username');
        $email    = $this->request->getPost('email');

        $users    = auth()->getProvider();
        $existing = $users->findByCredentials(['email' => $email]);

        if ($existing) {
            $user = $existing;
            $user->username = $username;
        } else {
            $user = new User([
                'username' => $username,
                'email'    => $email,
                'password' => bin2hex(random_bytes(16)),
            ]);
        }

        $users->save($user);
        $user = $users->findById($existing ? $existing->id : $users->getInsertID());

        if (! $user->inGroup('chairperson')) {
            $user->addGroup('chairperson');
        }

        db_connect()->table('users')
            ->where('id', $user->id)
            ->update(['department_id' => $id]);

        return redirect()
            ->to('/admin/departments')
            ->with('message', "{$username} assigned as chairperson of {$department['name']}.");
    }
}
