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
