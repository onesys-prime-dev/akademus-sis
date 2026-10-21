<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
use App\Models\AcademicYearModel;
use App\Models\LedgerAdjustmentModel;
use App\Models\NotificationModel;
use App\Models\StudentAdditionalFeeModel;
use App\Models\StudentModel;
use App\Models\TuitionAssessmentModel;
use App\Services\TuitionAssessmentService;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * One-off Finance-initiated fee grants — campus event fees, penalties,
 * surcharges — outside the regular curriculum-driven fee structure. Grants
 * don't touch the ledger directly: TuitionAssessmentService::recompute()
 * picks up every active grant on its next run (triggered here explicitly
 * so the effect is visible immediately), same pattern as DiscountController.
 */
class AdditionalFeeController extends BaseController
{
    public function index()
    {
        return view('finance/additional-fees/index', FinancePortal::shell('additional-fees'));
    }

    /**
     * Search students by username/student number — backs the "charge a fee"
     * search box, same pattern as DiscountController::searchStudents().
     */
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

    public function studentFees($studentId)
    {
        $student = (new StudentModel())->findDetailed((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/additional-fees/student', [
            'pageTitle'   => 'Additional Fees — ' . $student['student_number'],
            'student'     => $student,
            'fees'        => (new StudentAdditionalFeeModel())->forStudent((int) $studentId),
            'activeTerm'  => (new AcademicYearModel())->activeFormatted(),
        ] + FinancePortal::shell('additional-fees'));
    }

    public function grant($studentId)
    {
        $rules = [
            'academic_year' => 'required|max_length[60]',
            'label'         => 'required|max_length[100]',
            'amount'        => 'required|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $student = (new StudentModel())->find((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = (new StudentAdditionalFeeModel())->grant(
            (int) $studentId,
            (string) $this->request->getPost('academic_year'),
            (string) $this->request->getPost('label'),
            (float) $this->request->getPost('amount'),
            (int) auth()->id(),
            $this->request->getPost('remarks') ?: null
        );

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        (new TuitionAssessmentService())->recompute((int) $studentId, (string) $this->request->getPost('academic_year'));

        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'additional_fee_charged',
            esc($this->request->getPost('label')) . ' (₱' . number_format((float) $this->request->getPost('amount'), 2) . ') has been added to your tuition account.',
            site_url('student/tuition')
        );

        return redirect()->to('/finance/additional-fees/student/' . $studentId)->with('message', 'Fee added.');
    }

    public function revoke($id)
    {
        $fees = new StudentAdditionalFeeModel();
        $fee  = $fees->find($id);

        if ($fee === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = $fees->revoke((int) $id, (int) auth()->id());

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        // revoke() above only flips student_additional_fees.status — it
        // doesn't touch the ledger. Without reversing the 'additional_fee'
        // debit row ensureAdditionalFeeAdjustments() already materialized
        // for this grant, sumActiveAdditionalFees() would keep counting it
        // forever (it only excludes a row that's been explicitly reversed,
        // never one whose grant simply went inactive) — recompute() below
        // would then silently do nothing, leaving the charge on the bill.
        $assessment = (new TuitionAssessmentModel())->forStudentYear((int) $fee['student_id'], (string) $fee['academic_year']);

        if ($assessment !== null) {
            $ledger = new LedgerAdjustmentModel();
            $adjustment = $ledger->activeForFeeGrant((int) $id, (int) $assessment['assessment_id']);

            if ($adjustment !== null) {
                $ledger->reverse((int) $adjustment['id'], 'Fee revoked by Finance', (int) auth()->id());
            }
        }

        (new TuitionAssessmentService())->recompute((int) $fee['student_id'], (string) $fee['academic_year']);

        $student = (new StudentModel())->find((int) $fee['student_id']);

        if ($student !== null) {
            (new NotificationModel())->notify(
                (int) $student['user_id'],
                'additional_fee_revoked',
                esc($fee['label']) . ' has been removed from your tuition account.',
                site_url('student/tuition')
            );
        }

        return redirect()->to('/finance/additional-fees/student/' . $fee['student_id'])->with('message', 'Fee revoked.');
    }

    /**
     * The bulk-apply screen: pick a term + optional program/year level/
     * section filter, preview who it matches, then apply the same label +
     * amount to every one of them in a single action.
     */
    public function bulkForm()
    {
        $activeTerm = (new AcademicYearModel())->activeFormatted();
        $yearOnly   = $activeTerm !== null ? AcademicYearModel::yearOnly($activeTerm) : null;

        $sections = $yearOnly === null ? [] : db_connect()->table('sections')
            ->distinct()
            ->select('program, year_level, section')
            ->where('academic_year', $yearOnly)
            ->orderBy('program')->orderBy('year_level')->orderBy('section')
            ->get()
            ->getResultArray();

        return view('finance/additional-fees/bulk', [
            'activeTerm' => $activeTerm,
            'sections'   => $sections,
            'programs'   => array_values(array_unique(array_column($sections, 'program'))),
        ] + FinancePortal::shell('additional-fees'));
    }

    /**
     * Read-only: how many/which students the chosen filter currently
     * matches — shown before anything is actually charged, since this is a
     * one-click action against potentially dozens of accounts at once.
     */
    public function bulkPreview()
    {
        $students = $this->matchingStudents(
            (string) $this->request->getGet('program'),
            (string) $this->request->getGet('year_level'),
            (string) $this->request->getGet('section')
        );

        return $this->response->setJSON([
            'ok'    => true,
            'count' => count($students),
            'students' => array_map(static fn (array $s) => [
                'student_id'     => (int) $s['student_id'],
                'student_number' => $s['student_number'],
                'username'       => $s['username'],
            ], array_slice($students, 0, 50)),
        ]);
    }

    public function bulkApply()
    {
        $rules = [
            'academic_year' => 'required|max_length[60]',
            'label'         => 'required|max_length[100]',
            'amount'        => 'required|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $students = $this->matchingStudents(
            (string) $this->request->getPost('program'),
            (string) $this->request->getPost('year_level'),
            (string) $this->request->getPost('section')
        );

        if ($students === []) {
            return redirect()->back()->withInput()->with('errors', ['No students matched that filter — nothing was charged.']);
        }

        $academicYear = (string) $this->request->getPost('academic_year');
        $label        = (string) $this->request->getPost('label');
        $amount       = (float) $this->request->getPost('amount');
        $remarks      = $this->request->getPost('remarks') ?: null;
        $actorId      = (int) auth()->id();
        $batchKey     = bin2hex(random_bytes(10));

        $feeModel  = new StudentAdditionalFeeModel();
        $assessor  = new TuitionAssessmentService();
        $notifier  = new NotificationModel();
        $applied   = 0;

        foreach ($students as $s) {
            $result = $feeModel->grant((int) $s['student_id'], $academicYear, $label, $amount, $actorId, $remarks, $batchKey);

            if (! $result['ok']) {
                continue;
            }

            $assessor->recompute((int) $s['student_id'], $academicYear);
            $notifier->notify((int) $s['user_id'], 'additional_fee_charged', esc($label) . ' (₱' . number_format($amount, 2) . ') has been added to your tuition account.', site_url('student/tuition'));
            $applied++;
        }

        return redirect()
            ->to('/finance/additional-fees/bulk')
            ->with('message', "\"{$label}\" (₱" . number_format($amount, 2) . ") applied to {$applied} student" . ($applied === 1 ? '' : 's') . '.');
    }

    /**
     * @return list<array{student_id:int, user_id:int, student_number:string, username:string}>
     */
    private function matchingStudents(string $program, string $yearLevel, string $section): array
    {
        $activeTerm = (new AcademicYearModel())->activeFormatted();

        if ($activeTerm === null) {
            return [];
        }

        $builder = db_connect()->table('students s')
            ->select('s.student_id, u.id AS user_id, s.student_number, u.username')
            ->join('users u', 'u.id = s.user_id')
            ->join('sections sec', 'sec.section_id = u.section_id')
            ->where('sec.academic_year', AcademicYearModel::yearOnly($activeTerm))
            ->where('s.student_status', 'active');

        if ($program !== '') {
            $builder->where('sec.program', $program);
        }

        if ($yearLevel !== '') {
            $builder->where('sec.year_level', $yearLevel);
        }

        if ($section !== '') {
            $builder->where('sec.section', $section);
        }

        return $builder->orderBy('u.username')->get()->getResultArray();
    }
}
