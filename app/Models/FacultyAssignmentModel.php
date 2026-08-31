<?php

namespace App\Models;

use CodeIgniter\Model;

class FacultyAssignmentModel extends Model
{
    protected $table      = 'faculty_assignments';
    protected $primaryKey = 'assignment_id';

    protected $allowedFields = [
        'user_id',
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
}
