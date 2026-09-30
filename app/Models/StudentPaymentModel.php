<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentPaymentModel extends Model
{
    protected $table      = 'student_payments';
    protected $primaryKey = 'payment_id';

    protected $allowedFields = [
        'assessment_id',
        'student_id',
        'amount',
        'payment_date',
        'method',
        'reference_no',
        'recorded_by',
    ];

    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = '';

    public function historyFor(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('payment_date', 'DESC')->findAll();
    }

    public function forAssessment(int $assessmentId): array
    {
        return $this->where('assessment_id', $assessmentId)->orderBy('payment_date', 'DESC')->findAll();
    }
}
