<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One-off Finance-initiated fee grants — see CreateStudentAdditionalFees
 * migration. TuitionAssessmentService::recompute() reads activeFor() to
 * decide which ledger_adjustments 'additional_fee' debit rows should exist
 * for a student's term, the same way it already does for student_discounts.
 */
class StudentAdditionalFeeModel extends Model
{
    protected $table      = 'student_additional_fees';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'student_id', 'academic_year', 'label', 'amount', 'status',
        'remarks', 'batch_key', 'granted_by', 'granted_at', 'revoked_by', 'revoked_at',
    ];

    protected $useTimestamps = false;

    public function grant(int $studentId, string $academicYear, string $label, float $amount, int $grantedBy, ?string $remarks = null, ?string $batchKey = null): array
    {
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Amount must be greater than zero.'];
        }

        if (trim($label) === '') {
            return ['ok' => false, 'error' => 'A label is required.'];
        }

        $this->insert([
            'student_id'    => $studentId,
            'academic_year' => $academicYear,
            'label'         => trim($label),
            'amount'        => round($amount, 2),
            'status'        => 'active',
            'remarks'       => $remarks,
            'batch_key'     => $batchKey,
            'granted_by'    => $grantedBy,
            'granted_at'    => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'id' => $this->getInsertID()];
    }

    public function revoke(int $id, int $revokedBy): array
    {
        $grant = $this->find($id);

        if ($grant === null) {
            return ['ok' => false, 'error' => 'Fee not found.'];
        }

        if ($grant['status'] === 'revoked') {
            return ['ok' => false, 'error' => 'This fee is already revoked.'];
        }

        $this->update($id, [
            'status'     => 'revoked',
            'revoked_by' => $revokedBy,
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true];
    }

    /** Active grants for this exact student/term. @return list<array> */
    public function activeFor(int $studentId, string $academicYear): array
    {
        return $this->where('student_id', $studentId)
            ->where('academic_year', $academicYear)
            ->where('status', 'active')
            ->findAll();
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('granted_at', 'DESC')->findAll();
    }

    /** Every row from one bulk-apply action, most useful for a confirmation/summary view. */
    public function forBatch(string $batchKey): array
    {
        return $this->where('batch_key', $batchKey)->findAll();
    }
}
