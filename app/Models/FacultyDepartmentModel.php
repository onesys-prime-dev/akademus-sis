<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Faculty <-> department many-to-many affiliation — see
 * CreateFacultyDepartments migration for the full rationale. This is the
 * one place that reads/writes faculty_departments; nothing else should
 * query the table directly, so the "exactly one active primary" and
 * "users.sub_department_id stays in sync with the primary" invariants can
 * never be bypassed from a second code path.
 *
 * Affiliation answers "which departments is this person associated with,"
 * never "which departments may this person manage" — that is
 * SchedulingAuthorization's job (sub_department_schedulers), a completely
 * separate table this model never touches.
 */
class FacultyDepartmentModel extends Model
{
    protected $table      = 'faculty_departments';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'user_id',
        'sub_dept_id',
        'is_primary',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * Every active affiliation for one faculty member, with the
     * department's own code/name joined in, primary first.
     *
     * @return list<array{id: int, sub_dept_id: int, is_primary: bool, code: string, name: string}>
     */
    public function departmentsForFaculty(int $userId): array
    {
        $rows = $this->db->table('faculty_departments fd')
            ->select('fd.id, fd.sub_dept_id, fd.is_primary, fd.status, sd.code, sd.name')
            ->join('sub_departments sd', 'sd.sub_dept_id = fd.sub_dept_id')
            ->where('fd.user_id', $userId)
            ->where('fd.status', 'active')
            ->orderBy('fd.is_primary', 'DESC')
            ->orderBy('sd.name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $row['id']          = (int) $row['id'];
            $row['sub_dept_id'] = (int) $row['sub_dept_id'];
            $row['is_primary']  = (bool) $row['is_primary'];
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<int>
     */
    public function activeDepartmentIdsForFaculty(int $userId): array
    {
        return array_map('intval', array_column($this->departmentsForFaculty($userId), 'sub_dept_id'));
    }

    public function primaryDepartmentForFaculty(int $userId): ?array
    {
        foreach ($this->departmentsForFaculty($userId) as $row) {
            if ($row['is_primary']) {
                return $row;
            }
        }

        return null;
    }

    public function isAffiliatedWith(int $userId, int $subDeptId): bool
    {
        return $this->where('user_id', $userId)
            ->where('sub_dept_id', $subDeptId)
            ->where('status', 'active')
            ->countAllResults() > 0;
    }

    /**
     * Every actively-affiliated faculty user_id for one department (primary
     * + secondary) — the batched query behind both the chairperson faculty
     * picker and the department admin page's faculty list, so listing N
     * faculty never costs N affiliation queries.
     *
     * @return list<int>
     */
    public function userIdsForDepartment(int $subDeptId): array
    {
        return array_map('intval', array_column(
            $this->where('sub_dept_id', $subDeptId)->where('status', 'active')->findAll(),
            'user_id'
        ));
    }

    /**
     * Batched equivalent of departmentsForFaculty() for a whole roster —
     * one query instead of one per faculty row.
     *
     * @param list<int> $userIds
     *
     * @return array<int, list<array{id: int, sub_dept_id: int, is_primary: bool, code: string, name: string}>>
     */
    public function departmentsForFacultyBatch(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->db->table('faculty_departments fd')
            ->select('fd.id, fd.user_id, fd.sub_dept_id, fd.is_primary, sd.code, sd.name')
            ->join('sub_departments sd', 'sd.sub_dept_id = fd.sub_dept_id')
            ->whereIn('fd.user_id', $userIds)
            ->where('fd.status', 'active')
            ->orderBy('fd.is_primary', 'DESC')
            ->orderBy('sd.name', 'ASC')
            ->get()
            ->getResultArray();

        $byUser = [];

        foreach ($rows as $row) {
            $byUser[(int) $row['user_id']][] = [
                'id'          => (int) $row['id'],
                'sub_dept_id' => (int) $row['sub_dept_id'],
                'is_primary'  => (bool) $row['is_primary'],
                'code'        => $row['code'],
                'name'        => $row['name'],
            ];
        }

        return $byUser;
    }

    /**
     * Adds (or reactivates) an affiliation. A brand-new faculty member with
     * no affiliations yet always gets their first one as primary, regardless
     * of $asPrimary — someone must be primary. Promoting an existing
     * secondary to primary should go through setPrimaryDepartment() instead,
     * which also demotes the old one; this method refuses to silently create
     * a second primary.
     *
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function addAffiliation(int $userId, int $subDeptId, bool $asPrimary, ?int $actorId, ?string $actorRole = null): array
    {
        $department = (new SubDepartmentModel())->find($subDeptId);

        if ($department === null) {
            return ['ok' => false, 'error' => 'Department not found.'];
        }

        $existing = $this->where('user_id', $userId)->where('sub_dept_id', $subDeptId)->first();
        $hasAnyPrimary = $this->where('user_id', $userId)->where('is_primary', 1)->where('status', 'active')->countAllResults() > 0;

        if ($existing !== null && $existing['status'] === 'active') {
            return ['ok' => false, 'error' => 'This faculty member is already affiliated with that department.'];
        }

        $makePrimary = $asPrimary || ! $hasAnyPrimary;

        if ($makePrimary && $hasAnyPrimary && $asPrimary) {
            // Caller explicitly asked for primary but one already exists —
            // route through the dedicated transactional swap instead of
            // creating a second primary here.
            return $this->setPrimaryDepartment($userId, $subDeptId, $actorId, $actorRole, 'Set via addAffiliation');
        }

        $db = $this->db;
        $db->transStart();

        if ($existing !== null) {
            $this->update($existing['id'], ['status' => 'active', 'is_primary' => $makePrimary ? 1 : 0]);
            $id = (int) $existing['id'];
        } else {
            $this->insert(['user_id' => $userId, 'sub_dept_id' => $subDeptId, 'is_primary' => $makePrimary ? 1 : 0, 'status' => 'active']);
            $id = $this->getInsertID();
        }

        if ($makePrimary) {
            $this->syncPrimaryToUser($userId, $subDeptId);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not add this affiliation. Please retry.'];
        }

        (new AuditLogModel())->record(
            'faculty_department.added',
            'faculty_department',
            $id,
            null,
            ['user_id' => $userId, 'sub_dept_id' => $subDeptId, 'is_primary' => $makePrimary],
            null,
            $actorId,
            $actorRole
        );

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Removes one SECONDARY affiliation. Always refuses to remove the
     * primary — a faculty member must always have exactly one once they
     * have any at all; to drop a primary department, call
     * setPrimaryDepartment() with a different target first (which demotes
     * the old one), then remove it as a now-secondary affiliation.
     *
     * @return array{ok: bool, error?: string}
     */
    public function removeAffiliation(int $userId, int $subDeptId, ?int $actorId, ?string $actorRole = null): array
    {
        $row = $this->where('user_id', $userId)->where('sub_dept_id', $subDeptId)->where('status', 'active')->first();

        if ($row === null) {
            return ['ok' => false, 'error' => 'That affiliation does not exist.'];
        }

        if ($row['is_primary']) {
            return ['ok' => false, 'error' => 'Set a different department as primary before removing this one.'];
        }

        $this->delete($row['id']);

        (new AuditLogModel())->record(
            'faculty_department.removed',
            'faculty_department',
            $row['id'],
            ['user_id' => $userId, 'sub_dept_id' => $subDeptId, 'is_primary' => (bool) $row['is_primary']],
            null,
            null,
            $actorId,
            $actorRole
        );

        return ['ok' => true];
    }

    /**
     * Transactionally makes $subDeptId this faculty member's primary —
     * demotes whichever row currently holds is_primary (if any), promotes/
     * creates the target as primary, and syncs users.sub_department_id to
     * match. Never allows two active primaries to exist at once.
     *
     * @return array{ok: bool, error?: string}
     */
    public function setPrimaryDepartment(int $userId, int $subDeptId, ?int $actorId, ?string $actorRole = null, ?string $reason = null): array
    {
        $department = (new SubDepartmentModel())->find($subDeptId);

        if ($department === null) {
            return ['ok' => false, 'error' => 'Department not found.'];
        }

        $currentPrimary = $this->where('user_id', $userId)->where('is_primary', 1)->where('status', 'active')->first();

        if ($currentPrimary !== null && (int) $currentPrimary['sub_dept_id'] === $subDeptId) {
            return ['ok' => false, 'error' => 'That is already the primary department.'];
        }

        $db = $this->db;
        $db->transStart();

        if ($currentPrimary !== null) {
            $this->update($currentPrimary['id'], ['is_primary' => 0]);
        }

        $target = $this->where('user_id', $userId)->where('sub_dept_id', $subDeptId)->first();

        if ($target !== null) {
            $this->update($target['id'], ['is_primary' => 1, 'status' => 'active']);
            $targetId = (int) $target['id'];
        } else {
            $this->insert(['user_id' => $userId, 'sub_dept_id' => $subDeptId, 'is_primary' => 1, 'status' => 'active']);
            $targetId = $this->getInsertID();
        }

        $this->syncPrimaryToUser($userId, $subDeptId);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not change the primary department. Please retry.'];
        }

        (new AuditLogModel())->record(
            'faculty_department.primary_changed',
            'faculty_department',
            $targetId,
            $currentPrimary !== null ? ['sub_dept_id' => (int) $currentPrimary['sub_dept_id']] : null,
            ['user_id' => $userId, 'sub_dept_id' => $subDeptId],
            $reason,
            $actorId,
            $actorRole
        );

        return ['ok' => true];
    }

    private function syncPrimaryToUser(int $userId, int $subDeptId): void
    {
        $user = db_connect()->table('users')->select('department_id')->where('id', $userId)->get()->getRowArray();

        $update = ['sub_department_id' => $subDeptId];

        // Keep the college-level department_id consistent with the new
        // primary's own college, mirroring how chairperson/faculty
        // assignment already sets both together elsewhere (e.g.
        // Admin\SubDepartmentController::addFaculty()).
        $department = (new SubDepartmentModel())->find($subDeptId);

        if ($department !== null) {
            $update['department_id'] = $department['college_id'];
        }

        db_connect()->table('users')->where('id', $userId)->update($update);
    }
}
