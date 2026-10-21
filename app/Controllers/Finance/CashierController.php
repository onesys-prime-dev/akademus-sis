<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
use App\Models\NotificationModel;
use App\Models\PaymentProofModel;
use App\Models\StudentModel;
use App\Models\StudentPaymentModel;
use App\Models\TuitionAssessmentModel;
use App\Services\TuitionAssessmentService;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Finance's dedicated cashiering screen — search a student, record a
 * payment against their current-term assessment, hand off to the
 * generated receipt immediately. The payment-proof review queue lives at
 * Admin\PaymentProofController (dual/triple-mounted, see its portal()
 * helper), reachable from here via the nav rather than duplicated.
 */
class CashierController extends BaseController
{
    public function index()
    {
        return view('finance/cashier/index', [
            'pageTitle' => 'Cashier',
        ] + FinancePortal::shell('cashier'));
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

    public function studentLedger($studentId)
    {
        $student = (new StudentModel())->findDetailed((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/cashier/student', [
            'pageTitle'   => 'Cashier — ' . $student['student_number'],
            'student'     => $student,
            'assessments' => (new TuitionAssessmentModel())->historyFor((int) $studentId),
            'payments'    => (new StudentPaymentModel())->historyFor((int) $studentId),
            'proofs'      => (new PaymentProofModel())->forStudent((int) $studentId),
            'methods'     => StudentPaymentModel::METHODS,
        ] + FinancePortal::shell('cashier'));
    }

    public function recordPayment($studentId)
    {
        $rules = [
            'academic_year' => 'required',
            'amount'        => 'required|decimal|greater_than[0]',
            'method'        => 'required|in_list[' . implode(',', StudentPaymentModel::METHODS) . ']',
            'reference_no'  => 'permit_empty|max_length[50]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $student = (new StudentModel())->find((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new TuitionAssessmentService())->recordPayment(
            (int) $studentId,
            (string) $this->request->getPost('academic_year'),
            (float) $this->request->getPost('amount'),
            (string) $this->request->getPost('method'),
            $this->request->getPost('reference_no') ?: null,
            (int) auth()->id()
        );

        if (! $result['ok']) {
            return redirect()->to('/finance/cashier/student/' . $studentId)->with('errors', [$result['error']]);
        }

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'payment_recorded',
            'A payment of ' . number_format((float) $this->request->getPost('amount'), 2) . ' was recorded on your tuition account.',
            site_url('student/tuition')
        );

        return redirect()->to('/finance/cashier/student/' . $studentId)->with('message', 'Payment recorded. Official Receipt ' . $result['or_number'] . ' issued.');
    }

    /**
     * What changeInstallments() WOULD produce for the given academic year +
     * installment count — same computeScheduleRows() call the real save
     * uses, just not written to the database. Lets Finance see the exact
     * due dates and per-installment amounts before committing to a plan.
     */
    public function previewInstallments($studentId)
    {
        $academicYear     = (string) $this->request->getGet('academic_year');
        $installmentCount = (int) $this->request->getGet('installment_count');

        if ($academicYear === '' || $installmentCount < 1 || $installmentCount > 5) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Invalid academic year or installment count.']);
        }

        $assessment = (new TuitionAssessmentModel())->forStudentYear((int) $studentId, $academicYear);

        if ($assessment === null || (float) $assessment['total_amount'] <= 0) {
            return $this->response->setJSON(['ok' => false, 'error' => 'No assessment exists yet for this student/academic year.']);
        }

        $rows = (new TuitionAssessmentService())->computeScheduleRows(
            (float) $assessment['total_amount'],
            $installmentCount,
            $academicYear,
            (float) ($assessment['additional_fees_amount'] ?? 0)
        );

        return $this->response->setJSON([
            'ok'   => true,
            'rows' => array_map(static function (array $r) use ($installmentCount) {
                $dueDateText = match (true) {
                    $r['due_date'] !== null                                        => date('M j, Y', strtotime($r['due_date'])),
                    $r['label'] === 'Downpayment' && $installmentCount > 1         => 'Due on enrollment',
                    $r['label'] === 'Full Payment' || $r['label'] === 'Additional Fees' => null,
                    default                                                        => 'Not set — academic year dates not configured',
                };

                return ['label' => $r['label'], 'due_date' => $dueDateText, 'amount' => number_format($r['amount'], 2)];
            }, $rows),
        ]);
    }

    public function changeInstallments($studentId)
    {
        $rules = [
            'academic_year'     => 'required',
            'installment_count' => 'required|in_list[1,2,3,4,5]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $student = (new StudentModel())->find((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new TuitionAssessmentService())->changeInstallmentPlan(
            (int) $studentId,
            (string) $this->request->getPost('academic_year'),
            (int) $this->request->getPost('installment_count'),
            (int) auth()->id()
        );

        if (! $result['ok']) {
            return redirect()->to('/finance/cashier/student/' . $studentId)->with('errors', [$result['error']]);
        }

        return redirect()->to('/finance/cashier/student/' . $studentId)->with('message', 'Installment plan updated.');
    }
}
