<?php

namespace App\Services;

use App\Models\AcademicYearModel;
use App\Models\AssessmentItemModel;
use App\Models\AuditLogModel;
use App\Models\LedgerAdjustmentModel;
use App\Models\NotificationModel;
use App\Models\OfficialReceiptModel;
use App\Models\PaymentProofModel;
use App\Models\PaymentScheduleModel;
use App\Models\ProgramFeeRateModel;
use App\Models\StudentAdditionalFeeModel;
use App\Models\StudentDiscountModel;
use App\Libraries\TermCurriculum;
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

        // enrollments.academic_year (term-scoped) is the filter here, not
        // the section's — a section spans the whole school year now, so it
        // can no longer tell this term's enrollments apart from another.
        $totalUnits = (float) ($db->table('enrollments e')
            ->select('COALESCE(SUM(s.units), 0) AS total')
            ->join('subjects s', 's.subject_code = e.subject_code')
            ->where('e.user_id', $student['user_id'])
            ->where('e.status', 'enrolled')
            ->where('e.academic_year', $academicYear)
            ->get()
            ->getRowArray()['total'] ?? 0);

        // No real enrollment yet (brand-new account, or their section hasn't
        // been plotted) — fall back to what the CURRICULUM says their
        // year/term should carry, so a tuition estimate exists from day
        // one instead of staying blank until a chairperson schedules
        // classes. Marked is_estimated so the UI can tell a student this
        // number isn't final yet. The instant real enrollment exists this
        // branch stops being reached at all — $totalUnits above already
        // reflects it, taking priority.
        $isEstimated = false;

        if ($totalUnits <= 0 && $student['curriculum_id'] !== null) {
            $estimatedUnits = $this->curriculumExpectedUnits((int) $student['curriculum_id'], (int) $student['user_id'], $academicYear);

            if ($estimatedUnits > 0) {
                $totalUnits  = $estimatedUnits;
                $isEstimated = true;
            }
        }

        $rate = $student['program_id'] !== null
            ? (new TuitionRateModel())->forProgramYear((int) $student['program_id'], $academicYear)
            : null;

        $feeItems = ($rate !== null && $student['program_id'] !== null)
            ? (new ProgramFeeRateModel())->forProgramYear((int) $student['program_id'], $academicYear)
            : [];

        $assessments  = new TuitionAssessmentModel();
        $existing     = $assessments->forStudentYear($studentId, $academicYear);
        $amountPaid   = (float) ($existing['amount_paid'] ?? 0);
        $wasEstimated = (bool) ($existing['is_estimated'] ?? false);

        if ($rate === null) {
            $data = [
                'student_id'      => $studentId,
                'academic_year'   => $academicYear,
                'total_units'     => $totalUnits,
                'is_estimated'    => $isEstimated,
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
                'is_estimated'    => $isEstimated,
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

        // Additional fees (Finance-granted one-off charges — campus events,
        // penalties, surcharges; see CreateStudentAdditionalFees) are fixed
        // amounts, so unlike a percentage discount they carry no re-basing
        // risk — applied even to a preliminary estimate, since a charge
        // like a late-enrollment surcharge is independent of enrollment/
        // unit load.
        //
        // Discounts need assessmentId to exist first (ledger_adjustments
        // rows are keyed to it) — ensureDiscountAdjustments() creates any
        // missing ones idempotently, then total_amount/balance/status are
        // recomputed net of every discount and additional fee on record for
        // this assessment.
        //
        // Discounts specifically are never applied to a preliminary
        // estimate: a percentage scholarship would be frozen at the value
        // it computed on estimated units, and ensureDiscountAdjustments()
        // is idempotent per grant, so it would never be recalculated once
        // real enrollment changes the gross amount. The first recompute
        // with real enrollment applies them.
        if ($rate !== null) {
            $this->ensureAdditionalFeeAdjustments($assessmentId, $studentId, $academicYear);
            $additionalFeesAmount = $this->sumActiveAdditionalFees($assessmentId);

            $discountAmount = 0.0;

            if (! $isEstimated) {
                $this->ensureDiscountAdjustments($assessmentId, $studentId, $academicYear, $data['total_amount']);
                $discountAmount = $this->sumActiveDiscounts($assessmentId);
            }

            $netAmount = max(0, $data['total_amount'] - $discountAmount + $additionalFeesAmount);
            $balance   = max(0, $netAmount - $amountPaid);

            $assessments->update($assessmentId, [
                'discount_amount'        => $discountAmount,
                'additional_fees_amount' => $additionalFeesAmount,
                'total_amount'           => $netAmount,
                'balance'                => $balance,
                'status'                 => $this->statusFor($netAmount, $amountPaid),
            ]);
        }

        $finalAssessment = $assessments->find($assessmentId);

        // A real assessment whose total MOVED while nothing has been paid
        // against it (the normal case during a block enrollment: enrollOne()
        // recomputes once per subject, so the first subject used to lock the
        // whole installment plan at a fraction of the final bill). Safe to
        // rebuild precisely because amount_paid is still 0 — no plan is
        // being paid against yet. A paid assessment is never reshuffled.
        $scheduleIsStale = ! $isNew
            && ! $wasEstimated
            && (float) $finalAssessment['amount_paid'] <= 0
            && (float) $finalAssessment['total_amount'] !== (float) ($existing['total_amount'] ?? 0);

        // Only ever generated once — a later rate correction must not
        // reshuffle a payment plan the student may already be paying
        // against (see payment_schedules migration docblock).
        //
        // An ESTIMATE has no real bill behind it yet, so it must not carry an
        // installment plan: locking a schedule at estimated amounts would
        // leave every installment wrong the moment the student actually
        // enrolls (this is the only place a schedule is ever generated).
        // The first recompute carrying real enrollment creates it — the
        // estimated -> real transition therefore counts as "new" too.
        //
        // Any plan left over from a previous, real assessment (every course
        // dropped since) is removed for the same reason: its installments
        // keep summing to an amount that no longer exists. Safe with
        // payments already made — they are never pinned to a row (see
        // generateSchedule()).
        if ($isEstimated) {
            (new PaymentScheduleModel())->where('assessment_id', $assessmentId)->delete();
        } elseif ($rate !== null) {
            if ($isNew || $wasEstimated || $scheduleIsStale) {
                $this->generateSchedule($assessmentId, (float) $finalAssessment['total_amount'], max(1, (int) $rate['installment_count']), $academicYear);
            } else {
                // Total moved (e.g. Finance just charged an additional fee)
                // but the plan is locked — amount_paid > 0, so a full
                // regenerate would reshuffle installments already paid
                // against. Reconciled as one extra catch-up line instead;
                // idempotent, so calling this on every recompute is safe.
                $this->reconcileLockedSchedule($assessmentId, (float) $finalAssessment['total_amount']);
            }
        }

        return ['ok' => true, 'assessment' => $finalAssessment];
    }

    /**
     * Keeps a LOCKED schedule's rows summing to the real total without
     * touching any existing installment — appends (or updates) one
     * dedicated "Additional Fees" row covering exactly the gap between
     * total_amount and what the other rows already add up to, and removes
     * that row once there's no gap left (e.g. the fee causing it was
     * reversed). Only called when generateSchedule() itself is off-limits
     * (see the scheduleIsStale guard above) — a fresh/estimate-transition/
     * amount_paid=0 schedule is always fully regenerated instead, which
     * already produces exact installments with no gap to reconcile.
     */
    private function reconcileLockedSchedule(int $assessmentId, float $totalAmount): void
    {
        $schedules = new PaymentScheduleModel();
        $rows      = $schedules->forAssessment($assessmentId);

        if ($rows === []) {
            return;
        }

        $catchUpLabel = 'Additional Fees';
        $baseTotal    = 0.0;
        $catchUpRow   = null;
        $maxSortOrder = 0;

        foreach ($rows as $row) {
            $maxSortOrder = max($maxSortOrder, (int) $row['sort_order']);

            if ($row['label'] === $catchUpLabel) {
                $catchUpRow = $row;

                continue;
            }

            $baseTotal += (float) $row['amount'];
        }

        $gap = round($totalAmount - $baseTotal, 2);

        if ($gap <= 0) {
            if ($catchUpRow !== null) {
                $schedules->delete((int) $catchUpRow['schedule_id']);
            }

            return;
        }

        if ($catchUpRow !== null) {
            $schedules->update((int) $catchUpRow['schedule_id'], ['amount' => $gap]);
        } else {
            $schedules->insert([
                'assessment_id' => $assessmentId,
                'label'         => $catchUpLabel,
                'amount'        => $gap,
                'sort_order'    => $maxSortOrder + 1,
            ]);
        }
    }

    /**
     * What the student's CURRICULUM says their current year/term should
     * carry (curriculum_subjects for their year level + this academic
     * year's semester), used only as a stand-in for real enrolled units
     * when there aren't any yet. Year level comes from their assigned
     * section (users.section_id -> sections.year_level, the same
     * resolution SubjectDemandService uses elsewhere); a student with no
     * section yet (brand new, not yet placed into a cohort) is assumed to
     * be 1st Year — the only sensible default for someone with no cohort
     * at all.
     *
     * Curricula are often still being built out, so the exact year level
     * can legitimately have no subjects yet (a 2nd-Year cohort while only
     * 1st-Year subjects have been entered). Returning 0 there would mean
     * "no tuition at all" for a student who is very much expected to pay,
     * which is the exact blank page this fallback exists to prevent — so
     * an empty level borrows the nearest populated level at or below it,
     * and the lowest populated level when nothing at/below exists.
     */
    private function curriculumExpectedUnits(int $curriculumId, int $userId, string $academicYear): float
    {
        $semester = TermCurriculum::semesterOf($academicYear);

        if ($semester === null) {
            return 0.0;
        }

        $sectionYearLevel = db_connect()->table('users u')
            ->select('sec.year_level')
            ->join('sections sec', 'sec.section_id = u.section_id', 'left')
            ->where('u.id', $userId)
            ->get()
            ->getRowArray()['year_level'] ?? null;

        $yearLevel = $sectionYearLevel !== null ? TermCurriculum::yearLevelOf($sectionYearLevel) : null;
        $yearLevel ??= 1;

        // One grouped read covers both the exact level and the fallback
        // (rather than re-querying per candidate level).
        $unitsByLevel = [];

        foreach (db_connect()->table('curriculum_subjects cs')
            ->select('cs.year_level, COALESCE(SUM(s.units), 0) AS units')
            ->join('subjects s', 's.subject_id = cs.subject_id')
            ->where('cs.curriculum_id', $curriculumId)
            ->where('cs.semester', $semester)
            ->where('cs.is_active', 1)
            ->where('s.is_active', 1)
            ->groupBy('cs.year_level')
            ->orderBy('cs.year_level', 'ASC')
            ->get()
            ->getResultArray() as $row) {
            $unitsByLevel[(int) $row['year_level']] = (float) $row['units'];
        }

        if (($unitsByLevel[$yearLevel] ?? 0.0) > 0) {
            return $unitsByLevel[$yearLevel];
        }

        // Ascending order, so the last populated level at/below the
        // student's own is the nearest one; otherwise the first populated
        // level above is the closest available.
        $fallback = null;

        foreach ($unitsByLevel as $level => $units) {
            if ($units <= 0) {
                continue;
            }

            if ($level <= $yearLevel) {
                $fallback = $units;

                continue;
            }

            $fallback ??= $units;

            break;
        }

        return $fallback ?? 0.0;
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
     * Ensures one 'additional_fee' ledger_adjustments debit row exists per
     * active student_additional_fees grant for this exact student/term.
     * Idempotent via LedgerAdjustmentModel::activeForFeeGrant() — safe to
     * call on every recompute, mirrors ensureDiscountAdjustments() but with
     * no percentage math (every grant is already a fixed peso amount).
     */
    private function ensureAdditionalFeeAdjustments(int $assessmentId, int $studentId, string $academicYear): void
    {
        $grants = (new StudentAdditionalFeeModel())->activeFor($studentId, $academicYear);
        $ledger = new LedgerAdjustmentModel();

        foreach ($grants as $grant) {
            if ($ledger->activeForFeeGrant((int) $grant['id'], $assessmentId) !== null) {
                continue;
            }

            $grantedBy = $grant['granted_by'] !== null ? (int) $grant['granted_by'] : $studentId;
            $ledger->record($assessmentId, $studentId, 'debit', 'additional_fee', (float) $grant['amount'], $grant['label'], $grantedBy, null, (int) $grant['id']);
        }
    }

    /**
     * Sum of every still-active (never-reversed) 'additional_fee' debit
     * adjustment for this assessment — mirrors sumActiveDiscounts()'s own
     * reversal-aware filtering exactly.
     */
    private function sumActiveAdditionalFees(int $assessmentId): float
    {
        $ledger = new LedgerAdjustmentModel();
        $rows   = $ledger->where('assessment_id', $assessmentId)->where('category', 'additional_fee')->findAll();

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
    public function generateSchedule(int $assessmentId, float $totalAmount, int $installmentCount, ?string $academicYear = null): void
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
        $dueDates  = $academicYear !== null ? $this->dueDatesFor($installmentCount, $academicYear) : [];

        foreach ($labels as $i => $label) {
            $isLast = $i === $installmentCount - 1;
            $amount = $isLast ? round($totalAmount - $allocated, 2) : $share;
            $allocated += $amount;

            $schedules->insert([
                'assessment_id' => $assessmentId,
                'label'         => $label,
                'due_date'      => $dueDates[$i] ?? null,
                'amount'        => $amount,
                'sort_order'    => $i,
            ]);
        }
    }

    /**
     * One-time (but safely re-runnable) backfill for payment_schedules rows
     * created before due-date auto-generation existed: every row inserted
     * by generateSchedule() prior to this change has due_date NULL. Finds
     * every assessment with at least one such row, recomputes that
     * assessment's due dates from its academic_year the same way
     * generateSchedule() does for new rows, and fills in only the NULL
     * ones — amounts/labels/sort_order are left untouched, and a row that
     * already has a due_date (hand-set, or already backfilled) is never
     * overwritten. Assessments whose term has no start_date/end_date
     * configured are silently skipped, same as dueDatesFor() does for a new
     * schedule — nothing to backfill them with yet.
     *
     * @return int number of rows updated
     */
    public function backfillDueDates(): int
    {
        $schedules = new PaymentScheduleModel();

        $assessmentIds = array_unique(array_column(
            $schedules->select('assessment_id')->where('due_date', null)->where('sort_order >', 0)->findAll(),
            'assessment_id'
        ));

        if ($assessmentIds === []) {
            return 0;
        }

        $assessments = (new TuitionAssessmentModel())->whereIn('assessment_id', $assessmentIds)->findAll();
        $updated     = 0;

        foreach ($assessments as $assessment) {
            $assessmentId = (int) $assessment['assessment_id'];
            $rows         = $schedules->forAssessment($assessmentId);
            $dueDates     = $this->dueDatesFor(count($rows), (string) $assessment['academic_year']);

            foreach ($rows as $row) {
                $sortOrder = (int) $row['sort_order'];

                if ($row['due_date'] !== null || ! isset($dueDates[$sortOrder])) {
                    continue;
                }

                $schedules->update((int) $row['schedule_id'], ['due_date' => $dueDates[$sortOrder]]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Due dates for installments 2..N (the downpayment is always due on
     * enrollment, so it never gets a fixed date — matching how this school's
     * existing billing has always shown it). Spread evenly across the term's
     * start_date/end_date, trimmed in 3 weeks from each end so nothing falls
     * right at enrollment or right at the term's close, and snapped forward
     * to the nearest Monday so due dates land on a predictable weekday
     * instead of a different day each cycle.
     *
     * Returns null for any index whose date couldn't be resolved — including
     * the whole thing when the term's academic_years row has no date range
     * set yet — rather than guessing; the row is simply left with due_date
     * null ("Not set") until the calendar is configured, same as before.
     *
     * @return array<int, ?string> keyed by the same 0-based index used in
     *                              generateSchedule()'s $labels loop
     */
    private function dueDatesFor(int $installmentCount, string $academicYear): array
    {
        $remaining = $installmentCount - 1;

        if ($remaining <= 0) {
            return [];
        }

        $term = $this->termDateRange($academicYear);

        if ($term === null) {
            return [];
        }

        [$startDate, $endDate] = $term;

        $start = (new \DateTimeImmutable($startDate))->modify('+21 days');
        $end   = (new \DateTimeImmutable($endDate))->modify('-21 days');

        if ($end <= $start) {
            $start = new \DateTimeImmutable($startDate);
            $end   = new \DateTimeImmutable($endDate);
        }

        $dates = [];

        if ($remaining === 1) {
            $dates[] = $this->nextMonday($start);
        } else {
            $stepSeconds = intdiv($end->getTimestamp() - $start->getTimestamp(), $remaining - 1);

            for ($i = 0; $i < $remaining; $i++) {
                $dates[] = $this->nextMonday($start->modify('+' . ($stepSeconds * $i) . ' seconds'));
            }
        }

        // index 0 is the Downpayment (always undated); indexes 1..N-1 map to $dates[0..]
        $byIndex = [];

        foreach ($dates as $i => $date) {
            $byIndex[$i + 1] = $date->format('Y-m-d');
        }

        return $byIndex;
    }

    private function nextMonday(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return (int) $date->format('N') === 1 ? $date : $date->modify('next Monday');
    }

    /**
     * Resolves a term-scoped academic_year string ("AY 2026 - 2027 · 1st
     * Semester") back to its academic_years row's [start_date, end_date],
     * or null when the string can't be parsed or that row has no date
     * range configured. Mirrors StudentAccountNormalizer::splitAcademicYear().
     *
     * @return ?array{0: string, 1: string}
     */
    private function termDateRange(string $academicYear): ?array
    {
        if (preg_match('/AY\s*([\d]{4}\s*-\s*[\d]{4}).*?(1st|2nd|Summer)/u', $academicYear, $m) !== 1) {
            return null;
        }

        $row = (new AcademicYearModel())
            ->where('label', str_replace(' ', '', $m[1]))
            ->where('semester', $m[2])
            ->first();

        if ($row === null || $row['start_date'] === null || $row['end_date'] === null) {
            return null;
        }

        return [$row['start_date'], $row['end_date']];
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

        $this->generateSchedule($assessmentId, (float) $assessment['total_amount'], $installmentCount, (string) $assessment['academic_year']);

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
