<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The formal debit/credit memo system — see CreateLedgerAdjustments
 * migration. Append-only like AuditLogModel/StudentPaymentModel: record()
 * is the only writer, update()/delete() always throw. A reversal is a new
 * opposite-signed row via reverse(), never an edit to the original.
 */
class LedgerAdjustmentModel extends Model
{
    protected $table      = 'ledger_adjustments';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'assessment_id',
        'student_id',
        'type',
        'category',
        'amount',
        'reason',
        'discount_grant_id',
        'fee_grant_id',
        'reverses_adjustment_id',
        'created_by',
        'created_at',
    ];

    protected $useTimestamps = false;

    public const TYPES      = ['debit', 'credit'];
    public const CATEGORIES = ['discount', 'waiver', 'correction', 'penalty', 'other', 'additional_fee'];

    /**
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function record(int $assessmentId, int $studentId, string $type, string $category, float $amount, ?string $reason, int $createdBy, ?int $discountGrantId = null, ?int $feeGrantId = null): array
    {
        if (! in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'error' => 'Invalid adjustment type.'];
        }

        if (! in_array($category, self::CATEGORIES, true)) {
            return ['ok' => false, 'error' => 'Invalid adjustment category.'];
        }

        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'Adjustment amount must be greater than zero.'];
        }

        $this->insert([
            'assessment_id'      => $assessmentId,
            'student_id'         => $studentId,
            'type'               => $type,
            'category'           => $category,
            'amount'             => round($amount, 2),
            'reason'             => $reason,
            'discount_grant_id'  => $discountGrantId,
            'fee_grant_id'       => $feeGrantId,
            'created_by'         => $createdBy,
            'created_at'         => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'id' => $this->getInsertID()];
    }

    /**
     * Reverses a prior adjustment with a new, opposite-signed row — never an
     * edit to the original. The original stays on record exactly as it was.
     */
    public function reverse(int $adjustmentId, string $reason, int $actorId): array
    {
        $original = $this->find($adjustmentId);

        if ($original === null) {
            return ['ok' => false, 'error' => 'Adjustment not found.'];
        }

        $oppositeType = $original['type'] === 'debit' ? 'credit' : 'debit';

        $this->insert([
            'assessment_id'          => $original['assessment_id'],
            'student_id'             => $original['student_id'],
            'type'                   => $oppositeType,
            'category'               => 'correction',
            'amount'                 => $original['amount'],
            'reason'                 => $reason,
            'reverses_adjustment_id' => $adjustmentId,
            'created_by'             => $actorId,
            'created_at'             => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'id' => $this->getInsertID()];
    }

    public function forAssessment(int $assessmentId): array
    {
        return $this->where('assessment_id', $assessmentId)->orderBy('created_at', 'ASC')->findAll();
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('created_at', 'DESC')->findAll();
    }

    /**
     * The still-active 'discount' adjustment already applied for a given
     * grant on a given assessment (term), or null if none exists yet —
     * the idempotency check TuitionAssessmentService::recompute() uses so
     * re-running recompute never double-applies the same discount grant.
     * Scoped per-assessment (not just per-grant) because an "every term"
     * grant (student_discounts.academic_year IS NULL) must still produce
     * one separate adjustment row per term/assessment it applies to.
     */
    public function activeForGrant(int $discountGrantId, int $assessmentId): ?array
    {
        return $this->where('discount_grant_id', $discountGrantId)
            ->where('assessment_id', $assessmentId)
            ->where('reverses_adjustment_id', null)
            ->first();
    }

    /** Same idempotency check as activeForGrant(), for a student_additional_fees grant instead. */
    public function activeForFeeGrant(int $feeGrantId, int $assessmentId): ?array
    {
        return $this->where('fee_grant_id', $feeGrantId)
            ->where('assessment_id', $assessmentId)
            ->where('reverses_adjustment_id', null)
            ->first();
    }

    /**
     * Every still-active row for a discount grant, across EVERY assessment
     * it was ever applied to — unlike activeForGrant(), not scoped to one
     * term. An "every term" grant (student_discounts.academic_year IS
     * NULL) can have one adjustment row per term/assessment it's touched
     * (see activeForGrant()'s own docblock), so revoking that grant has to
     * reverse all of them, not just the current term's, or it would keep
     * silently discounting whichever other assessment(s) it already hit.
     *
     * @return list<array>
     */
    public function allActiveForGrant(int $discountGrantId): array
    {
        return $this->where('discount_grant_id', $discountGrantId)
            ->where('reverses_adjustment_id', null)
            ->findAll();
    }

    public function update($id = null, $row = null): bool
    {
        throw new \LogicException('ledger_adjustments is append-only.');
    }

    public function delete($id = null, bool $purge = false)
    {
        throw new \LogicException('ledger_adjustments is append-only.');
    }
}
