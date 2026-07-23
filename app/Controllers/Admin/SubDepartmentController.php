<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;
use App\Models\ProgramModel;
use App\Models\SubDepartmentModel;
use CodeIgniter\Shield\Entities\User;

/**
 * Departments (chairperson-headed), nested under a College (dean-headed).
 */
class SubDepartmentController extends BaseController
{
    protected SubDepartmentModel $subDeptModel;

    public function __construct()
    {
        $this->subDeptModel = new SubDepartmentModel();
    }

    public function show($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $college = (new DepartmentModel())->find($department['college_id']);
        $department['chair'] = $this->chairOf($id);

        $programs = (new ProgramModel())
            ->where('sub_department_id', $id)
            ->orderBy('program_name')
            ->findAll();

        $colleges = (new DepartmentModel())->orderBy('name')->findAll();

        return view('admin/sub_departments/show', [
            'pageTitle'      => $department['name'],
            'activeAdmin'    => 'departments',
            'college'        => $college,
            'department'     => $department,
            'programs'       => $programs,
            'colleges'       => $colleges,
            'facultyMembers' => $this->facultyMembersOf($id),
            'facultyCount'   => $this->countInGroup($id, 'faculty'),
            'studentCount'   => $this->countInGroup($id, 'student'),
        ]);
    }

    public function update($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'code' => 'required|max_length[20]',
            'name' => 'required|max_length[150]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->subDeptModel->update($id, [
            'code' => strtoupper($this->request->getPost('code')),
            'name' => $this->request->getPost('name'),
        ]);

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', 'Department details updated.');
    }

    public function storeChair($id)
    {
        $department = $this->subDeptModel->find($id);

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
        $tempPassword = null;

        $usernameTaken = db_connect()->table('users')
            ->where('username', $username)
            ->where('id !=', $existing->id ?? 0)
            ->countAllResults() > 0;

        if ($usernameTaken) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', ['username' => 'An account with that name already exists.']);
        }

        if ($existing) {
            $user = $existing;
            $user->username = $username;
        } else {
            $tempPassword = bin2hex(random_bytes(6));
            $user = new User([
                'username' => $username,
                'email'    => $email,
                'password' => $tempPassword,
            ]);
        }

        $users->save($user);
        $user = $users->findById($existing ? $existing->id : $users->getInsertID());

        if (! $user->inGroup('chairperson')) {
            $user->addGroup('chairperson');
        }

        // sub_department_id scopes them to this department; department_id keeps the
        // parent college in sync, since scheduling (ChairpersonController/TeachingLoads)
        // still scopes chairpersons/faculty by college via users.department_id.
        db_connect()->table('users')
            ->where('id', $user->id)
            ->update([
                'sub_department_id' => $id,
                'department_id'     => $department['college_id'],
            ]);

        $message = "{$username} assigned as chairperson of {$department['name']}.";

        if ($tempPassword) {
            $message .= " Temporary password: {$tempPassword} (share this with them — it won't be shown again).";
        }

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', $message);
    }

    public function transfer($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $targetCollegeId = (int) $this->request->getPost('college_id');
        $targetCollege    = (new DepartmentModel())->find($targetCollegeId);

        if (! $targetCollege) {
            return redirect()->back()->with('errors', ['College not found.']);
        }

        if ($targetCollegeId === (int) $department['college_id']) {
            return redirect()
                ->to("/admin/sub-departments/{$id}")
                ->with('message', "{$department['name']} is already under {$targetCollege['name']}.");
        }

        $this->subDeptModel->update($id, ['college_id' => $targetCollegeId]);

        // Keep every chairperson/faculty already scoped to this department in sync
        // with their new parent college for scheduling purposes.
        db_connect()->table('users')
            ->where('sub_department_id', $id)
            ->update(['department_id' => $targetCollegeId]);

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', "{$department['name']} transferred to {$targetCollege['name']}.");
    }

    public function addProgram($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'program_code' => 'required|max_length[20]|is_unique[programs.program_code]',
            'program_name' => 'required|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        (new ProgramModel())->insert([
            'sub_department_id' => $id,
            'program_code'      => strtoupper($this->request->getPost('program_code')),
            'program_name'      => $this->request->getPost('program_name'),
        ]);

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', 'Program added.');
    }

    public function toggleStatus($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->subDeptModel->update($id, ['is_active' => $department['is_active'] ? 0 : 1]);

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', $department['is_active'] ? 'Department deactivated.' : 'Department activated.');
    }

    public function delete($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $collegeId = $department['college_id'];
        $db        = db_connect();

        // Detach rather than cascade-delete: chairpersons/faculty keep their
        // college link, programs are left unlinked for reassignment later.
        $db->table('users')->where('sub_department_id', $id)->update(['sub_department_id' => null]);
        $db->table('programs')->where('sub_department_id', $id)->update(['sub_department_id' => null]);

        $this->subDeptModel->delete($id);

        return redirect()
            ->to("/admin/departments/{$collegeId}")
            ->with('message', "{$department['name']} deleted.");
    }

    /**
     * Add a faculty member (teaching staff) to this department.
     */
    public function addFaculty($id)
    {
        $department = $this->subDeptModel->find($id);

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

        $tempPassword = bin2hex(random_bytes(6));

        $user = new User([
            'username' => $username,
            'email'    => $email,
            'password' => $tempPassword,
        ]);

        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup('faculty');

        db_connect()->table('users')
            ->where('id', $user->id)
            ->update([
                'sub_department_id' => $id,
                'department_id'     => $department['college_id'],
            ]);

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', "{$username} added to {$department['name']}. Temporary password: {$tempPassword} (share this with them — it won't be shown again).");
    }

    public function updateFaculty($id, $userId)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $users = auth()->getProvider();
        $user  = $users->find($userId);

        if (! $user || (int) $user->sub_department_id !== (int) $id) {
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

        $user->username = $this->request->getPost('username');
        $user->email    = $this->request->getPost('email');
        $users->save($user);

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', 'Faculty member updated.');
    }

    public function toggleFacultyStatus($id, $userId)
    {
        $users = auth()->getProvider();
        $user  = $users->find($userId);

        if (! $user || (int) $user->sub_department_id !== (int) $id) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $user->active = $user->active ? 0 : 1;
        $users->save($user);

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', $user->active ? 'Faculty member activated.' : 'Faculty member deactivated.');
    }

    public function deleteFaculty($id, $userId)
    {
        $users = auth()->getProvider();
        $user  = $users->find($userId);

        if (! $user || (int) $user->sub_department_id !== (int) $id) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $username = $user->username;
        $users->delete($userId);

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', "{$username} removed.");
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

    private function facultyMembersOf(int $subDeptId): array
    {
        return db_connect()->table('users u')
            ->select('u.id, u.username, u.active, i.secret as email')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('g.group', 'faculty')
            ->where('u.sub_department_id', $subDeptId)
            ->where('u.deleted_at', null)
            ->orderBy('u.username')
            ->get()
            ->getResultArray();
    }

    private function countInGroup(int $subDeptId, string $group): int
    {
        return db_connect()->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', $group)
            ->where('u.sub_department_id', $subDeptId)
            ->countAllResults();
    }
}
