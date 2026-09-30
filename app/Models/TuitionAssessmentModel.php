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
        'tuition_amount',
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
}
