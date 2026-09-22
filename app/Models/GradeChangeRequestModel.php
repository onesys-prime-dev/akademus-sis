<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Mirrors FacultyChangeRequestModel (faculty_change_requests) exactly: a
 * one-directional request, reviewed once, fully audited. Here the "official
 * assignment" being corrected is a locked grade rather than a teaching load.
 */
class GradeChangeRequestModel extends Model
{
    protected $table      = 'grade_change_requests';
    protected $primaryKey = 'request_id';

    protected $allowedFields = [
        'grade_id',
        'requested_by',
        'current_value',
        'requested_value',
        'reason',
        'status',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
    ];

    protected $useTimestamps = true;

    public function hasOpenRequest(int $gradeId): bool
    {
        return $this->where('grade_id', $gradeId)->where('status', 'pending')->countAllResults() > 0;
    }

    public function forFaculty(int $userId): array
    {
        return $this->where('requested_by', $userId)->orderBy('created_at', 'DESC')->findAll();
    }

    /**
     * Pending requests against a grade belonging to any of these programs —
     * the chairperson's own-department scoping, same idea as
     * SectionModel::forSubDepartment().
     */
    public function pendingForPrograms(array $programs): array
    {
        if ($programs === []) {
            return [];
        }

        return $this->db->table('grade_change_requests r')
            ->select('r.request_id, r.grade_id, r.current_value, r.requested_value, r.reason, r.created_at,
                      g.subject_code, g.academic_year, s.program, s.year_level, s.section,
                      su.username AS student_username, fu.username AS requested_by_username')
            ->join('grades g', 'g.grade_id = r.grade_id')
            ->join('sections s', 's.section_id = g.section_id')
            ->join('users su', 'su.id = g.user_id')
            ->join('users fu', 'fu.id = r.requested_by')
            ->where('r.status', 'pending')
            ->whereIn('s.program', $programs)
            ->orderBy('r.created_at', 'ASC')
            ->get()
            ->getResultArray();
    }
}
