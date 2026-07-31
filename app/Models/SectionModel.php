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
        'year_level',
        'section',
        'capacity',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * Every section whose program belongs to this sub-department, newest
     * academic year first.
     *
     * Resolved via DepartmentModel::programDept() rather than a SQL join on
     * programs.program_name — some programs are stored under a legacy
     * display alias (see DepartmentModel::LEGACY_PROGRAM_NAMES) that does
     * NOT equal programs.program_name, so a raw name join silently drops
     * those sections.
     */
    public function forSubDepartment(string $subDeptCode): array
    {
        $all = $this->orderBy('academic_year', 'DESC')
            ->orderBy('program')
            ->orderBy('year_level')
            ->orderBy('section')
            ->findAll();

        return array_values(array_filter(
            $all,
            static fn (array $s) => DepartmentModel::programDept($s['program']) === $subDeptCode
        ));
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
     * first time a chairperson plots a schedule for it.
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
            'year_level'    => $yearLevel,
            'section'       => $section,
        ]);

        return $this->find($this->getInsertID());
    }
}
