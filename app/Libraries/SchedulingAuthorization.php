<?php

namespace App\Libraries;

use App\Models\DepartmentDeanModel;
use App\Models\DepartmentModel;
use App\Models\SectionModel;
use App\Models\SubDepartmentSchedulerModel;

/**
 * Centralizes the "does this user own this section?" check that used to be
 * duplicated, verbatim, between ChairpersonController and
 * Chairperson\OfferingController. Behavior for existing users is unchanged:
 * a user's authorized sub-departments still defaults to just their own.
 * sub_department_schedulers is the additive extension point — a user gains
 * extra sub-departments only when a row explicitly grants it (see
 * CreateSubDepartmentSchedulers migration).
 */
class SchedulingAuthorization
{
    /**
     * Every sub_dept_id this user may schedule for: their own sub-department
     * (if any) plus any sub-departments they've been explicitly delegated.
     *
     * @return list<int>
     */
    public function authorizedSubDeptIds(int $userId): array
    {
        $ids = [];

        $subDept = (new DepartmentModel())->subDeptOfUser($userId);

        if ($subDept !== null) {
            $ids[] = (int) $subDept['sub_dept_id'];
        }

        foreach ((new SubDepartmentSchedulerModel())->subDeptIdsForUser($userId) as $delegatedId) {
            if (! in_array($delegatedId, $ids, true)) {
                $ids[] = $delegatedId;
            }
        }

        return $ids;
    }

    /**
     * Every dept_id (College) this user may act as dean for: their own
     * primary college (if any, and only while they hold the 'dean' group)
     * plus any colleges they've been explicitly delegated. Dean's
     * counterpart to authorizedSubDeptIds().
     *
     * @return list<int>
     */
    public function authorizedDepartmentIds(int $userId): array
    {
        $ids = [];

        $college = (new DepartmentModel())->collegeOfUser($userId);

        if ($college !== null) {
            $ids[] = (int) $college['dept_id'];
        }

        foreach ((new DepartmentDeanModel())->deptIdsForUser($userId) as $delegatedId) {
            if (! in_array($delegatedId, $ids, true)) {
                $ids[] = $delegatedId;
            }
        }

        return $ids;
    }

    /**
     * The section row, only if it belongs to a sub-department this user is
     * authorized to schedule for.
     */
    public function ownSection(int $userId, int $sectionId): ?array
    {
        return $this->matchSection($userId, $sectionId)['section'];
    }

    /**
     * Which of this user's authorized sub_dept_ids owns $sectionId's program,
     * or null if none does (unauthorized, or section not found). Used to
     * snapshot "which unit is asking" on a placement request.
     */
    public function subDeptForSection(int $userId, int $sectionId): ?int
    {
        return $this->matchSection($userId, $sectionId)['sub_dept_id'];
    }

    /**
     * @return array{section: ?array, sub_dept_id: ?int}
     */
    private function matchSection(int $userId, int $sectionId): array
    {
        $subDeptIds = $this->authorizedSubDeptIds($userId);

        if ($subDeptIds === []) {
            return ['section' => null, 'sub_dept_id' => null];
        }

        $section = (new SectionModel())->find($sectionId);

        if ($section === null) {
            return ['section' => null, 'sub_dept_id' => null];
        }

        // Preferred path: section.program_id -> programs.sub_department_id,
        // a single direct lookup instead of looping every authorized
        // sub-department and re-deriving its program name list just to
        // string-compare. This is also what makes a Program rename or a
        // Program transfer to a different department resolve correctly
        // automatically — the FK is the identity, not the text.
        if ($section['program_id'] !== null) {
            $ownerSubDeptId = db_connect()->table('programs')
                ->select('sub_department_id')
                ->where('program_id', $section['program_id'])
                ->get()->getRowArray()['sub_department_id'] ?? null;

            if ($ownerSubDeptId === null) {
                return ['section' => null, 'sub_dept_id' => null];
            }

            $ownerSubDeptId = (int) $ownerSubDeptId;

            return in_array($ownerSubDeptId, $subDeptIds, true)
                ? ['section' => $section, 'sub_dept_id' => $ownerSubDeptId]
                : ['section' => null, 'sub_dept_id' => null];
        }

        // Legacy fallback — only reached for a section never backfilled
        // with program_id (pre-dating the FK hardening migration).
        $departmentModel = new DepartmentModel();

        foreach ($subDeptIds as $subDeptId) {
            $programs = $departmentModel->programsOfSubDept($subDeptId);

            if (in_array($section['program'], $programs, true)) {
                return ['section' => $section, 'sub_dept_id' => $subDeptId];
            }
        }

        return ['section' => null, 'sub_dept_id' => null];
    }

    /**
     * Whether this user is authorized to manage (review/decide requests for)
     * the given subject — derived from subjects.sub_department_id, never
     * from subject prefix, program, or the requesting section. Returns false
     * when the subject has no owning unit set (nothing to be authorized
     * against) or doesn't exist.
     */
    public function canManageSubject(int $userId, int $subjectId): bool
    {
        $subject = db_connect()->table('subjects')
            ->select('sub_department_id')
            ->where('subject_id', $subjectId)
            ->get()
            ->getRowArray();

        if ($subject === null || $subject['sub_department_id'] === null) {
            return false;
        }

        return in_array((int) $subject['sub_department_id'], $this->authorizedSubDeptIds($userId), true);
    }

    /**
     * The single, centralized place that decides whether a user may bypass
     * normal subject-ownership authorization for scheduling workflows (e.g.
     * Registrar/Admin reviewing a placement request for a unit they don't
     * belong to). Backed today by Shield group membership — kept as one
     * method so the actual mechanism can later move to a configurable
     * permission without every calling workflow needing to change.
     */
    public function canOverrideScheduling(int $userId): bool
    {
        return db_connect()->table('auth_groups_users')
            ->where('user_id', $userId)
            ->whereIn('group', ['registrar', 'admin', 'superadmin'])
            ->countAllResults() > 0;
    }

    /**
     * Whether $userId may manage (review, plot, edit) something owned by
     * $subjectId — either because they normally manage that subject's unit,
     * or because they're using the centralized override. Previously
     * duplicated in OfferingPlacementRequestModel::authorizeReview(); that
     * method now delegates here.
     *
     * @return array{ok: bool, override: bool}
     */
    public function authorizeManageSubject(int $userId, int $subjectId): array
    {
        if ($this->canManageSubject($userId, $subjectId)) {
            return ['ok' => true, 'override' => false];
        }

        if ($this->canOverrideScheduling($userId)) {
            return ['ok' => true, 'override' => true];
        }

        return ['ok' => false, 'override' => false];
    }
}
