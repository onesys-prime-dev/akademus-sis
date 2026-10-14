<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
use App\Models\FinancialHoldModel;
use App\Models\NotificationModel;
use App\Models\StudentModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Financial hold placement/release — the only thing that gates enrollment/
 * drop (EnrollmentModel) and, together with an outstanding balance,
 * document release (GeneratedDocumentModel). No cron in this codebase —
 * delinquency flagging is a deliberate staff action from this dashboard,
 * not an automatic background job.
 */
class HoldController extends BaseController
{
    public function index()
    {
        return view('finance/holds/index', [
            'pageTitle' => 'Financial Holds',
            'holds'     => (new FinancialHoldModel())->queue(),
        ] + FinancePortal::shell('holds'));
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

    public function studentHolds($studentId)
    {
        $student = (new StudentModel())->findDetailed((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/holds/student', [
            'pageTitle' => 'Holds — ' . $student['student_number'],
            'student'   => $student,
            'holds'     => (new FinancialHoldModel())->forStudent((int) $studentId),
        ] + FinancePortal::shell('holds'));
    }

    public function place($studentId)
    {
        $rules = [
            'hold_type' => 'required|in_list[' . implode(',', FinancialHoldModel::TYPES) . ']',
            'reason'    => 'required|min_length[5]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $student = (new StudentModel())->find((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new FinancialHoldModel())->place(
            (int) $studentId,
            (string) $this->request->getPost('hold_type'),
            (string) $this->request->getPost('reason'),
            (int) auth()->id()
        );

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'hold_placed',
            'A financial hold has been placed on your account: ' . (string) $this->request->getPost('reason') . '. This may block enrollment, dropping, or document requests until it is resolved.',
            site_url('student/tuition')
        );

        return redirect()->to('/finance/holds/student/' . $studentId)->with('message', 'Hold placed.');
    }

    public function release($id)
    {
        $holds = new FinancialHoldModel();
        $hold  = $holds->find($id);

        if ($hold === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = $holds->release((int) $id, (int) auth()->id());

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        $student = (new StudentModel())->find((int) $hold['student_id']);

        if ($student !== null) {
            (new NotificationModel())->notify(
                (int) $student['user_id'],
                'hold_released',
                'A financial hold on your account has been lifted.',
                site_url('student/tuition')
            );
        }

        return redirect()->to('/finance/holds/student/' . $hold['student_id'])->with('message', 'Hold released.');
    }
}
