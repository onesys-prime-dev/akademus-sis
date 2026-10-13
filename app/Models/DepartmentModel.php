<?php

namespace App\Models;

use CodeIgniter\Model;

class DepartmentModel extends Model
{
    protected $table      = 'departments';
    protected $primaryKey = 'dept_id';

    protected $allowedFields = ['code', 'name', 'location', 'established_year'];

    protected $useTimestamps = true;

    /**
     * A few programs were seeded (plotted_schedules/faculty_assignments/etc.)
     * with display names that predate the real `programs` table and don't
     * match `programs.program_name` verbatim. New programs need no entry
     * here — they resolve straight off program_name.
     */
    private const LEGACY_PROGRAM_NAMES = [
        'BSIT' => 'BS in Information Technology',
        'BSCS' => 'BS in Computer Science',
    ];

    /**
     * @return array<string, string> program_code => legacy display name
     */
    public static function legacyProgramNames(): array
    {
        return self::LEGACY_PROGRAM_NAMES;
    }

    /**
     * Sub-department (the chairperson-headed "Department" in the UI) that a
     * user belongs to, or null when they have none.
     */
    public function subDeptOfUser(int $userId): ?array
    {
        return db_connect()->table('users u')
            ->select('sd.sub_dept_id, sd.code, sd.name')
            ->join('sub_departments sd', 'sd.sub_dept_id = u.sub_department_id')
            ->where('u.id', $userId)
            ->get()
            ->getRowArray();
    }

    /**
     * College (the dean-headed "Department" row in this table) that a user
     * primary-deans, or null when they have none. Dean's counterpart to
     * subDeptOfUser() — must additionally check the 'dean' Shield group,
     * since users.department_id is also kept in sync for chairpersons (to
     * scope their college-wide scheduling), so the column alone can't tell
     * a dean apart from a chairperson of a department in that same college.
     */
    public function collegeOfUser(int $userId): ?array
    {
        return db_connect()->table('users u')
            ->select('d.dept_id, d.code, d.name')
            ->join('departments d', 'd.dept_id = u.department_id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('u.id', $userId)
            ->where('g.group', 'dean')
            ->get()
            ->getRowArray();
    }

    /**
     * Department code of a user, or null when they have none.
     */
    public function codeOfUser(int $userId): ?string
    {
        return $this->subDeptOfUser($userId)['code'] ?? null;
    }

    /**
     * Active programs belonging to a sub-department, as the display-name
     * values used throughout scheduling (plotted_schedules, faculty_assignments, ...).
     */
    public function programsOfSubDept(int $subDeptId): array
    {
        $rows = db_connect()->table('programs')
            ->where('sub_department_id', $subDeptId)
            ->where('is_active', 1)
            ->orderBy('program_name')
            ->get()
            ->getResultArray();

        return array_map(
            static fn (array $r) => self::LEGACY_PROGRAM_NAMES[$r['program_code']] ?? $r['program_name'],
            $rows
        );
    }

    /**
     * Active program_ids belonging to a sub-department — the ID-based
     * counterpart to programsOfSubDept(), used wherever a section's
     * department needs resolving through sections.program_id rather than
     * free-text name matching (SchedulingAuthorization::matchSection(),
     * SharedSubjectCoverageService, SectionModel::forSubDepartment()).
     *
     * @return list<int>
     */
    public function programIdsOfSubDept(int $subDeptId): array
    {
        return array_map('intval', array_column(
            db_connect()->table('programs')->select('program_id')->where('sub_department_id', $subDeptId)->where('is_active', 1)->get()->getResultArray(),
            'program_id'
        ));
    }

    /**
     * Sub-department code that owns the program with this display name.
     */
    public static function programDept(string $program): ?string
    {
        $row = self::programRow($program);

        if ($row === null) {
            return null;
        }

        return db_connect()->table('sub_departments')
            ->where('sub_dept_id', $row['sub_department_id'])
            ->get()
            ->getRowArray()['code'] ?? null;
    }

    /**
     * programs.program_id for the program with this display name, or null.
     */
    public static function programIdOfName(string $program): ?int
    {
        $row = self::programRow($program);

        return $row === null ? null : (int) $row['program_id'];
    }

    /**
     * The display-name value (matching programsOfSubDept()) for a program_id.
     */
    public static function programNameById(int $programId): ?string
    {
        $row = db_connect()->table('programs')->where('program_id', $programId)->get()->getRowArray();

        if ($row === null) {
            return null;
        }

        return self::LEGACY_PROGRAM_NAMES[$row['program_code']] ?? $row['program_name'];
    }

    private static function programRow(string $program): ?array
    {
        $code = array_search($program, self::LEGACY_PROGRAM_NAMES, true);

        $builder = db_connect()->table('programs');

        $row = $code !== false
            ? $builder->where('program_code', $code)->get()->getRowArray()
            : $builder->where('program_name', $program)->get()->getRowArray();

        return $row;
    }

    /**
     * What a Department transfer (to a different College) would touch —
     * used by Admin\SubDepartmentController::transferPreview() so the admin
     * sees real counts before confirming. Nothing here is written; this is
     * read-only. Programs/faculty/subjects/chairpersons+delegates all key
     * off sub_dept_id directly, so a college change never touches them —
     * they're reported purely for visibility, not because they'd change.
     *
     * @return array{programs: int, faculty: int, subjects: int, chairpersons: int, students: int, sections: int}
     */
    public function departmentTransferImpact(int $subDeptId): array
    {
        $db = db_connect();

        $programIds = array_column(
            $db->table('programs')->select('program_id')->where('sub_department_id', $subDeptId)->get()->getResultArray(),
            'program_id'
        );

        $students = $programIds === [] ? 0 : $db->table('students')->whereIn('program_id', $programIds)->countAllResults();

        $programNames = $this->programsOfSubDept($subDeptId);
        $sections = $programNames === [] ? 0 : $db->table('sections')->whereIn('program', $programNames)->countAllResults();

        $chairpersonIds = array_column(
            $db->table('users u')->select('u.id')
                ->join('auth_groups_users g', 'g.user_id = u.id')
                ->where('g.group', 'chairperson')
                ->where('u.sub_department_id', $subDeptId)
                ->get()->getResultArray(),
            'id'
        );
        $delegateIds = array_column(
            $db->table('sub_department_schedulers')->select('user_id')->where('sub_dept_id', $subDeptId)->get()->getResultArray(),
            'user_id'
        );

        return [
            'programs'     => count($programIds),
            'faculty'      => $db->table('faculty_departments')->where('sub_dept_id', $subDeptId)->where('status', 'active')->countAllResults(),
            'subjects'     => $db->table('subjects')->where('sub_department_id', $subDeptId)->countAllResults(),
            'chairpersons' => count(array_unique(array_map('intval', array_merge($chairpersonIds, $delegateIds)))),
            'students'     => $students,
            'sections'     => $sections,
        ];
    }

    /**
     * What a Program transfer (to a different Department) would touch — see
     * ProgramController::transferPreview(). Curriculum/subject ownership are
     * deliberately NOT in this list — they're unaffected (curriculum stays
     * keyed to the same program_id; subjects keep whichever department
     * already owns them, independent of which department the program
     * itself belongs to) and the preview UI says so explicitly rather than
     * implying they move too.
     *
     * @return array{curricula: int, students: int, sections: int, enrollments: int}
     */
    public function programTransferImpact(int $programId): array
    {
        $db = db_connect();

        $students = $db->table('students')->where('program_id', $programId)->countAllResults();
        $curricula = $db->table('curriculum')->where('program_id', $programId)->countAllResults();

        $programName = self::programNameById($programId);
        $sections = 0;
        $enrollments = 0;

        if ($programName !== null) {
            $sections = $db->table('sections')->where('program', $programName)->countAllResults();

            $enrollments = $db->table('enrollments e')
                ->join('sections s', 's.section_id = e.section_id')
                ->where('s.program', $programName)
                ->countAllResults();
        }

        return [
            'curricula'   => $curricula,
            'students'    => $students,
            'sections'    => $sections,
            'enrollments' => $enrollments,
        ];
    }
}
