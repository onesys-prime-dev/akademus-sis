<?php

namespace App\Services;

use App\Models\StudentModel;
use App\Models\StudentPaymentModel;
use App\Models\TuitionAssessmentModel;
use App\Models\TuitionRateModel;

/**
 * Computes and stores a student's tuition assessment for one academic year,
 * from their currently-enrolled subject units and the program's tuition_rates
 * row. Called after every enrollment change (see EnrollmentModel::enrollOne/
 * drop) so the assessment never drifts out of sync with the roster, and
 * again whenever a payment is recorded so the balance/status stay correct.
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

        $assessments = new TuitionAssessmentModel();
        $existing    = $assessments->forStudentYear($studentId, $academicYear);
        $amountPaid  = (float) ($existing['amount_paid'] ?? 0);

        if ($rate === null) {
            $data = [
                'student_id'      => $studentId,
                'academic_year'   => $academicYear,
                'total_units'     => $totalUnits,
                'tuition_amount'  => 0,
                'misc_fee_amount' => 0,
                'total_amount'    => 0,
                'amount_paid'     => $amountPaid,
                'balance'         => 0,
                'status'          => 'unassessed',
            ];
        } else {
            $tuitionAmount = $totalUnits * (float) $rate['per_unit_rate'];
            $miscFee       = (float) $rate['misc_fee'];
            $totalAmount   = $tuitionAmount + $miscFee;
            $balance       = max(0, $totalAmount - $amountPaid);

            $data = [
                'student_id'      => $studentId,
                'academic_year'   => $academicYear,
                'total_units'     => $totalUnits,
                'tuition_amount'  => $tuitionAmount,
                'misc_fee_amount' => $miscFee,
                'total_amount'    => $totalAmount,
                'amount_paid'     => $amountPaid,
                'balance'         => $balance,
                'status'          => $this->statusFor($totalAmount, $amountPaid),
            ];
        }

        if ($existing !== null) {
            $assessments->update((int) $existing['assessment_id'], $data);
            $assessmentId = (int) $existing['assessment_id'];
        } else {
            $assessments->insert($data);
            $assessmentId = $assessments->getInsertID();
        }

        return ['ok' => true, 'assessment' => $assessments->find($assessmentId)];
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

        $totalPaid = (float) (db_connect()->table('student_payments')
            ->select('COALESCE(SUM(amount), 0) AS total')
            ->where('assessment_id', $assessment['assessment_id'])
            ->get()
            ->getRowArray()['total'] ?? 0);

        $totalAmount = (float) $assessment['total_amount'];

        $assessments->update((int) $assessment['assessment_id'], [
            'amount_paid' => $totalPaid,
            'balance'     => max(0, $totalAmount - $totalPaid),
            'status'      => $this->statusFor($totalAmount, $totalPaid),
        ]);

        return ['ok' => true, 'assessment' => $assessments->find((int) $assessment['assessment_id'])];
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
