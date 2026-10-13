<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Discount/scholarship grants — see CreateStudentDiscounts migration.
 * TuitionAssessmentService::recompute() reads activeFor() to decide which
 * ledger_adjustments credit rows should exist for a student's term.
 */
class StudentDiscountModel extends Model
{
    protected $table      = 'student_discounts';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'student_id', 'discount_type_id', 'academic_year', 'override_value',
        'status', 'granted_by', 'granted_at', 'revoked_by', 'revoked_at', 'remarks',
    ];

    protected $useTimestamps = false;

    public function grant(int $studentId, int $discountTypeId, ?string $academicYear, ?float $overrideValue, int $grantedBy, ?string $remarks = null): array
    {
        $discountType = (new DiscountTypeModel())->find($discountTypeId);

        if ($discountType === null || ! $discountType['is_active']) {
            return ['ok' => false, 'error' => 'That discount type is not available.'];
        }

        $this->insert([
            'student_id'       => $studentId,
            'discount_type_id' => $discountTypeId,
            'academic_year'    => $academicYear,
            'override_value'   => $overrideValue,
            'status'           => 'active',
            'granted_by'       => $grantedBy,
            'granted_at'       => date('Y-m-d H:i:s'),
            'remarks'          => $remarks,
        ]);

        return ['ok' => true, 'id' => $this->getInsertID()];
    }

    public function revoke(int $id, int $revokedBy): array
    {
        $grant = $this->find($id);

        if ($grant === null) {
            return ['ok' => false, 'error' => 'Grant not found.'];
        }

        if ($grant['status'] === 'revoked') {
            return ['ok' => false, 'error' => 'This grant is already revoked.'];
        }

        $this->update($id, [
            'status'     => 'revoked',
            'revoked_by' => $revokedBy,
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true];
    }

    /**
     * Active grants applicable to a student's term — either scoped to that
     * exact academic_year, or scoped to every term (academic_year IS NULL).
     *
     * @return list<array>
     */
    public function activeFor(int $studentId, string $academicYear): array
    {
        return $this->db->table('student_discounts sd')
            ->select('sd.id, sd.override_value, sd.academic_year, sd.granted_by, dt.id AS discount_type_id, dt.name, dt.value_type, dt.default_value')
            ->join('discount_types dt', 'dt.id = sd.discount_type_id')
            ->where('sd.student_id', $studentId)
            ->where('sd.status', 'active')
            ->groupStart()
                ->where('sd.academic_year', $academicYear)
                ->orWhere('sd.academic_year', null)
            ->groupEnd()
            ->get()
            ->getResultArray();
    }

    public function forStudent(int $studentId): array
    {
        return $this->db->table('student_discounts sd')
            ->select('sd.*, dt.name, dt.value_type, dt.default_value')
            ->join('discount_types dt', 'dt.id = sd.discount_type_id')
            ->where('sd.student_id', $studentId)
            ->orderBy('sd.granted_at', 'DESC')
            ->get()
            ->getResultArray();
    }
}
