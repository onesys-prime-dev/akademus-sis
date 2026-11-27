<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
use App\Models\AcademicYearModel;
use App\Models\AssessmentItemModel;
use App\Models\LedgerAdjustmentModel;
use App\Models\StudentModel;
use App\Models\StudentPaymentModel;
use App\Models\TuitionAssessmentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Daily Collection Report, Accounts Receivable Aging, and a per-student
 * Ledger Summary — the three reads Section 6 of the module asked for.
 * None of these write anything; they only read student_payments,
 * tuition_assessments, and ledger_adjustments.
 */
class ReportController extends BaseController
{
    /** Daily Collection Report — one day's payments, grouped by method. */
    public function dailyCollection()
    {
        $date = $this->request->getGet('date') ?: date('Y-m-d');

        $rows = db_connect()->table('student_payments sp')
            ->select('sp.*, s.student_number, u.id AS user_id, u.username, u.photo_mime')
            ->join('students s', 's.student_id = sp.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('DATE(sp.payment_date)', $date)
            ->orderBy('sp.payment_date', 'ASC')
            ->get()
            ->getResultArray();

        $byMethod = [];
        $total    = 0.0;

        foreach ($rows as $row) {
            $byMethod[$row['method']] = ($byMethod[$row['method']] ?? 0) + (float) $row['amount'];
            $total += (float) $row['amount'];
        }

        return view('finance/reports/daily_collection', [
            'pageTitle' => 'Daily Collection Report',
            'date'      => $date,
            'rows'      => $rows,
            'byMethod'  => $byMethod,
            'total'     => $total,
        ] + FinancePortal::shell('reports'));
    }

    /**
     * Accounts Receivable Aging — every assessment with a balance,
     * bucketed by how long it's been sitting on the books (days since the
     * assessment's own created_at, a reasonable proxy for "since billed"
     * — this codebase has no separate due-date-per-assessment concept
     * outside payment_schedules' per-installment due_date, which is a
     * finer grain than this institution-wide report needs).
     */
    public function arAging()
    {
        $rows = db_connect()->table('tuition_assessments ta')
            ->select('ta.*, s.student_number, u.id AS user_id, u.username, u.photo_mime')
            ->join('students s', 's.student_id = ta.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('ta.balance >', 0)
            ->orderBy('ta.created_at', 'ASC')
            ->get()
            ->getResultArray();

        $buckets = ['current' => [], '30' => [], '60' => [], '90' => []];
        $now     = time();

        foreach ($rows as $row) {
            $days = max(0, (int) floor(($now - strtotime($row['created_at'])) / 86400));
            $row['days_outstanding'] = $days;

            $bucket = $days <= 30 ? 'current' : ($days <= 60 ? '30' : ($days <= 90 ? '60' : '90'));
            $buckets[$bucket][] = $row;
        }

        $totals = array_map(
            static fn (array $bucket) => array_sum(array_column($bucket, 'balance')),
            $buckets
        );

        return view('finance/reports/ar_aging', [
            'pageTitle' => 'Accounts Receivable Aging',
            'buckets'   => $buckets,
            'totals'    => $totals,
        ] + FinancePortal::shell('reports'));
    }

    /**
     * One student's full chronological ledger — charges (assessment_items),
     * payments, and adjustments interleaved with a running balance. The
     * one place that reads all three tables together.
     */
    public function ledgerSummary($studentId)
    {
        $student = (new StudentModel())->findDetailed((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $entries = [];

        // Exclude a $0 curriculum-estimate preview stub (is_estimated +
        // unassessed) left over from a term that's no longer active and
        // was never really billed — otherwise it shows as a phantom
        // "Charge" line for a term the student was never enrolled in.
        $activeTerm  = (new AcademicYearModel())->activeFormatted();
        $assessments = array_filter(
            (new TuitionAssessmentModel())->historyFor((int) $studentId),
            static fn (array $a): bool => $a['academic_year'] === $activeTerm
                || ! ((bool) $a['is_estimated'] && $a['status'] === 'unassessed')
        );

        foreach ($assessments as $assessment) {
            $entries[] = [
                'date'   => $assessment['created_at'],
                'type'   => 'Charge',
                'label'  => 'Tuition & Fees — ' . $assessment['academic_year'],
                'debit'  => (float) $assessment['tuition_amount'] + array_sum(array_map(
                    static fn (array $items) => array_sum(array_column($items, 'amount')),
                    (new AssessmentItemModel())->forAssessment((int) $assessment['assessment_id'])
                )),
                'credit' => 0.0,
            ];
        }

        foreach ((new StudentPaymentModel())->historyFor((int) $studentId) as $payment) {
            $entries[] = [
                'date'   => $payment['payment_date'],
                'type'   => 'Payment',
                'label'  => ucfirst(str_replace('_', ' ', $payment['method'])) . (! empty($payment['or_number']) ? ' (' . $payment['or_number'] . ')' : ''),
                'debit'  => 0.0,
                'credit' => (float) $payment['amount'],
            ];
        }

        foreach ((new LedgerAdjustmentModel())->forStudent((int) $studentId) as $adjustment) {
            $entries[] = [
                'date'   => $adjustment['created_at'],
                'type'   => ucfirst($adjustment['category']),
                'label'  => $adjustment['reason'] ?? ucfirst($adjustment['category']),
                'debit'  => $adjustment['type'] === 'debit' ? (float) $adjustment['amount'] : 0.0,
                'credit' => $adjustment['type'] === 'credit' ? (float) $adjustment['amount'] : 0.0,
            ];
        }

        usort($entries, static fn (array $a, array $b) => strtotime($a['date']) <=> strtotime($b['date']));

        $running = 0.0;

        foreach ($entries as &$entry) {
            $running += $entry['debit'] - $entry['credit'];
            $entry['running_balance'] = $running;
        }
        unset($entry);

        return view('finance/reports/ledger_summary', [
            'pageTitle' => 'Ledger Summary — ' . $student['student_number'],
            'student'   => $student,
            'entries'   => $entries,
        ] + FinancePortal::shell('reports'));
    }

    /**
     * Term-scoped: billed/collected/outstanding totals grouped by the
     * student's current program + year level. Like AdditionalFeeController::
     * matchingStudents(), "program at this term" is approximated from the
     * student's CURRENT section filtered to the chosen term's sections —
     * there's no historical per-term section snapshot anywhere else in the
     * schema to join against instead.
     */
    public function revenueByProgram()
    {
        $term = (string) $this->request->getGet('term') ?: $this->defaultTerm();

        $rows = [];

        if ($term !== '') {
            $rows = db_connect()->table('tuition_assessments ta')
                ->select('sec.program, sec.year_level, COUNT(DISTINCT ta.student_id) AS student_count, SUM(ta.total_amount) AS billed, SUM(ta.amount_paid) AS collected, SUM(ta.balance) AS outstanding')
                ->join('students s', 's.student_id = ta.student_id')
                ->join('users u', 'u.id = s.user_id')
                ->join('sections sec', 'sec.section_id = u.section_id')
                ->where('ta.academic_year', $term)
                ->where('sec.academic_year', AcademicYearModel::yearOnly($term))
                ->groupBy('sec.program, sec.year_level')
                ->orderBy('sec.program')->orderBy('sec.year_level')
                ->get()
                ->getResultArray();
        }

        return view('finance/reports/revenue_by_program', [
            'pageTitle'   => 'Revenue by Program',
            'term'        => $term,
            'termOptions' => $this->termOptions(),
            'rows'        => $rows,
            'grandTotal'  => [
                'students'    => array_sum(array_column($rows, 'student_count')),
                'billed'      => array_sum(array_column($rows, 'billed')),
                'collected'   => array_sum(array_column($rows, 'collected')),
                'outstanding' => array_sum(array_column($rows, 'outstanding')),
            ],
        ] + FinancePortal::shell('reports'));
    }

    /**
     * Same program/year-level grouping as AR Aging, but by program instead
     * of by age bucket — which programs are carrying the most unpaid balance
     * this term.
     */
    public function balancesByProgram()
    {
        $term = (string) $this->request->getGet('term') ?: $this->defaultTerm();

        $rows = [];

        if ($term !== '') {
            $rows = db_connect()->table('tuition_assessments ta')
                ->select('sec.program, sec.year_level, COUNT(*) AS count, SUM(ta.balance) AS outstanding')
                ->join('students s', 's.student_id = ta.student_id')
                ->join('users u', 'u.id = s.user_id')
                ->join('sections sec', 'sec.section_id = u.section_id')
                ->where('ta.academic_year', $term)
                ->where('sec.academic_year', AcademicYearModel::yearOnly($term))
                ->where('ta.balance >', 0)
                ->groupBy('sec.program, sec.year_level')
                ->orderBy('outstanding', 'DESC')
                ->get()
                ->getResultArray();
        }

        return view('finance/reports/balances_by_program', [
            'pageTitle'   => 'Outstanding Balances by Program',
            'term'        => $term,
            'termOptions' => $this->termOptions(),
            'rows'        => $rows,
            'grandTotal'  => array_sum(array_column($rows, 'outstanding')),
        ] + FinancePortal::shell('reports'));
    }

    /** A year's worth of collections, one total per month. */
    public function monthlyCollection()
    {
        $year = (int) ($this->request->getGet('year') ?: date('Y'));

        $found = db_connect()->table('student_payments')
            ->select('MONTH(payment_date) AS month, SUM(amount) AS total, COUNT(*) AS count')
            ->where('YEAR(payment_date)', $year)
            ->groupBy('MONTH(payment_date)')
            ->get()
            ->getResultArray();

        $byMonth = array_fill(1, 12, ['total' => 0.0, 'count' => 0]);

        foreach ($found as $row) {
            $byMonth[(int) $row['month']] = ['total' => (float) $row['total'], 'count' => (int) $row['count']];
        }

        $totals = array_column($byMonth, 'total');

        return view('finance/reports/monthly_collection', [
            'pageTitle' => 'Monthly Collection Summary',
            'year'      => $year,
            'byMonth'   => $byMonth,
            'total'     => array_sum($totals),
            'max'       => max($totals) ?: 1.0,
        ] + FinancePortal::shell('reports'));
    }

    /**
     * Financial impact of the Discounts and Additional Fees modules over a
     * date range — what was actually granted, grouped by discount type /
     * fee label. Reads the same 'discount'/'additional_fee' ledger rows
     * TuitionAssessmentService materializes, excluding any row a later
     * correction has reversed (mirrors sumActiveDiscounts()/
     * sumActiveAdditionalFees()'s own reversal-aware filtering, done here as
     * a NOT EXISTS instead of a PHP post-filter since this report can span
     * thousands of rows across many assessments).
     */
    public function discountsFeesSummary()
    {
        $from = (string) ($this->request->getGet('from') ?: date('Y-m-01'));
        $to   = (string) ($this->request->getGet('to') ?: date('Y-m-d'));

        $db = db_connect();
        $notReversed = 'NOT EXISTS (SELECT 1 FROM ledger_adjustments r WHERE r.reverses_adjustment_id = la.id)';

        $discounts = $db->table('ledger_adjustments la')
            ->select('dt.name AS label, SUM(la.amount) AS total, COUNT(*) AS count')
            ->join('student_discounts sd', 'sd.id = la.discount_grant_id')
            ->join('discount_types dt', 'dt.id = sd.discount_type_id')
            ->where('la.category', 'discount')
            ->where('DATE(la.created_at) >=', $from)
            ->where('DATE(la.created_at) <=', $to)
            ->where($notReversed, null, false)
            ->groupBy('dt.name')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResultArray();

        $fees = $db->table('ledger_adjustments la')
            ->select('saf.label AS label, SUM(la.amount) AS total, COUNT(*) AS count')
            ->join('student_additional_fees saf', 'saf.id = la.fee_grant_id')
            ->where('la.category', 'additional_fee')
            ->where('DATE(la.created_at) >=', $from)
            ->where('DATE(la.created_at) <=', $to)
            ->where($notReversed, null, false)
            ->groupBy('saf.label')
            ->orderBy('total', 'DESC')
            ->get()
            ->getResultArray();

        return view('finance/reports/discounts_fees_summary', [
            'pageTitle'      => 'Discounts & Additional Fees Summary',
            'from'           => $from,
            'to'             => $to,
            'discounts'      => $discounts,
            'discountsTotal' => array_sum(array_column($discounts, 'total')),
            'fees'           => $fees,
            'feesTotal'      => array_sum(array_column($fees, 'total')),
        ] + FinancePortal::shell('reports'));
    }

    /** @return list<array{value:string, label:string}> */
    private function termOptions(): array
    {
        $terms = (new AcademicYearModel())->orderBy('label', 'DESC')->orderBy('semester', 'DESC')->findAll();

        return array_map(
            static fn (array $t): array => ['value' => AcademicYearModel::format($t['label'], $t['semester']), 'label' => $t['label'] . ' · ' . $t['semester'] . ' Semester'],
            $terms
        );
    }

    private function defaultTerm(): string
    {
        $active = (new AcademicYearModel())->activeFormatted();

        if ($active !== null) {
            return $active;
        }

        return $this->termOptions()[0]['value'] ?? '';
    }

    public function searchStudents()
    {
        $query = trim((string) $this->request->getGet('q'));

        if ($query === '') {
            return $this->response->setJSON(['ok' => true, 'results' => []]);
        }

        $results = db_connect()->table('students s')
            ->select('s.student_id, s.student_number, u.id AS user_id, u.username, u.photo_mime')
            ->join('users u', 'u.id = s.user_id')
            ->groupStart()
                ->like('u.username', $query)
                ->orLike('s.student_number', $query)
            ->groupEnd()
            ->limit(20)
            ->get()
            ->getResultArray();

        return $this->response->setJSON(['ok' => true, 'results' => $results]);
    }

    public function index()
    {
        return view('finance/reports/index', [
            'pageTitle' => 'Reports',
        ] + FinancePortal::shell('reports'));
    }
}
