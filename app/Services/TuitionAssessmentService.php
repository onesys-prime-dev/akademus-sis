<?php

namespace App\Services;

use App\Models\AssessmentItemModel;
use App\Models\AuditLogModel;
use App\Models\LedgerAdjustmentModel;
use App\Models\NotificationModel;
use App\Models\OfficialReceiptModel;
use App\Models\PaymentProofModel;
use App\Models\PaymentScheduleModel;
use App\Models\ProgramFeeRateModel;
use App\Models\StudentDiscountModel;
use App\Models\StudentModel;
use App\Models\StudentPaymentModel;
use App\Models\TuitionAssessmentModel;
use App\Models\TuitionRateModel;

/**
 * Computes and stores a student's tuition assessment for one academic year,
 * from their currently-enrolled subject units, the program's tuition_rates
 * row (the per-unit tuition line), and every priced program_fee_rates entry
 * (the itemized Miscellaneous/Other/Additional/Course lines). Called after
 * every enrollment change (see EnrollmentModel::enrollOne/drop) so the
 * assessment never drifts out of sync with the roster, and again whenever a
 * payment is recorded so the balance/status stay correct.
 */
class TuitionAssessmentService
{
    /**
     * Recompute (or create) the student's assessment for one academic year.
     * Leaves amounts at zero with status 'unassessed' when the student's
     * program has no tuition_rates row for that year yet — an administrative
     * gap to fill in, not an error (mirrors how promoteFromApplicant handles
     * a program with no published curriculum).
     */
    public function recompute(int $studentId, string $academicYear): array
    {
        $students = new StudentModel();
        $student  = $students->find($studentId);

        if ($student === null) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        $db = db_connect();

        $totalUnits = (float) ($db->table('enrollments e')
            ->select('COALESCE(SUM(s.units), 0) AS total')
            ->join('sections sec', 'sec.section_id = e.section_id')
            ->join('subjects s', 's.subject_code = e.subject_code')
            ->where('e.user_id', $student['user_id'])
            ->where('e.status', 'enrolled')
            ->where('sec.academic_year', $academicYear)
            ->get()
            ->getRowArray()['total'] ?? 0);

        $rate = $student['program_id'] !== null
            ? (new TuitionRateModel())->forProgramYear((int) $student['program_id'], $academicYear)
            : null;

        $feeItems = ($rate !== null && $student['program_id'] !== null)
            ? (new ProgramFeeRateModel())->forProgramYear((int) $student['program_id'], $academicYear)
            : [];

        $assessments = new TuitionAssessmentModel();
        $existing    = $assessments->forStudentYear($studentId, $academicYear);
        $amountPaid  = (float) ($existing['amount_paid'] ?? 0);

        if ($rate === null) {
            $data = [
                'student_id'      => $studentId,
                'academic_year'   => $academicYear,
                'total_units'     => $totalUnits,
                'tuition_amount'  => 0,
                'discount_amount' => 0,
                'total_amount'    => 0,
                'amount_paid'     => $amountPaid,
                'balance'         => 0,
                'status'          => 'unassessed',
            ];
            $feeItems = [];
        } else {
            $tuitionAmount  = $totalUnits * (float) $rate['per_unit_rate'];
            $addedFeesTotal = array_sum(array_column($feeItems, 'amount'));
            $grossAmount    = $tuitionAmount + $addedFeesTotal;

            $data = [
                'student_id'      => $studentId,
                'academic_year'   => $academicYear,
                'total_units'     => $totalUnits,
                'tuition_amount'  => $tuitionAmount,
                'discount_amount' => 0, // filled in below, once $assessmentId exists
                'total_amount'    => $grossAmount,
                'amount_paid'     => $amountPaid,
                'balance'         => max(0, $grossAmount - $amountPaid),
                'status'          => $this->statusFor($grossAmount, $amountPaid),
            ];
        }

        $isNew = $existing === null;

        if (! $isNew) {
            $assessments->update((int) $existing['assessment_id'], $data);
            $assessmentId = (int) $existing['assessment_id'];
        } else {
            $assessments->insert($data);
            $assessmentId = $assessments->getInsertID();
        }

        (new AssessmentItemModel())->replaceFor($assessmentId, $feeItems);

        // Discounts need assessmentId to exist first (ledger_adjustments
        // rows are keyed to it) — ensureDiscountAdjustments() creates any
        // missing ones idempotently, then total_amount/balance/status are
        // recomputed net of every discount on record for this assessment.
        if ($rate !== null) {
            $this->ensureDiscountAdjustments($assessmentId, $studentId, $academicYear, $data['total_amount']);

            $discountAmount = $this->sumActiveDiscounts($assessmentId);
            $netAmount      = max(0, $data['total_amount'] - $discountAmount);
            $balance        = max(0, $netAmount - $amountPaid);

            $assessments->update($assessmentId, [
                'discount_amount' => $discountAmount,
                'total_amount'    => $netAmount,
                'balance'         => $balance,
                'status'          => $this->statusFor($netAmount, $amountPaid),
            ]);
        }

        $finalAssessment = $assessments->find($assessmentId);

        // Only ever generated once — a later rate correction must not
        // reshuffle a payment plan the student may already be paying
        // against (see payment_schedules migration docblock).
        if ($isNew && $rate !== null) {
            $this->generateSchedule($assessmentId, (float) $finalAssessment['total_amount'], max(1, (int) $rate['installment_count']));
        }

        return ['ok' => true, 'assessment' => $finalAssessment];
    }

    /**
     * Ensures one 'discount' ledger_adjustments credit row exists per
     * active student_discounts grant applicable to this term, computed
     * against the gross (pre-discount) amount. Idempotent via
     * LedgerAdjustmentModel::activeForGrant() — safe to call on every
     * recompute.
     */
    private function ensureDiscountAdjustments(int $assessmentId, int $studentId, string $academicYear, float $grossAmount): void
    {
        $grants  = (new StudentDiscountModel())->activeFor($studentId, $academicYear);
        $ledger  = new LedgerAdjustmentModel();

        foreach ($grants as $grant) {
            if ($ledger->activeForGrant((int) $grant['id'], $assessmentId) !== null) {
                continue;
            }

            $value = $grant['override_value'] !== null ? (float) $grant['override_value'] : (float) $grant['default_value'];
            $amount = $grant['value_type'] === 'percentage' ? round($grossAmount * $value / 100, 2) : round($value, 2);

            if ($amount <= 0) {
                continue;
            }

            $grantedBy = $grant['granted_by'] !== null ? (int) $grant['granted_by'] : $studentId;
            $ledger->record($assessmentId, $studentId, 'credit', 'discount', $amount, $grant['name'], $grantedBy, (int) $grant['id']);
        }
    }

    /**
     * Sum of every still-active (never-reversed) 'discount'-category
     * credit adjustment for this assessment — what total_amount is
     * actually net of. LedgerAdjustmentModel::reverse() records its
     * reversal with category 'correction' (not 'discount'), so a reversed
     * discount must be excluded explicitly here by checking whether any
     * other row points back at it via reverses_adjustment_id — summing by
     * category alone would still count an original discount row that has
     * since been reversed.
     */
    private function sumActiveDiscounts(int $assessmentId): float
    {
        $ledger = new LedgerAdjustmentModel();
        $rows   = $ledger->where('assessment_id', $assessmentId)->where('category', 'discount')->findAll();

        if ($rows === []) {
            return 0.0;
        }

        $reversedIds = array_column(
            $ledger->whereIn('reverses_adjustment_id', array_column($rows, 'id'))->findAll(),
            'reverses_adjustment_id'
        );

        $total = 0.0;

        foreach ($rows as $row) {
            if (in_array($row['id'], $reversedIds, true)) {
                continue;
            }

            $total += (float) $row['amount'];
        }

        return round($total, 2);
    }

    /**
     * Builds (or rebuilds) an assessment's installment schedule. Safe to call
     * more than once — any existing rows for this assessment are deleted
     * first. That's safe because payments are never pinned to a specific
     * row: student_payments.schedule_id / payment_proofs.schedule_id are
     * both nullable with ON DELETE SET NULL, and withBalances() applies the
     * assessment's single amount_paid figure as a FIFO waterfall against
     * whatever rows currently exist — so replacing the row shape never
     * loses or misattributes money already paid.
     */
    public function generateSchedule(int $assessmentId, float $totalAmount, int $installmentCount): void
    {
        $schedules = new PaymentScheduleModel();
        $schedules->where('assessment_id', $assessmentId)->delete();

        if ($installmentCount <= 1) {
            $schedules->insert([
                'assessment_id' => $assessmentId,
                'label'         => 'Full Payment',
                'amount'        => round($totalAmount, 2),
                'sort_order'    => 0,
            ]);

            return;
        }

        $share     = round($totalAmount / $installmentCount, 2);
        $allocated = 0.0;
        $labels    = $this->installmentLabels($installmentCount);

        foreach ($labels as $i => $label) {
            $isLast = $i === $installmentCount - 1;
            $amount = $isLast ? round($totalAmount - $allocated, 2) : $share;
            $allocated += $amount;

            $schedules->insert([
                'assessment_id' => $assessmentId,
                'label'         => $label,
                'amount'        => $amount,
                'sort_order'    => $i,
            ]);
        }
    }

    /** @return string[] */
    private function installmentLabels(int $count): array
    {
        $labels = ['Downpayment'];

        for ($i = 2; $i < $count; $i++) {
            $labels[] = $this->ordinal($i) . ' Payment';
        }

        if ($count > 1) {
            $labels[] = 'Final Payment';
        }

        return $labels;
    }

    private function ordinal(int $n): string
    {
        if (in_array($n % 100, [11, 12, 13], true)) {
            return $n . 'th';
        }

        return $n . match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }

    /**
     * Finance-initiated change to a student's installment plan — e.g. a
     * student asks in person to switch from 5 installments to 3. Rebuilds
     * the schedule for the assessment's current total_amount (not just the
     * remaining balance); see generateSchedule()'s docblock for why that's
     * always safe even after partial payment.
     *
     * @return array{ok: bool, error?: string, assessment?: array}
     */
    public function changeInstallmentPlan(int $studentId, string $academicYear, int $installmentCount, int $changedBy): array
    {
        $assessments = new TuitionAssessmentModel();
        $assessment  = $assessments->forStudentYear($studentId, $academicYear);

        if ($assessment === null || (float) $assessment['total_amount'] <= 0) {
            return ['ok' => false, 'error' => 'No assessment exists yet for this student/academic year.'];
        }

        $assessmentId = (int) $assessment['assessment_id'];
        $installmentCount = max(1, min(5, $installmentCount));

        $this->generateSchedule($assessmentId, (float) $assessment['total_amount'], $installmentCount);

        (new AuditLogModel())->record(
            'tuition.installment_plan_changed',
            'tuition_assessment',
            $assessmentId,
            null,
            ['installment_count' => $installmentCount],
            null,
            $changedBy,
            'finance'
        );

        $this->notifyStudent($studentId, 'Your payment plan was updated to ' . $installmentCount . ' installment(s) by the Finance office.');

        return ['ok' => true, 'assessment' => $assessments->find($assessmentId)];
    }

    /**
     * Record a payment against a student's assessment for one academic year,
     * issue its Official Receipt in the same transaction (a payment is
     * never left without one), then recompute the assessment's
     * amount_paid/balance/status from the ledger — the assessment row
     * never has amount_paid written directly.
     *
     * @return array{ok: bool, error?: string, assessment?: array, or_number?: string}
     */
    public function recordPayment(int $studentId, string $academicYear, float $amount, string $method, ?string $referenceNo, ?int $recordedBy, ?int $scheduleId = null): array
    {
        $assessments = new TuitionAssessmentModel();
        $assessment  = $assessments->forStudentYear($studentId, $academicYear);

        if ($assessment === null) {
            return ['ok' => false, 'error' => 'No assessment exists yet for this student/academic year.'];
        }

        $db = db_connect();
        $db->transStart();

        $payments = new StudentPaymentModel();
        $payments->insert([
            'assessment_id' => (int) $assessment['assessment_id'],
            'schedule_id'   => $scheduleId,
            'student_id'    => $studentId,
            'amount'        => $amount,
            'payment_date'  => date('Y-m-d H:i:s'),
            'method'        => $method,
            'reference_no'  => $referenceNo,
            'recorded_by'   => $recordedBy,
        ]);
        $paymentId = $payments->getInsertID();

        $receipt = (new OfficialReceiptModel())->issueFor($paymentId, (int) ($recordedBy ?? $studentId));

        if ($receipt['ok']) {
            $this->recalculateLedger((int) $assessment['assessment_id']);
        }

        $db->transComplete();

        if ($db->transStatus() === false || ! $receipt['ok']) {
            return ['ok' => false, 'error' => $receipt['error'] ?? 'Could not record this payment. Please try again.'];
        }

        return ['ok' => true, 'assessment' => $assessments->find((int) $assessment['assessment_id']), 'or_number' => $receipt['or_number']];
    }

    /**
     * Approve a pending proof: writes the real ledger row via recordPayment
     * (same path a staff-recorded cash payment uses), then flags the proof
     * approved. The proof and the ledger stay deliberately separate — a
     * proof never touches the balance until this runs.
     */
    public function approveProof(int $proofId, int $reviewedBy): array
    {
        $proofs = new PaymentProofModel();
        $proof  = $proofs->find($proofId);

        if ($proof === null || $proof['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This proof is no longer pending.'];
        }

        $assessment = (new TuitionAssessmentModel())->find((int) $proof['assessment_id']);

        if ($assessment === null) {
            return ['ok' => false, 'error' => 'Assessment not found.'];
        }

        $amount = $proof['schedule_id'] !== null
            ? (float) ((new PaymentScheduleModel())->find((int) $proof['schedule_id'])['amount'] ?? 0)
            : (float) $assessment['balance'];

        $result = $this->recordPayment(
            (int) $assessment['student_id'],
            (string) $assessment['academic_year'],
            $amount,
            'Uploaded Proof',
            'PROOF-' . $proofId,
            $reviewedBy,
            $proof['schedule_id'] !== null ? (int) $proof['schedule_id'] : null
        );

        if (! $result['ok']) {
            return $result;
        }

        $proofs->update($proofId, [
            'status'       => 'approved',
            'reviewed_by'  => $reviewedBy,
            'reviewed_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->notifyStudent((int) $assessment['student_id'], 'Your uploaded proof of payment was approved and applied to your account.');

        return $result;
    }

    public function rejectProof(int $proofId, int $reviewedBy, string $remarks): array
    {
        $proofs = new PaymentProofModel();
        $proof  = $proofs->find($proofId);

        if ($proof === null || $proof['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This proof is no longer pending.'];
        }

        $proofs->update($proofId, [
            'status'          => 'rejected',
            'cashier_remarks' => $remarks,
            'reviewed_by'     => $reviewedBy,
            'reviewed_at'     => date('Y-m-d H:i:s'),
        ]);

        $assessment = (new TuitionAssessmentModel())->find((int) $proof['assessment_id']);

        if ($assessment !== null) {
            $this->notifyStudent((int) $assessment['student_id'], 'Your uploaded proof of payment was rejected: ' . $remarks);
        }

        return ['ok' => true];
    }

    private function recalculateLedger(int $assessmentId): void
    {
        $assessments = new TuitionAssessmentModel();
        $assessment  = $assessments->lockForUpdate($assessmentId);

        $totalPaid = (float) (db_connect()->table('student_payments')
            ->select('COALESCE(SUM(amount), 0) AS total')
            ->where('assessment_id', $assessmentId)
            ->get()
            ->getRowArray()['total'] ?? 0);

        $totalAmount = (float) $assessment['total_amount'];

        $assessments->update($assessmentId, [
            'amount_paid' => $totalPaid,
            'balance'     => max(0, $totalAmount - $totalPaid),
            'status'      => $this->statusFor($totalAmount, $totalPaid),
        ]);
    }

    private function notifyStudent(int $studentId, string $message): void
    {
        $student = (new StudentModel())->find($studentId);

        if ($student === null) {
            return;
        }

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'tuition_payment',
            $message,
            'student/tuition'
        );
    }

    private function statusFor(float $totalAmount, float $amountPaid): string
    {
        if ($totalAmount <= 0) {
            return 'unassessed';
        }

        if ($amountPaid <= 0) {
            return 'unpaid';
        }

        return $amountPaid >= $totalAmount ? 'paid' : 'partial';
    }
}
