<?php

namespace App\Services;

use App\Models\AssessmentItemModel;
use App\Models\NotificationModel;
use App\Models\PaymentProofModel;
use App\Models\PaymentScheduleModel;
use App\Models\ProgramFeeRateModel;
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
                'student_id'     => $studentId,
                'academic_year'  => $academicYear,
                'total_units'    => $totalUnits,
                'tuition_amount' => 0,
                'total_amount'   => 0,
                'amount_paid'    => $amountPaid,
                'balance'        => 0,
                'status'         => 'unassessed',
            ];
            $feeItems = [];
        } else {
            $tuitionAmount = $totalUnits * (float) $rate['per_unit_rate'];
            $addedFeesTotal = array_sum(array_column($feeItems, 'amount'));
            $totalAmount   = $tuitionAmount + $addedFeesTotal;
            $balance       = max(0, $totalAmount - $amountPaid);

            $data = [
                'student_id'     => $studentId,
                'academic_year'  => $academicYear,
                'total_units'    => $totalUnits,
                'tuition_amount' => $tuitionAmount,
                'total_amount'   => $totalAmount,
                'amount_paid'    => $amountPaid,
                'balance'        => $balance,
                'status'         => $this->statusFor($totalAmount, $amountPaid),
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

        // Only ever generated once — a later rate correction must not
        // reshuffle a payment plan the student may already be paying
        // against (see payment_schedules migration docblock).
        if ($isNew && $rate !== null) {
            $this->generateSchedule($assessmentId, (float) $data['total_amount'], max(1, (int) $rate['installment_count']));
        }

        return ['ok' => true, 'assessment' => $assessments->find($assessmentId)];
    }

    private function generateSchedule(int $assessmentId, float $totalAmount, int $installmentCount): void
    {
        $schedules = new PaymentScheduleModel();

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
     * Record a payment against a student's assessment for one academic year,
     * then recompute the assessment's amount_paid/balance/status from the
     * ledger — the assessment row never has amount_paid written directly.
     */
    public function recordPayment(int $studentId, string $academicYear, float $amount, string $method, ?string $referenceNo, ?int $recordedBy): array
    {
        $assessments = new TuitionAssessmentModel();
        $assessment  = $assessments->forStudentYear($studentId, $academicYear);

        if ($assessment === null) {
            return ['ok' => false, 'error' => 'No assessment exists yet for this student/academic year.'];
        }

        (new StudentPaymentModel())->insert([
            'assessment_id' => (int) $assessment['assessment_id'],
            'student_id'    => $studentId,
            'amount'        => $amount,
            'payment_date'  => date('Y-m-d H:i:s'),
            'method'        => $method,
            'reference_no'  => $referenceNo,
            'recorded_by'   => $recordedBy,
        ]);

        $this->recalculateLedger((int) $assessment['assessment_id']);

        return ['ok' => true, 'assessment' => $assessments->find((int) $assessment['assessment_id'])];
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
            $reviewedBy
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
        $assessment  = $assessments->find($assessmentId);

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
