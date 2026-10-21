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

    /**
     * Every proof this student has ever submitted, newest first — carries
     * the same amount/installment info pendingQueue() does (see its own
     * docblock) so a caller never has to special-case "pending" to show
     * what's being asked for.
     */
    public function forStudent(int $studentId): array
    {
        return $this->db->table('payment_proofs pp')
            ->select('pp.*, ps.label AS schedule_label, ps.amount AS schedule_amount, ta.balance AS assessment_balance')
            ->join('payment_schedules ps', 'ps.schedule_id = pp.schedule_id', 'left')
            ->join('tuition_assessments ta', 'ta.assessment_id = pp.assessment_id', 'left')
            ->where('pp.student_id', $studentId)
            ->orderBy('pp.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Every pending proof, oldest first — the staff review queue.
     *
     * Carries the same "amount this proof will record" TuitionAssessmentService::
     * approveProof() itself computes (the picked installment's amount, or the
     * assessment's full remaining balance for a "General Payment" proof) so
     * the queue can show it up front instead of leaving staff to guess before
     * approving.
     */
    public function pendingQueue(): array
    {
        return $this->db->table('payment_proofs pp')
            ->select('pp.*, u.id AS user_id, u.first_name, u.last_name, u.photo_mime, s.student_number,
                      ps.label AS schedule_label, ps.amount AS schedule_amount, ta.balance AS assessment_balance')
            ->join('students s', 's.student_id = pp.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('payment_schedules ps', 'ps.schedule_id = pp.schedule_id', 'left')
            ->join('tuition_assessments ta', 'ta.assessment_id = pp.assessment_id', 'left')
            ->where('pp.status', 'pending')
            ->orderBy('pp.created_at')
            ->get()
            ->getResultArray();
    }
}
