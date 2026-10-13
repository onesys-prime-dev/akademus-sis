<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
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
            ->select('sp.*, s.student_number, u.username')
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
            ->select('ta.*, s.student_number, u.username')
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

        foreach ((new TuitionAssessmentModel())->historyFor((int) $studentId) as $assessment) {
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

    public function searchStudents()
    {
        $query = trim((string) $this->request->getGet('q'));

        if ($query === '') {
            return $this->response->setJSON(['ok' => true, 'results' => []]);
        }

        $results = db_connect()->table('students s')
            ->select('s.student_id, s.student_number, u.username')
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
