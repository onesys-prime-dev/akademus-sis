<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentProofModel extends Model
{
    protected $table      = 'payment_proofs';
    protected $primaryKey = 'proof_id';

    protected $allowedFields = [
        'student_id',
        'assessment_id',
        'schedule_id',
        'stored_filename',
        'original_filename',
        'remarks',
        'status',
        'cashier_remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public const STATUSES = ['pending', 'approved', 'rejected'];

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('created_at', 'DESC')->findAll();
    }

    /** Every pending proof, oldest first — the staff review queue. */
    public function pendingQueue(): array
    {
        return $this->db->table('payment_proofs pp')
            ->select('pp.*, u.first_name, u.last_name, s.student_number')
            ->join('students s', 's.student_id = pp.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('pp.status', 'pending')
            ->orderBy('pp.created_at')
            ->get()
            ->getResultArray();
    }
}
