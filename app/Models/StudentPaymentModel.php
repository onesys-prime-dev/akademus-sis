<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Append-only ledger: rows can be inserted but never updated or deleted
 * through the application (docblock on the CreateStudentPayments migration
 * already said so — this is that promise actually enforced, the same way
 * AuditLogModel enforces its own append-only contract). A correction is a
 * new ledger_adjustments row, never an edit here.
 */
class StudentPaymentModel extends Model
{
    protected $table      = 'student_payments';
    protected $primaryKey = 'payment_id';

    protected $allowedFields = [
        'assessment_id',
        'schedule_id',
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

    public const METHODS = ['cash', 'check', 'bank_transfer', 'gcash', 'other'];

    /**
     * Payment history joined to its Official Receipt (left join — a
     * payment recorded before Phase 1 shipped has none), for display
     * contexts that need to link to the receipt download.
     */
    public function historyFor(int $studentId): array
    {
        return $this->db->table('student_payments sp')
            ->select('sp.*, r.id AS receipt_id, r.or_number, r.status AS receipt_status, ps.label AS schedule_label')
            ->join('official_receipts r', 'r.payment_id = sp.payment_id', 'left')
            ->join('payment_schedules ps', 'ps.schedule_id = sp.schedule_id', 'left')
            ->where('sp.student_id', $studentId)
            ->orderBy('sp.payment_date', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function forAssessment(int $assessmentId): array
    {
        return $this->where('assessment_id', $assessmentId)->orderBy('payment_date', 'DESC')->findAll();
    }

    /**
     * Most recent payments across every student, for a dashboard activity
     * feed — same receipt join as historyFor(), plus the student's own
     * name/number since this isn't scoped to one student.
     */
    public function recentAcrossStudents(int $limit): array
    {
        return $this->db->table('student_payments sp')
            ->select('sp.payment_id, sp.amount, sp.payment_date, sp.method, sp.student_id,
                      r.id AS receipt_id, r.or_number, s.student_number, u.first_name, u.last_name')
            ->join('official_receipts r', 'r.payment_id = sp.payment_id', 'left')
            ->join('students s', 's.student_id = sp.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->orderBy('sp.payment_date', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function update($id = null, $row = null): bool
    {
        throw new \LogicException('student_payments is append-only.');
    }

    public function delete($id = null, bool $purge = false)
    {
        throw new \LogicException('student_payments is append-only.');
    }
}
