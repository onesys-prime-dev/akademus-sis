<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use App\Models\SubDepartmentModel;
use CodeIgniter\Shield\Entities\User;

/**
 * Colleges (dean-headed). Departments (chairperson-headed) nest under a
 * college — see Admin\SubDepartmentController.
 */
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
            $dept['dean']         = $this->deanOf($dept['dept_id']);
            $dept['dept_count']   = $db->table('sub_departments')->where('college_id', $dept['dept_id'])->countAllResults();
            $dept['faculty_count'] = $this->countInGroup($dept['dept_id'], 'faculty');
        }
        unset($dept);

        return view('admin/departments/index', [
            'pageTitle'   => 'Colleges',
            'activeAdmin' => 'departments',
            'departments' => $departments,
        ]);
    }

    public function show($id)
    {
        $college = $this->departmentModel->find($id);

        if (! $college) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $college['dean'] = $this->deanOf($id);

        $subDepartments = (new SubDepartmentModel())
            ->where('college_id', $id)
            ->orderBy('name')
            ->findAll();

        $db = db_connect();

        foreach ($subDepartments as &$dept) {
            $dept['chair']         = $this->chairOf($dept['sub_dept_id']);
            $dept['faculty_count'] = $this->countInGroupBySubDept($dept['sub_dept_id'], 'faculty');
            $dept['program_count'] = $db->table('programs')->where('sub_department_id', $dept['sub_dept_id'])->countAllResults();
        }
        unset($dept);

        return view('admin/departments/show', [
            'pageTitle'      => $college['name'],
            'activeAdmin'    => 'departments',
            'college'        => $college,
            'subDepartments' => $subDepartments,
            'facultyCount'   => $this->countInGroup($id, 'faculty'),
            'studentCount'   => $this->countInGroup($id, 'student'),
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
            ->with('message', 'College created successfully.');
    }

    public function update($id)
    {
        $college = $this->departmentModel->find($id);

        if (! $college) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'code'             => "required|max_length[10]|is_unique[departments.code,dept_id,{$id}]",
            'name'             => 'required|max_length[100]',
            'location'         => 'permit_empty|max_length[150]',
            'established_year' => 'permit_empty|integer',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->departmentModel->update($id, [
            'code'             => strtoupper($this->request->getPost('code')),
            'name'             => $this->request->getPost('name'),
            'location'         => $this->request->getPost('location') ?: null,
            'established_year' => $this->request->getPost('established_year') ?: null,
        ]);

        return redirect()
            ->to("/admin/departments/{$id}")
            ->with('message', 'College details updated.');
    }

    public function storeDean($id)
    {
        $college = $this->departmentModel->find($id);

        if (! $college) {
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

        if (! $user->inGroup('dean')) {
            $user->addGroup('dean');
        }

        db_connect()->table('users')
            ->where('id', $user->id)
            ->update(['department_id' => $id]);

        return redirect()
            ->to("/admin/departments/{$id}")
            ->with('message', "{$username} assigned as dean of {$college['name']}.");
    }

    public function storeSubDepartment($id)
    {
        $college = $this->departmentModel->find($id);

        if (! $college) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'dept_code' => 'required|max_length[20]',
            'dept_name' => 'required|max_length[150]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        (new SubDepartmentModel())->insert([
            'college_id' => $id,
            'code'       => strtoupper($this->request->getPost('dept_code')),
            'name'       => $this->request->getPost('dept_name'),
        ]);

        return redirect()
            ->to("/admin/departments/{$id}")
            ->with('message', 'Department added.');
    }

    private function deanOf(int $collegeId): ?array
    {
        return db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'")
            ->where('g.group', 'dean')
            ->where('u.department_id', $collegeId)
            ->where('u.deleted_at', null)
            ->get()
            ->getRowArray();
    }

    private function chairOf(int $subDeptId): ?array
    {
        return db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'")
            ->where('g.group', 'chairperson')
            ->where('u.sub_department_id', $subDeptId)
            ->where('u.deleted_at', null)
            ->get()
            ->getRowArray();
    }

    private function countInGroup(int $collegeId, string $group): int
    {
        return db_connect()->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', $group)
            ->where('u.department_id', $collegeId)
            ->where('u.deleted_at', null)
            ->countAllResults();
    }

    private function countInGroupBySubDept(int $subDeptId, string $group): int
    {
        return db_connect()->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', $group)
            ->where('u.sub_department_id', $subDeptId)
            ->where('u.deleted_at', null)
            ->countAllResults();
    }
}
