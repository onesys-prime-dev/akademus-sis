<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use App\Models\DepartmentModel;
use App\Models\FacultyDepartmentModel;
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
        $facultyMembers = $this->facultyMembersOf($id);

        $allDepartments = $this->subDeptModel->orderBy('name')->findAll();

        return view('admin/sub_departments/show', [
            'pageTitle'      => $department['name'],
            'activeAdmin'    => 'departments',
            'college'        => $college,
            'department'     => $department,
            'programs'       => $programs,
            'colleges'       => $colleges,
            'allDepartments' => $allDepartments,
            'facultyMembers' => $facultyMembers,
            // Affiliated count (primary + secondary), matching the list
            // below it — previously this was a separate primary-only query,
            // which would have silently disagreed with the roster once
            // secondary affiliations existed.
            'facultyCount'   => count($facultyMembers),
            'studentCount'   => $this->countInGroup($id, 'student'),
            'staffCandidates' => $this->staffCandidates((int) $id),
        ]);
    }

    /**
     * Staff accounts that can be granted a department-level role — active,
     * non-deleted users in the `faculty` or `chairperson` group.
     *
     * Deliberately NOT "every account" any more: the Assign Chairperson and
     * Add Faculty pickers used to list every student in the SIS, burying the
     * handful of real candidates under hundreds of rows. Students, admins and
     * registrars are never eligible for either role, so they're excluded at
     * the source (not merely hidden in the browser).
     *
     * Each row also carries what a useful picker needs: the roles they
     * already hold, which departments they chair, and whether that includes
     * THIS department — so the current chair can be labeled instead of
     * silently offered as a no-op.
     *
     * @return list<array{id:int, username:string, email:?string, roles:list<string>, chair_of:list<string>, is_current:bool, is_chairperson:bool, tone:int}>
     */
    private function staffCandidates(int $subDeptId): array
    {
        $db = db_connect();

        $rows = $db->table('users u')
            ->select('u.id, u.username, i.secret AS email')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->whereIn('g.group', ['faculty', 'chairperson'])
            ->where('u.deleted_at', null)
            ->where('u.active', 1)
            ->distinct()
            ->orderBy('u.username', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);

        $rolesByUser = [];

        foreach ($db->table('auth_groups_users')->select('user_id, `group`')->whereIn('user_id', $ids)->get()->getResultArray() as $g) {
            $rolesByUser[(int) $g['user_id']][] = (string) $g['group'];
        }

        // Every department each candidate currently chairs: the primary one
        // stored on users.sub_department_id, plus any delegated through
        // sub_department_schedulers (one person may chair more than one).
        $chairsByUser = [];

        $primary = $db->table('users u')
            ->select('u.id, sd.sub_dept_id, sd.name')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('sub_departments sd', 'sd.sub_dept_id = u.sub_department_id')
            ->where('g.group', 'chairperson')
            ->whereIn('u.id', $ids)
            ->get()
            ->getResultArray();

        $delegated = $db->table('sub_department_schedulers sds')
            ->select('sds.user_id, sd.sub_dept_id, sd.name')
            ->join('sub_departments sd', 'sd.sub_dept_id = sds.sub_dept_id')
            ->whereIn('sds.user_id', $ids)
            ->get()
            ->getResultArray();

        foreach (array_merge($primary, $delegated) as $row) {
            $userId = (int) ($row['id'] ?? $row['user_id']);
            $chairsByUser[$userId][(int) $row['sub_dept_id']] = (string) $row['name'];
        }

        $candidates = [];

        foreach ($rows as $row) {
            $userId     = (int) $row['id'];
            $roles      = array_values(array_unique($rolesByUser[$userId] ?? []));
            $chairs     = $chairsByUser[$userId] ?? [];
            $isChair    = in_array('chairperson', $roles, true) || $chairs !== [];

            $candidates[] = [
                'id'             => $userId,
                'username'       => (string) $row['username'],
                'email'          => $row['email'] !== null ? (string) $row['email'] : null,
                'roles'          => $roles,
                'chair_of'       => array_values($chairs),
                'is_current'     => array_key_exists($subDeptId, $chairs),
                'is_chairperson' => $isChair,
                // Deterministic avatar tone — restrained colour variety
                // without a per-user preference to configure or maintain.
                'tone'           => (crc32((string) $row['username']) % 6) + 1,
            ];
        }

        // Chairpersons first (a reassignment is almost always to someone who
        // already holds the role), then everyone alphabetically.
        usort($candidates, static function (array $a, array $b): int {
            if ($a['is_chairperson'] !== $b['is_chairperson']) {
                return $a['is_chairperson'] ? -1 : 1;
            }

            return strcasecmp($a['username'], $b['username']);
        });

        return $candidates;
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

        $mode           = (string) ($this->request->getPost('chair_mode') ?? '');
        $existingUserId = (int) ($this->request->getPost('existing_user_id') ?? 0);

        // A real "new" mode must win even if the form still carries a stale
        // existing_user_id (switching modes after picking someone used to
        // silently reassign that person instead of creating the account).
        // The no-mode fallback remains for legacy callers that only post an
        // id, e.g. the users page's retargeted create form.
        if ($mode === 'existing' || ($mode === '' && $existingUserId > 0)) {
            return $this->assignExistingChair($id, $department, $existingUserId);
        }

        $rules = [
            'username' => 'required|max_length[100]',
            'email'    => 'required|valid_email',
            'password' => 'permit_empty|min_length[8]',
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
            // Same optional-password pattern as UserController::store() —
            // an admin may set one explicitly, or leave it blank to
            // auto-generate.
            $tempPassword = (string) $this->request->getPost('password') ?: bin2hex(random_bytes(6));
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

        // A department has exactly one chairperson at a time. Lock the
        // department row for the duration of this transaction so a
        // concurrent assignment to the same department can't interleave
        // between clearing the old chair and setting the new one.
        $db = db_connect();
        $db->transStart();
        $this->subDeptModel->lockForUpdate($id);

        // Clear whoever currently holds that slot (primary or delegated)
        // before handing it to this account. See clearDepartmentChair().
        $this->clearDepartmentChair($id, $user->id);

        // sub_department_id scopes them to this department; department_id keeps the
        // parent college in sync, since scheduling (ChairpersonController/TeachingLoads)
        // still scopes chairpersons/faculty by college via users.department_id.
        $db->table('users')
            ->where('id', $user->id)
            ->update([
                'sub_department_id' => $id,
                'department_id'     => $department['college_id'],
            ]);

        $db->transComplete();

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
     *
     * Only ACTIVE faculty/chairperson accounts are accepted, mirroring the
     * picker's own filter — a crafted POST naming a student (or a disabled
     * account) is rejected rather than silently granting the role.
     */
    private function assignExistingChair(int $id, array $department, int $userId)
    {
        if ($userId <= 0) {
            return redirect()->back()->withInput()->with('errors', ['Choose a staff member to assign as chairperson.']);
        }

        $users = auth()->getProvider();
        $user  = $users->findById($userId);

        if ($user === null) {
            return redirect()->back()->withInput()->with('errors', ['Selected user not found.']);
        }

        // The picker only offers faculty/chairperson accounts — enforce the
        // same rule here so a hand-crafted POST can never turn a student (or
        // an inactive account) into a department chairperson.
        if (! $user->active) {
            return redirect()->back()->withInput()->with('errors', ['That account is inactive. Activate it first, or choose another staff member.']);
        }

        if (! $user->inGroup('faculty') && ! $user->inGroup('chairperson')) {
            return redirect()->back()->withInput()->with('errors', ['Only faculty and existing chairpersons can be assigned as chairperson. Pick a faculty member, or create a new account.']);
        }

        if (! $user->inGroup('chairperson')) {
            $user->addGroup('chairperson');
        }

        // Lock the department row for the duration of this transaction so a
        // concurrent assignment to the same department can't interleave
        // between clearing the old chair and setting the new one.
        $db = db_connect();
        $db->transStart();
        $this->subDeptModel->lockForUpdate($id);

        $this->clearDepartmentChair($id, $userId);

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

        $db->transComplete();

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

    /**
     * JSON: what Transfer Department would affect, for the confirmation
     * modal's preview step — read-only, nothing is written here.
     */
    public function transferPreview($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false, 'error' => 'Department not found.']);
        }

        $targetCollegeId = (int) $this->request->getPost('college_id');
        $targetCollege   = (new DepartmentModel())->find($targetCollegeId);

        if (! $targetCollege) {
            return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => 'College not found.']);
        }

        return $this->response->setJSON([
            'ok'             => true,
            'from'           => (new DepartmentModel())->find((int) $department['college_id'])['name'] ?? '—',
            'to'             => $targetCollege['name'],
            'same_as_current' => $targetCollegeId === (int) $department['college_id'],
            'affected'       => (new DepartmentModel())->departmentTransferImpact((int) $id),
        ]);
    }

    public function transfer($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $targetCollegeId = (int) $this->request->getPost('college_id');
        $targetCollege    = (new DepartmentModel())->find($targetCollegeId);
        $reason           = trim((string) $this->request->getPost('reason'));

        if (! $targetCollege) {
            return redirect()->back()->with('errors', ['College not found.']);
        }

        if ($reason === '') {
            return redirect()->back()->withInput()->with('errors', ['reason' => 'A reason is required for a transfer.']);
        }

        if ($targetCollegeId === (int) $department['college_id']) {
            return redirect()
                ->to("/admin/sub-departments/{$id}")
                ->with('message', "{$department['name']} is already under {$targetCollege['name']}.");
        }

        $oldCollegeId = (int) $department['college_id'];

        $this->subDeptModel->update($id, ['college_id' => $targetCollegeId]);

        // Keep every chairperson/faculty already scoped to this department in sync
        // with their new parent college for scheduling purposes.
        db_connect()->table('users')
            ->where('sub_department_id', $id)
            ->update(['department_id' => $targetCollegeId]);

        (new AuditLogModel())->record(
            'department.transferred',
            'sub_department',
            $id,
            ['college_id' => $oldCollegeId],
            ['college_id' => $targetCollegeId],
            $reason,
            (int) auth()->id(),
            'admin'
        );

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

        $name = trim($this->request->getPost('program_name'));

        // program_code is already globally unique (is_unique rule above);
        // program_name never was — scoped to this department, since a name
        // collision across two unrelated departments isn't necessarily
        // wrong, but one within the same department almost certainly is.
        $nameTaken = (new ProgramModel())->where('sub_department_id', $id)->where('program_name', $name)->countAllResults() > 0;

        if ($nameTaken) {
            return redirect()->back()->withInput()->with('errors', ['program_name' => "A Program named \"{$name}\" already exists in this Department."]);
        }

        (new ProgramModel())->insert([
            'sub_department_id' => $id,
            'program_code'      => strtoupper($this->request->getPost('program_code')),
            'program_name'      => $name,
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

    /**
     * Hard delete, blocked whenever anything still depends on this
     * department — previously this silently detached programs (NULLing
     * sub_department_id, immediately re-creating the exact "orphaned
     * program" problem Academic Health now flags) and never checked
     * subjects, faculty affiliations, chairpersons, or scheduler
     * delegations at all; faculty_departments/sub_department_schedulers
     * rows would have been silently CASCADE-deleted by their own FKs.
     * Deactivate (toggleStatus) is the supported way to retire a
     * department that still has history attached.
     */
    public function delete($id)
    {
        $department = $this->subDeptModel->find($id);

        if (! $department) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $collegeId = $department['college_id'];
        $db        = db_connect();

        $blockers = [];

        $programCount = $db->table('programs')->where('sub_department_id', $id)->countAllResults();
        if ($programCount > 0) {
            $blockers[] = "{$programCount} program(s)";
        }

        $subjectCount = $db->table('subjects')->where('sub_department_id', $id)->countAllResults();
        if ($subjectCount > 0) {
            $blockers[] = "{$subjectCount} owned subject(s)";
        }

        $facultyCount = $db->table('faculty_departments')->where('sub_dept_id', $id)->where('status', 'active')->countAllResults();
        if ($facultyCount > 0) {
            $blockers[] = "{$facultyCount} faculty affiliation(s)";
        }

        $chairCount = $db->table('users u')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'chairperson')
            ->where('u.sub_department_id', $id)
            ->countAllResults();
        if ($chairCount > 0) {
            $blockers[] = 'a chairperson';
        }

        $delegationCount = $db->table('sub_department_schedulers')->where('sub_dept_id', $id)->countAllResults();
        if ($delegationCount > 0) {
            $blockers[] = "{$delegationCount} scheduler delegation(s)";
        }

        if ($blockers !== []) {
            return redirect()
                ->to("/admin/sub-departments/{$id}")
                ->with('errors', ["Cannot delete {$department['name']} — it still has " . implode(', ', $blockers) . '. Transfer or remove those first, or deactivate the department instead.']);
        }

        $this->subDeptModel->delete($id);

        (new AuditLogModel())->record(
            'department.deleted',
            'sub_department',
            $id,
            $department,
            null,
            null,
            (int) auth()->id(),
            'admin'
        );

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

        $existingUserId = (int) ($this->request->getPost('existing_user_id') ?? 0);

        if ($this->request->getPost('faculty_mode') === 'existing' || $existingUserId > 0) {
            return $this->assignExistingFaculty($id, $department, $existingUserId);
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

    /**
     * Adds an ALREADY-EXISTING user as faculty AFFILIATED with this
     * department — no new account, no temp password, and (important) no
     * longer silently MOVES them here if they already belong elsewhere.
     * That used to be the behavior (a flat, single sub_department_id
     * overwrite) and it's exactly what accidentally relocated real faculty
     * out of their home department during testing. FacultyDepartmentModel::
     * addAffiliation() makes this their first-ever primary only if they
     * have none yet; otherwise it adds this department as a SECONDARY
     * affiliation and leaves their existing primary untouched. Promoting
     * someone to primary here is a separate, explicit action (see
     * setPrimaryAffiliation()), never an implicit side effect of "add."
     */
    private function assignExistingFaculty(int $id, array $department, int $userId)
    {
        $users = auth()->getProvider();
        $user  = $users->findById($userId);

        if ($user === null) {
            return redirect()->back()->with('errors', ['Selected user not found.']);
        }

        if (! $user->inGroup('faculty')) {
            $user->addGroup('faculty');
        }

        $employmentStatus = $this->request->getPost('employment_status');

        if ($employmentStatus !== null && $employmentStatus !== '' && ! in_array($employmentStatus, ['full_time', 'part_time'], true)) {
            return redirect()->back()->with('errors', ['employment_status' => 'Invalid employment status.']);
        }

        $result = (new FacultyDepartmentModel())->addAffiliation($userId, $id, false, (int) auth()->id(), 'admin');

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        if ($employmentStatus !== null && $employmentStatus !== '') {
            db_connect()->table('users')->where('id', $user->id)->update(['employment_status' => $employmentStatus]);
        }

        return redirect()
            ->to("/admin/sub-departments/{$id}")
            ->with('message', "{$user->username} added to {$department['name']} as faculty.");
    }

    /**
     * Adds a secondary affiliation for a faculty member already visible on
     * this (or any) department page — the "Manage Affiliations" action.
     * $targetSubDeptId is the department to affiliate with, which may be
     * unrelated to $id (the page the admin is currently viewing from).
     */
    public function addAffiliation($id, $userId)
    {
        $targetSubDeptId = (int) $this->request->getPost('target_sub_dept_id');

        if ($targetSubDeptId <= 0) {
            return redirect()->back()->with('errors', ['Choose a department.']);
        }

        $result = (new FacultyDepartmentModel())->addAffiliation((int) $userId, $targetSubDeptId, false, (int) auth()->id(), 'admin');

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to("/admin/sub-departments/{$id}")->with('message', 'Affiliation added.');
    }

    /**
     * Removes a SECONDARY affiliation (the model itself refuses to remove a
     * primary — see FacultyDepartmentModel::removeAffiliation()).
     */
    public function removeAffiliation($id, $userId)
    {
        $targetSubDeptId = (int) $this->request->getPost('target_sub_dept_id');

        $result = (new FacultyDepartmentModel())->removeAffiliation((int) $userId, $targetSubDeptId, (int) auth()->id(), 'admin');

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to("/admin/sub-departments/{$id}")->with('message', 'Affiliation removed.');
    }

    /**
     * Promotes an existing (primary or secondary) affiliation to primary —
     * demotes whichever department currently holds that slot and syncs
     * users.sub_department_id, transactionally, via
     * FacultyDepartmentModel::setPrimaryDepartment().
     */
    public function setPrimaryAffiliation($id, $userId)
    {
        $targetSubDeptId = (int) $this->request->getPost('target_sub_dept_id');

        if ($targetSubDeptId <= 0) {
            return redirect()->back()->with('errors', ['Choose a department.']);
        }

        $result = (new FacultyDepartmentModel())->setPrimaryDepartment((int) $userId, $targetSubDeptId, (int) auth()->id(), 'admin', 'Changed via admin department page');

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to("/admin/sub-departments/{$id}")->with('message', 'Primary department updated.');
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

    /**
     * Every faculty member AFFILIATED with this department (primary or
     * secondary) — previously this only matched u.sub_department_id
     * (primary-only), so a faculty member teaching here as a secondary
     * affiliation was invisible on the department they actually teach for.
     * Each row carries is_primary_here (is THIS department their home) plus
     * their full affiliation list, so the view can show e.g. "Theo Abella —
     * Primary: IT, Affiliated here: CS" instead of implying a move.
     *
     * Batched: one query for the department roster, one for every row's
     * full affiliation set — never one affiliation query per faculty row.
     */
    private function facultyMembersOf(int $subDeptId): array
    {
        $affiliations = new FacultyDepartmentModel();
        $userIds      = $affiliations->userIdsForDepartment($subDeptId);

        if ($userIds === []) {
            return [];
        }

        $rows = db_connect()->table('users u')
            ->select('u.id, u.username, u.active, u.employment_status, i.secret as email')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('g.group', 'faculty')
            ->whereIn('u.id', $userIds)
            ->where('u.deleted_at', null)
            ->orderBy('u.username')
            ->get()
            ->getResultArray();

        $allAffiliationsByUser = $affiliations->departmentsForFacultyBatch(array_map(static fn (array $r) => (int) $r['id'], $rows));

        foreach ($rows as &$row) {
            $userId = (int) $row['id'];
            $own    = $allAffiliationsByUser[$userId] ?? [];

            $row['is_primary_here'] = false;
            $row['affiliations']    = $own;

            foreach ($own as $aff) {
                if ($aff['sub_dept_id'] === $subDeptId && $aff['is_primary']) {
                    $row['is_primary_here'] = true;
                }
            }
        }
        unset($row);

        return $rows;
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
