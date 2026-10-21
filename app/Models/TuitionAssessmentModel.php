<?php

namespace App\Models;

use CodeIgniter\Model;

class TuitionAssessmentModel extends Model
{
    protected $table      = 'tuition_assessments';
    protected $primaryKey = 'assessment_id';

    protected $allowedFields = [
        'student_id',
        'academic_year',
        'total_units',
        'is_estimated',
        'tuition_amount',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'balance',
        'status',
    ];

    protected $useTimestamps = true;

    public const STATUSES = ['unassessed', 'unpaid', 'partial', 'paid'];

    public function forStudentYear(int $studentId, string $academicYear): ?array
    {
        return $this->where('student_id', $studentId)->where('academic_year', $academicYear)->first();
    }

    /**
     * Every assessment on record for a student, most recent academic year first.
     */
    public function historyFor(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('academic_year', 'DESC')->findAll();
    }

    /**
     * Row-locks the assessment for the duration of the enclosing transaction so
     * concurrent payment postings against it serialize instead of racing on the
     * amount_paid/balance recalculation. FOR UPDATE is only valid under MySQL
     * (production); it's a no-op elsewhere (the SQLite test connection is
     * single-process anyway).
     */
    public function lockForUpdate(int $assessmentId): ?array
    {
        $db     = $this->db;
        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';

        return $db->query("SELECT * FROM tuition_assessments WHERE assessment_id = ?{$suffix}", [$assessmentId])->getRowArray();
    }
}
