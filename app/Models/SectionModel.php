<?php

namespace App\Models;

use CodeIgniter\Model;

class SectionModel extends Model
{
    protected $table      = 'sections';
    protected $primaryKey = 'section_id';

    protected $allowedFields = [
        'academic_year',
        'program',
        'program_id',
        'year_level',
        'section',
        'capacity',
        'status',
        'origin',
        'auto_balance',
        'min_capacity',
    ];

    protected $useTimestamps = true;

    /**
     * Every section whose program belongs to this sub-department, newest
     * academic year first.
     *
     * Prefers the real FK (sections.program_id -> programs.sub_department_id)
     * — a single indexed query instead of fetching every section and calling
     * DepartmentModel::programDept() per row (that was a genuine N+1: one
     * query per section just to resolve its department). Sections not yet
     * backfilled (program_id IS NULL, pre-dating the FK hardening migration)
     * still fall back to the old text-matching path so nothing silently
     * disappears from a department's list during the transition — see
     * Database\Migrations\...AddProgramIdToSections and the backfill
     * command for closing that gap entirely.
     */
    public function forSubDepartment(string $subDeptCode): array
    {
        $subDept = db_connect()->table('sub_departments')->where('code', $subDeptCode)->get()->getRowArray();

        if ($subDept === null) {
            return [];
        }

        $subDeptId = (int) $subDept['sub_dept_id'];
        $programIds = (new DepartmentModel())->programIdsOfSubDept($subDeptId);

        $resolved = $programIds === [] ? [] : $this->whereIn('program_id', $programIds)->findAll();

        // Legacy fallback: sections still unresolved (program_id NULL)
        // that the old text-matching rule would have caught. Checked only
        // against the leftover NULL rows, not the whole table, so this
        // stays cheap once backfill has run.
        $legacyCandidates = $this->where('program_id', null)->findAll();
        $legacyMatches = array_values(array_filter(
            $legacyCandidates,
            static fn (array $s) => DepartmentModel::programDept($s['program']) === $subDeptCode
        ));

        $all = array_merge($resolved, $legacyMatches);

        usort($all, static function (array $a, array $b): int {
            $byYear = $b['academic_year'] <=> $a['academic_year']; // DESC
            if ($byYear !== 0) {
                return $byYear;
            }

            return [$a['program'], $a['year_level'], $a['section']] <=> [$b['program'], $b['year_level'], $b['section']];
        });

        return $all;
    }

    public function forContext(string $academicYear, string $program, string $yearLevel, string $section): ?array
    {
        return $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
        ])->first();
    }

    /**
     * The section row for this context, creating it (unlimited capacity)
     * if it doesn't exist yet. This is how a section becomes a real row the
     * first time a chairperson plots a schedule for it. program_id is
     * resolved and stored alongside the legacy text field automatically —
     * callers never need to resolve it themselves (see
     * DepartmentModel::programIdOfName()).
     */
    public function firstOrCreateForContext(string $academicYear, string $program, string $yearLevel, string $section): array
    {
        $existing = $this->forContext($academicYear, $program, $yearLevel, $section);

        if ($existing !== null) {
            return $existing;
        }

        $this->insert([
            'academic_year' => $academicYear,
            'program'       => $program,
            'program_id'    => DepartmentModel::programIdOfName($program),
            'year_level'    => $yearLevel,
            'section'       => $section,
        ]);

        return $this->find($this->getInsertID());
    }
}
