<?php

namespace App\Libraries;

/**
 * Read-only academic-hierarchy integrity audit (College -> Department ->
 * Program -> student/section/faculty relationships). Every check here is a
 * real, independently-verifiable query against the live schema — nothing
 * is a placeholder metric, and nothing here auto-repairs anything; this is
 * a diagnostic, not a fixer. Admin-only (see Admin\AcademicHealthController).
 *
 * Findings are grouped CRITICAL (a relationship the app depends on is
 * actually broken — an ID pointing nowhere, an invariant violated),
 * WARNING (functions, but is operationally wrong or will surprise
 * someone), or INFORMATIONAL (worth knowing, not a problem by itself).
 */
class AcademicHealthService
{
    /**
     * @return list<array{severity: string, category: string, entity_type: string, entity_id: int|string|null, message: string, suggestion: string}>
     */
    public function check(): array
    {
        $findings = [];

        $this->checkProgramsWithoutDepartment($findings);
        $this->checkProgramsPointingToMissingDepartment($findings);
        $this->checkDepartmentsWithoutCollege($findings);
        $this->checkFacultyPrimaryAffiliation($findings);
        $this->checkUsersSubDepartmentDriftFromPrimary($findings);
        $this->checkDepartmentsNamedLikeTheirPrograms($findings);
        $this->checkDuplicateDepartmentCodes($findings);
        $this->checkDuplicateProgramCodes($findings);
        $this->checkInactiveProgramsStillScheduled($findings);
        $this->checkCurriculaPointingToMissingPrograms($findings);
        $this->checkUnresolvedSections($findings);
        $this->checkAmbiguousSections($findings);
        $this->checkInvalidChairpersonAssignment($findings);
        $this->checkStaleSchedulerDelegations($findings);
        $this->checkOrphanedSubjectOwnership($findings);
        $this->checkDepartmentsWithoutChairperson($findings);
        $this->checkInactiveProgramsWithHistoricalStudents($findings);

        return $findings;
    }

    private function add(array &$findings, string $severity, string $category, string $entityType, $entityId, string $message, string $suggestion): void
    {
        $findings[] = [
            'severity'    => $severity,
            'category'    => $category,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'message'     => $message,
            'suggestion'  => $suggestion,
        ];
    }

    private function checkProgramsWithoutDepartment(array &$findings): void
    {
        $rows = db_connect()->table('programs')->where('sub_department_id', null)->get()->getResultArray();

        foreach ($rows as $r) {
            $this->add($findings, 'warning', 'hierarchy', 'program', (int) $r['program_id'],
                "Program \"{$r['program_name']}\" ({$r['program_code']}) has no Department.",
                'Assign it to a Department via Transfer Program, or via the Department\'s Add Program action.');
        }
    }

    private function checkProgramsPointingToMissingDepartment(array &$findings): void
    {
        $rows = db_connect()->table('programs p')
            ->select('p.program_id, p.program_code, p.program_name')
            ->join('sub_departments sd', 'sd.sub_dept_id = p.sub_department_id', 'left')
            ->where('p.sub_department_id IS NOT NULL')
            ->where('sd.sub_dept_id IS NULL')
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $this->add($findings, 'critical', 'hierarchy', 'program', (int) $r['program_id'],
                "Program \"{$r['program_name']}\" ({$r['program_code']}) points to a Department that no longer exists.",
                'Reassign to a valid Department immediately — this program is currently unmanageable by any Chairperson.');
        }
    }

    private function checkDepartmentsWithoutCollege(array &$findings): void
    {
        $rows = db_connect()->table('sub_departments sd')
            ->select('sd.sub_dept_id, sd.name')
            ->join('departments d', 'd.dept_id = sd.college_id', 'left')
            ->where('d.dept_id IS NULL')
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $this->add($findings, 'critical', 'hierarchy', 'sub_department', (int) $r['sub_dept_id'],
                "Department \"{$r['name']}\" points to a College that no longer exists.",
                'This should be prevented by a foreign key — investigate how it happened and reassign to a valid College.');
        }
    }

    private function checkFacultyPrimaryAffiliation(array &$findings): void
    {
        $db = db_connect();

        $facultyIds = array_map('intval', array_column(
            $db->table('auth_groups_users')->select('user_id')->where('group', 'faculty')->get()->getResultArray(),
            'user_id'
        ));

        if ($facultyIds === []) {
            return;
        }

        $primaryCounts = $db->table('faculty_departments')
            ->select('user_id, COUNT(*) AS n')
            ->whereIn('user_id', $facultyIds)
            ->where('is_primary', 1)
            ->where('status', 'active')
            ->groupBy('user_id')
            ->get()->getResultArray();

        $countByUser = array_column($primaryCounts, 'n', 'user_id');

        $usernames = array_column(
            $db->table('users')->select('id, username')->whereIn('id', $facultyIds)->get()->getResultArray(),
            'username', 'id'
        );

        foreach ($facultyIds as $id) {
            $n = (int) ($countByUser[$id] ?? 0);

            if ($n === 0) {
                $this->add($findings, 'warning', 'faculty', 'user', $id,
                    "Faculty member \"{$usernames[$id]}\" has no active primary Department affiliation.",
                    'Assign a primary Department via Faculty Affiliations.');
            } elseif ($n > 1) {
                $this->add($findings, 'critical', 'faculty', 'user', $id,
                    "Faculty member \"{$usernames[$id]}\" has {$n} active primary Department affiliations — exactly one is required.",
                    'Use Set Primary on the intended Department; FacultyDepartmentModel::setPrimaryDepartment() enforces exactly one, so this indicates a direct data edit bypassed it.');
            }
        }
    }

    private function checkUsersSubDepartmentDriftFromPrimary(array &$findings): void
    {
        $db = db_connect();

        $rows = $db->table('users u')
            ->select('u.id, u.username, u.sub_department_id, fd.sub_dept_id AS primary_sub_dept_id')
            ->join('auth_groups_users g', 'g.user_id = u.id AND g.group = \'faculty\'')
            ->join('faculty_departments fd', 'fd.user_id = u.id AND fd.is_primary = 1 AND fd.status = \'active\'', 'left')
            ->where('u.deleted_at', null)
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $usersCol = $r['sub_department_id'] !== null ? (int) $r['sub_department_id'] : null;
            $primary  = $r['primary_sub_dept_id'] !== null ? (int) $r['primary_sub_dept_id'] : null;

            if ($usersCol !== $primary) {
                $this->add($findings, 'warning', 'faculty', 'user', (int) $r['id'],
                    "Faculty \"{$r['username']}\"'s users.sub_department_id ({$this->fmt($usersCol)}) does not match their faculty_departments primary ({$this->fmt($primary)}).",
                    'Re-save their primary Department via Faculty Affiliations to resync both.');
            }
        }
    }

    private function fmt(?int $v): string
    {
        return $v === null ? 'none' : (string) $v;
    }

    private function checkDepartmentsNamedLikeTheirPrograms(array &$findings): void
    {
        $db = db_connect();

        $rows = $db->table('sub_departments sd')
            ->select('sd.sub_dept_id, sd.name, p.program_id, p.program_name')
            ->join('programs p', 'p.sub_department_id = sd.sub_dept_id')
            ->get()->getResultArray();

        foreach ($rows as $r) {
            if (mb_strtolower(trim($r['name'])) === mb_strtolower(trim($r['program_name']))) {
                $this->add($findings, 'warning', 'hierarchy', 'sub_department', (int) $r['sub_dept_id'],
                    "Department \"{$r['name']}\" is named identically to its own Program \"{$r['program_name']}\" — this is the \"Program stored as a Department\" pattern flagged in the earlier audit.",
                    'Rename the Department to a real organizational name (e.g. "Department of X"), not a degree title.');
            }
        }
    }

    private function checkDuplicateDepartmentCodes(array &$findings): void
    {
        $rows = db_connect()->table('sub_departments')
            ->select('college_id, code, COUNT(*) AS n, GROUP_CONCAT(sub_dept_id) AS ids')
            ->groupBy('college_id, code')
            ->having('n >', 1)
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $this->add($findings, 'critical', 'hierarchy', 'sub_department', $r['ids'],
                "Department code \"{$r['code']}\" is used by {$r['n']} Departments in the same College.",
                'Rename one of the duplicate codes — codes should be unique within a College.');
        }
    }

    private function checkDuplicateProgramCodes(array &$findings): void
    {
        $rows = db_connect()->table('programs')
            ->select('program_code, COUNT(*) AS n, GROUP_CONCAT(program_id) AS ids')
            ->groupBy('program_code')
            ->having('n >', 1)
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $this->add($findings, 'critical', 'hierarchy', 'program', $r['ids'],
                "Program code \"{$r['program_code']}\" is used by {$r['n']} Programs.",
                'Program codes must be unique — rename or merge the duplicates.');
        }
    }

    private function checkInactiveProgramsStillScheduled(array &$findings): void
    {
        $db = db_connect();

        $inactive = $db->table('programs')->where('is_active', 0)->get()->getResultArray();

        foreach ($inactive as $p) {
            $sectionCount = $db->table('sections')->where('program_id', $p['program_id'])->whereIn('status', ['proposed', 'open'])->countAllResults();

            if ($sectionCount > 0) {
                $this->add($findings, 'warning', 'scheduling', 'program', (int) $p['program_id'],
                    "Inactive Program \"{$p['program_name']}\" still has {$sectionCount} open/proposed section(s).",
                    'Close those sections or reactivate the Program if it\'s still meant to be offered.');
            }
        }
    }

    private function checkCurriculaPointingToMissingPrograms(array &$findings): void
    {
        $rows = db_connect()->table('curriculum c')
            ->select('c.curriculum_id, c.curriculum_code')
            ->join('programs p', 'p.program_id = c.program_id', 'left')
            ->where('p.program_id IS NULL')
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $this->add($findings, 'critical', 'curriculum', 'curriculum', (int) $r['curriculum_id'],
                "Curriculum \"{$r['curriculum_code']}\" points to a Program that no longer exists.",
                'This curriculum is orphaned — investigate before any student is assigned to it.');
        }
    }

    private function checkUnresolvedSections(array &$findings): void
    {
        $sections = db_connect()->table('sections')->where('program_id', null)->get()->getResultArray();

        foreach ($sections as $s) {
            $matches = db_connect()->table('programs')->where('program_name', $s['program'])->countAllResults();
            $isLegacyAlias = array_search($s['program'], \App\Models\DepartmentModel::legacyProgramNames(), true) !== false;

            if ($matches === 0 && ! $isLegacyAlias) {
                $this->add($findings, 'critical', 'section', 'section', (int) $s['section_id'],
                    "Section \"{$s['program']} · {$s['year_level']} · {$s['section']}\" ({$s['academic_year']}) has no matching Program at all.",
                    'Run php spark sections:backfill-program-id to see the full report, then fix the section\'s program text or reassign it manually.');
            }
        }
    }

    private function checkAmbiguousSections(array &$findings): void
    {
        $sections = db_connect()->table('sections')->where('program_id', null)->get()->getResultArray();

        foreach ($sections as $s) {
            $isLegacyAlias = array_search($s['program'], \App\Models\DepartmentModel::legacyProgramNames(), true) !== false;

            if ($isLegacyAlias) {
                continue;
            }

            $matches = db_connect()->table('programs')->where('program_name', $s['program'])->countAllResults();

            if ($matches > 1) {
                $this->add($findings, 'critical', 'section', 'section', (int) $s['section_id'],
                    "Section \"{$s['program']} · {$s['year_level']} · {$s['section']}\" ({$s['academic_year']}) matches {$matches} different Programs by name — cannot be resolved automatically.",
                    'Resolve the duplicate Program names first (see duplicate-code/name findings), then re-run the backfill command.');
            }
        }
    }

    private function checkInvalidChairpersonAssignment(array &$findings): void
    {
        $db = db_connect();

        $chairs = $db->table('users u')
            ->select('u.id, u.username, u.sub_department_id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'chairperson')
            ->where('u.deleted_at', null)
            ->get()->getResultArray();

        foreach ($chairs as $c) {
            if ($c['sub_department_id'] === null) {
                $this->add($findings, 'warning', 'chairperson', 'user', (int) $c['id'],
                    "Chairperson \"{$c['username']}\" has no Department assigned.",
                    'Assign them to a Department from the Department page.');
                continue;
            }

            $exists = $db->table('sub_departments')->where('sub_dept_id', $c['sub_department_id'])->countAllResults() > 0;

            if (! $exists) {
                $this->add($findings, 'critical', 'chairperson', 'user', (int) $c['id'],
                    "Chairperson \"{$c['username']}\" is assigned to a Department that no longer exists.",
                    'Reassign them to a valid Department immediately.');
            }
        }
    }

    private function checkStaleSchedulerDelegations(array &$findings): void
    {
        $db = db_connect();

        $stale = $db->table('sub_department_schedulers sds')
            ->select('sds.id, sds.user_id, sds.sub_dept_id')
            ->join('sub_departments sd', 'sd.sub_dept_id = sds.sub_dept_id', 'left')
            ->join('users u', 'u.id = sds.user_id', 'left')
            ->where('(sd.sub_dept_id IS NULL OR u.id IS NULL)', null, false)
            ->get()->getResultArray();

        foreach ($stale as $r) {
            $this->add($findings, 'critical', 'chairperson', 'sub_department_scheduler', (int) $r['id'],
                'A scheduler delegation references a missing Department or user.',
                'This should be prevented by a foreign key — remove the stale delegation row.');
        }
    }

    private function checkOrphanedSubjectOwnership(array &$findings): void
    {
        $count = db_connect()->table('subjects')->where('sub_department_id', null)->where('is_active', 1)->countAllResults();

        if ($count > 0) {
            $this->add($findings, 'informational', 'subjects', 'subjects', null,
                "{$count} active subject(s) have no owning Department.",
                'Not necessarily a problem — but an unowned subject cannot be scheduled by any Chairperson until it\'s assigned one.');
        }
    }

    private function checkDepartmentsWithoutChairperson(array &$findings): void
    {
        $db = db_connect();

        $depts = $db->table('sub_departments')->get()->getResultArray();
        $chaired = array_map('intval', array_column(
            $db->table('users u')->select('u.sub_department_id')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->where('g.group', 'chairperson')
                ->where('u.sub_department_id IS NOT NULL')
                ->get()->getResultArray(),
            'sub_department_id'
        ));
        $delegated = array_map('intval', array_column($db->table('sub_department_schedulers')->select('sub_dept_id')->get()->getResultArray(), 'sub_dept_id'));

        foreach ($depts as $d) {
            $id = (int) $d['sub_dept_id'];

            if (! in_array($id, $chaired, true) && ! in_array($id, $delegated, true)) {
                $this->add($findings, 'warning', 'chairperson', 'sub_department', $id,
                    "Department \"{$d['name']}\" has no Chairperson (primary or delegated).",
                    'Assign a Chairperson from the Department page — otherwise nobody can manage its subjects/offerings.');
            }
        }
    }

    private function checkInactiveProgramsWithHistoricalStudents(array &$findings): void
    {
        $inactive = db_connect()->table('programs')->where('is_active', 0)->get()->getResultArray();

        foreach ($inactive as $p) {
            $studentCount = db_connect()->table('students')->where('program_id', $p['program_id'])->countAllResults();

            if ($studentCount > 0) {
                $this->add($findings, 'informational', 'program', 'program', (int) $p['program_id'],
                    "Inactive Program \"{$p['program_name']}\" still has {$studentCount} student(s) on record.",
                    'Expected and fine — historical students remain readable. No action needed unless they should be transferred to an active Program.');
            }
        }
    }
}
