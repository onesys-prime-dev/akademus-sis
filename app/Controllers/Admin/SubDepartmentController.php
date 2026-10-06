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
        $department['chair']  = $this->chairOf($id);
        $department['chairs'] = $this->allChairsOf($id);

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
            'assignableUsers' => $this->assignableUsers(),
        ]);
    }

    /**
     * Every active, non-deleted account — for the "Assign Chairperson"
     * modal's existing-user picker, so promoting someone already in the
     * system (most commonly an existing faculty member) doesn't require
     * re-typing their name/email and hoping it matches exactly.
     *
     * @return list<array{id: int, username: string, email: ?string, groups: list<string>}>
     */
    private function assignableUsers(): array
    {
        $rows = db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('u.deleted_at', null)
            ->orderBy('u.username')
            ->get()
            ->getResultArray();

        $groupsByUser = [];

        foreach (db_connect()->table('auth_groups_users')->select('user_id, `group`')->get()->getResultArray() as $g) {
            $groupsByUser[(int) $g['user_id']][] = $g['group'];
        }

        foreach ($rows as &$row) {
            $row['groups'] = $groupsByUser[(int) $row['id']] ?? [];
        }
        unset($row);

        return $rows;
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

        $existingUserId = (int) ($this->request->getPost('existing_user_id') ?? 0);

        if ($this->request->getPost('chair_mode') === 'existing' || $existingUserId > 0) {
            return $this->assignExistingChair($id, $department, $existingUserId);
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

        // A department has exactly one chairperson at a time — clear whoever
        // currently holds that slot (primary or delegated) before handing it
        // to this account. See clearDepartmentChair().
        $this->clearDepartmentChair($id, $user->id);

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

    /**
     * Assigns an ALREADY-EXISTING user as this department's chairperson —
     * never overwriting their primary sub_department_id if they're already
     * the primary chairperson of a DIFFERENT department (a real scenario:
     * one person chairing two related departments, e.g. a combined CS/IT
     * chairmanship, or a faculty member teaching across both). In that
     * case this grants DELEGATED access via sub_department_schedulers (the
     * mechanism SchedulingAuthorization::authorizedSubDeptIds() already
     * reads) instead of silently stealing them away from their first
     * department. Either way, THIS department still ends up with exactly
     * one chairperson — see clearDepartmentChair().
     */
    private function assignExistingChair(int $id, array $department, int $userId)
    {
        $users = auth()->getProvider();
        $user  = $users->findById($userId);

        if ($user === null) {
            return redirect()->back()->with('errors', ['Selected user not found.']);
        }

        if (! $user->inGroup('chairperson')) {
            $user->addGroup('chairperson');
        }

        $this->clearDepartmentChair($id, $userId);

        $db = db_connect();
        $currentSubDept = $db->table('users')->select('sub_department_id')->where('id', $user->id)->get()->getRowArray()['sub_department_id'] ?? null;

        if ($currentSubDept === null || (int) $currentSubDept === $id) {
            $db->table('users')
                ->where('id', $user->id)
                ->update([
                    'sub_department_id' => $id,
                    'department_id'     => $department['college_id'],
                ]);

            $message = "{$user->username} assigned as chairperson of {$department['name']}.";
        } else {
            $alreadyDelegated = $db->table('sub_department_schedulers')
                ->where('sub_dept_id', $id)
                ->where('user_id', $user->id)
                ->countAllResults() > 0;

            if (! $alreadyDelegated) {
                $db->table('sub_department_schedulers')->insert([
                    'sub_dept_id' => $id,
                    'user_id'     => $user->id,
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            }

            $message = "{$user->username} is already chairperson of another department, so they weren't moved — they've been granted additional chairperson access to {$department['name']} as well. Both departments remain under their management.";
        }

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', $message);
    }

    /**
     * Removes one chairperson's access to this department — the primary
     * chair (users.sub_department_id match) is detached (set null, same as
     * delete()'s department-deletion detach, never account-deleted since
     * they may still chair/teach elsewhere); a delegated chair (a
     * sub_department_schedulers row, see assignExistingChair()) simply has
     * that delegation row removed. Either way the user account itself is
     * untouched — this only revokes their management of THIS department.
     */
    public function removeChair($id, $userId)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $users = auth()->getProvider();
        $user  = $users->find($userId);

        if (! $user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db = db_connect();

        if ((int) $user->sub_department_id === (int) $id) {
            $db->table('users')->where('id', $user->id)->update(['sub_department_id' => null]);
        } else {
            $db->table('sub_department_schedulers')
                ->where('sub_dept_id', $id)
                ->where('user_id', $user->id)
                ->delete();
        }

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', "{$user->username} removed as chairperson of {$department['name']}.");
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
            'username'          => 'required|max_length[100]',
            'email'             => 'required|valid_email',
            'employment_status' => 'permit_empty|in_list[full_time,part_time]',
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
                'employment_status' => $this->request->getPost('employment_status') ?: null,
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
            'username'          => 'required|max_length[100]',
            'email'             => 'required|valid_email',
            'employment_status' => 'permit_empty|in_list[full_time,part_time]',
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

        db_connect()->table('users')
            ->where('id', $user->id)
            ->update(['employment_status' => $this->request->getPost('employment_status') ?: null]);

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

    /**
     * Enforces "one chairperson per department": clears whoever currently
     * holds this department's chair slot — the primary chair
     * (users.sub_department_id match, detached to null, account untouched)
     * and any delegated chair (sub_department_schedulers row, deleted) —
     * except $exceptUserId, so re-assigning the same person is a no-op
     * rather than momentarily unseating them. The person being newly
     * assigned may still chair OTHER departments; only this department's
     * single slot is cleared.
     */
    private function clearDepartmentChair(int $subDeptId, ?int $exceptUserId = null): void
    {
        $db = db_connect();

        $primaryIds = $db->table('users u')
            ->select('u.id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'chairperson')
            ->where('u.sub_department_id', $subDeptId)
            ->get()
            ->getResultArray();

        foreach ($primaryIds as $row) {
            if ($exceptUserId !== null && (int) $row['id'] === $exceptUserId) {
                continue;
            }

            $db->table('users')->where('id', $row['id'])->update(['sub_department_id' => null]);
        }

        $delegated = $db->table('sub_department_schedulers')->where('sub_dept_id', $subDeptId);

        if ($exceptUserId !== null) {
            $delegated->where('user_id !=', $exceptUserId);
        }

        $delegated->delete();
    }

    /**
     * The PRIMARY chairperson (sub_department_id match), falling back to
     * the first DELEGATED chairperson (sub_department_schedulers) when no
     * primary is set — e.g. someone whose primary department is CS but who
     * also chairs IT. See allChairsOf() for the full list when there's
     * more than one.
     */
    private function chairOf(int $subDeptId): ?array
    {
        $primary = db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'")
            ->where('g.group', 'chairperson')
            ->where('u.sub_department_id', $subDeptId)
            ->where('u.deleted_at', null)
            ->get()
            ->getRowArray();

        if ($primary !== null) {
            return $primary;
        }

        return db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email')
            ->join('sub_department_schedulers sds', 'sds.user_id = u.id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('sds.sub_dept_id', $subDeptId)
            ->where('u.deleted_at', null)
            ->get()
            ->getRowArray();
    }

    /**
     * Every chairperson who manages this department — primary plus any
     * delegated via sub_department_schedulers — for display when more than
     * one person is involved (a combined chairmanship across departments).
     *
     * @return list<array{id: int, username: string, email: ?string, is_primary: bool}>
     */
    private function allChairsOf(int $subDeptId): array
    {
        $primary = db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('g.group', 'chairperson')
            ->where('u.sub_department_id', $subDeptId)
            ->where('u.deleted_at', null)
            ->get()
            ->getResultArray();

        $delegated = db_connect()->table('users u')
            ->select('u.id, u.username, i.secret as email')
            ->join('sub_department_schedulers sds', 'sds.user_id = u.id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('sds.sub_dept_id', $subDeptId)
            ->where('u.deleted_at', null)
            ->get()
            ->getResultArray();

        $primaryIds = array_map(static fn (array $row) => (int) $row['id'], $primary);

        $all = array_map(static fn (array $row) => $row + ['is_primary' => true], $primary);

        foreach ($delegated as $row) {
            if (! in_array((int) $row['id'], $primaryIds, true)) {
                $all[] = $row + ['is_primary' => false];
            }
        }

        return $all;
    }

    private function facultyMembersOf(int $subDeptId): array
    {
        return db_connect()->table('users u')
            ->select('u.id, u.username, u.active, u.employment_status, i.secret as email')
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
