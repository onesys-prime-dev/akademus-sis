<?php

namespace App\Models;

use CodeIgniter\Model;

class FacultyChangeRequestModel extends Model
{
    protected $table      = 'faculty_change_requests';
    protected $primaryKey = 'request_id';

    protected $allowedFields = [
        'assignment_id',
        'faculty_id',
        'reason',
        'proposed_alternative',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $useTimestamps = true;

    private const OPEN_STATUSES = ['pending', 'under_review'];

    public function hasOpenRequest(int $assignmentId): bool
    {
        return $this->where('assignment_id', $assignmentId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->countAllResults() > 0;
    }

    public function forFaculty(int $userId): array
    {
        return $this->where('faculty_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }
}
