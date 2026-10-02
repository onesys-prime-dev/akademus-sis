<?php

namespace App\Models;

use CodeIgniter\Model;

class FacultyAssignmentModel extends Model
{
    protected $table      = 'faculty_assignments';
    protected $primaryKey = 'assignment_id';

    protected $allowedFields = [
        'user_id',
        'offering_id',
        'offering_component_id',
        'academic_year',
        'program',
        'year_level',
        'section',
        'subject_code',
        'method',
        'status',
    ];

    protected $useTimestamps = true;

    /**
     * Assign (or reassign) a faculty member to a subject within one context,
     * keyed on the table's own unique (academic_year, program, year_level,
     * section, subject_code) index. Used by ChairpersonController::sync()
     * now that assignment is chairperson-directed rather than a faculty
     * first-come-first-served claim.
     */
    /**
     * @return bool True when the faculty on this assignment actually
     *              changed (new assignment or reassignment) — the caller's
     *              cue to notify the newly-assigned faculty member.
     */
    public function upsertAssignment(string $academicYear, string $program, string $yearLevel, string $section, string $subjectCode, int $userId): bool
    {
        $existing = $this->where([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
            'subject_code'  => $subjectCode,
        ])->first();

        if ($existing === null) {
            $this->insert([
                'user_id'       => $userId,
                'academic_year' => $academicYear,
                'program'       => $program,
                'year_level'    => $yearLevel,
                'section'       => $section,
                'subject_code'  => $subjectCode,
                'method'        => 'assigned',
                'status'        => 'assigned',
            ]);

            return true;
        }

        if ((int) $existing['user_id'] !== $userId) {
            $this->update($existing['assignment_id'], [
                'user_id' => $userId,
                'method'  => 'assigned',
            ]);

            return true;
        }

        return false;
    }

    /**
     * Step 9: assigns (or reassigns) a faculty member to an offering, or to
     * one specific offering_component of it. No DB-level uniqueness backs
     * this (verified empirically: MySQL allows duplicate rows when another
     * column of a composite unique index is NULL) — duplicate prevention is
     * this explicit check-then-insert/update, matching the approved design.
     *
     * @return bool True when the faculty on this assignment actually
     *              changed (new assignment or reassignment).
     */
    public function upsertForOffering(int $offeringId, ?int $offeringComponentId, string $academicYear, string $subjectCode, int $userId): bool
    {
        $query = $this->where('offering_id', $offeringId)->where('subject_code', $subjectCode);
        $query = $offeringComponentId === null ? $query->where('offering_component_id IS NULL') : $query->where('offering_component_id', $offeringComponentId);

        $existing = $query->first();

        if ($existing === null) {
            $this->insert([
                'user_id'                => $userId,
                'offering_id'            => $offeringId,
                'offering_component_id'  => $offeringComponentId,
                'academic_year'          => $academicYear,
                'program'                => null,
                'year_level'             => null,
                'section'                => null,
                'subject_code'           => $subjectCode,
                'method'                 => 'assigned',
                'status'                 => 'assigned',
            ]);

            return true;
        }

        if ((int) $existing['user_id'] !== $userId) {
            $this->update($existing['assignment_id'], ['user_id' => $userId, 'method' => 'assigned']);

            return true;
        }

        return false;
    }

    /**
     * Step 9 offering-based load only (legacy per-section-tuple assignments
     * are untouched and keep using TeachingLoads' own aggregation). Charges
     * once per DISTINCT (offering_id, subject_code) this faculty member
     * holds — the approved rule: the same faculty teaching both Lecture and
     * Laboratory of ONE offering counts that offering's subject once, while
     * two separate offerings each count in full, and different faculty on
     * different components of the same offering each receive the full
     * parent-subject units under the current system.
     *
     * Component-specific faculty load distribution is not modeled — there
     * is no lecture/lab unit-weighting invented here.
     */
    public function currentOfferingLoadUnits(int $userId): float
    {
        $rows = $this->db->table('faculty_assignments fa')
            ->distinct()
            ->select('fa.offering_id, fa.subject_code, s.units')
            ->join('subjects s', 's.subject_code = fa.subject_code', 'left')
            ->where('fa.user_id', $userId)
            ->where('fa.offering_id IS NOT NULL')
            ->get()
            ->getResultArray();

        $units = 0.0;

        foreach ($rows as $row) {
            $units += $row['units'] !== null ? (float) $row['units'] : 3.0;
        }

        return $units;
    }
}
