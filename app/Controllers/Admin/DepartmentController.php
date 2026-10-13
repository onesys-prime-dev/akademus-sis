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

        if (! $user->inGroup('dean')) {
            $user->addGroup('dean');
        }

        // A college has exactly one primary dean at a time — clear whoever
        // currently holds that slot (primary or delegated) before handing
        // it to this account. Mirrors clearDepartmentChair().
        $this->clearCollegeDean($id, $user->id);

        db_connect()->table('users')
            ->where('id', $user->id)
            ->update(['department_id' => $id]);

        $message = "{$username} assigned as dean of {$college['name']}.";

        if ($tempPassword) {
            $message .= " Temporary password: {$tempPassword} (share this with them — it won't be shown again).";
        }

        return redirect()
            ->to("/admin/departments/{$id}")
            ->with('message', $message);
    }

    /**
     * Removing a college only makes sense once nothing depends on it —
     * otherwise its departments, programs, faculty, and schedules would be
     * left pointing at a college_id that no longer exists. Blocked instead
     * of cascading, same as the rest of the app's delete guards (subjects
     * with plotted classes, etc.).
     */
    public function delete($id)
    {
        $college = $this->departmentModel->find($id);

        if (! $college) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $deptCount = db_connect()->table('sub_departments')->where('college_id', $id)->countAllResults();

        if ($deptCount > 0) {
            return redirect()
                ->to('/admin/departments')
                ->with('errors', ["Cannot delete {$college['name']} — it still has {$deptCount} department" . ($deptCount === 1 ? '' : 's') . '. Remove those first.']);
        }

        // Detach (not delete) the dean account — the college is gone, but
        // the person's account and other roles, if any, are untouched.
        $dean = $this->deanOf((int) $id);
        if ($dean !== null) {
            db_connect()->table('users')->where('id', $dean['id'])->update(['department_id' => null]);
        }

        $this->departmentModel->delete($id);

        return redirect()
            ->to('/admin/departments')
            ->with('message', "{$college['name']} deleted.");
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

        $code = strtoupper(trim($this->request->getPost('dept_code')));
        $name = trim($this->request->getPost('dept_name'));

        // Scoped to this college, not global — is_unique[] alone can't
        // express "unique within college_id", so this is a plain manual
        // check (previously there was no uniqueness check on department
        // code at all).
        $codeTaken = (new SubDepartmentModel())->where('college_id', $id)->where('code', $code)->countAllResults() > 0;

        if ($codeTaken) {
            return redirect()->back()->withInput()->with('errors', ['dept_code' => "Department code \"{$code}\" is already used in this College."]);
        }

        $nameTaken = (new SubDepartmentModel())->where('college_id', $id)->where('name', $name)->countAllResults() > 0;

        if ($nameTaken) {
            return redirect()->back()->withInput()->with('errors', ['dept_name' => "A Department named \"{$name}\" already exists in this College."]);
        }

        (new SubDepartmentModel())->insert([
            'college_id' => $id,
            'code'       => $code,
            'name'       => $name,
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

    /**
     * Clears whoever currently holds this college's dean slot — the
     * PRIMARY dean (users.department_id match, detached to null, account
     * untouched) and any DELEGATED dean (department_deans row, deleted) —
     * except $exceptUserId, so re-assigning the same person is a no-op
     * rather than momentarily unseating them. Mirrors
     * SubDepartmentController::clearDepartmentChair() exactly.
     */
    private function clearCollegeDean(int $deptId, ?int $exceptUserId = null): void
    {
        $db = db_connect();

        $primaryIds = $db->table('users u')
            ->select('u.id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'dean')
            ->where('u.department_id', $deptId)
            ->get()
            ->getResultArray();

        foreach ($primaryIds as $row) {
            if ($exceptUserId !== null && (int) $row['id'] === $exceptUserId) {
                continue;
            }

            $db->table('users')->where('id', $row['id'])->update(['department_id' => null]);
        }

        $delegated = $db->table('department_deans')->where('dept_id', $deptId);

        if ($exceptUserId !== null) {
            $delegated->where('user_id !=', $exceptUserId);
        }

        $delegated->delete();
    }

    /**
     * Removes one dean's access to this college — the primary dean
     * (users.department_id match) is detached (set null, account
     * untouched); a delegated dean (department_deans row) simply has that
     * delegation row removed. Mirrors
     * SubDepartmentController::removeChair() exactly.
     */
    public function removeDean($id, $userId)
    {
        $college = $this->departmentModel->find($id);

        if (! $college) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $users = auth()->getProvider();
        $user  = $users->find($userId);

        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db = db_connect();

        if ((int) $user->department_id === (int) $id) {
            $db->table('users')->where('id', $user->id)->update(['department_id' => null]);
        } else {
            $db->table('department_deans')
                ->where('dept_id', $id)
                ->where('user_id', $userId)
                ->delete();
        }

        return redirect()
            ->to("/admin/departments/{$id}")
            ->with('message', "{$user->username} removed as dean of {$college['name']}.");
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
