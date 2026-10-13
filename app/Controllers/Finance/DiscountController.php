<?php

namespace App\Controllers\Finance;

use App\Controllers\BaseController;
use App\Libraries\FinancePortal;
use App\Models\DiscountTypeModel;
use App\Models\StudentDiscountModel;
use App\Models\StudentModel;
use App\Services\TuitionAssessmentService;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Discount/scholarship catalog CRUD + grant/revoke against a student.
 * Granting doesn't touch the ledger directly — TuitionAssessmentService::
 * recompute() picks up the new active grant on its next run (triggered
 * here explicitly so the effect is visible immediately).
 */
class DiscountController extends BaseController
{
    public function index()
    {
        return view('finance/discounts/index', [
            'pageTitle'     => 'Discounts & Scholarships',
            'discountTypes' => (new DiscountTypeModel())->orderBy('sort_order')->orderBy('name')->findAll(),
        ] + FinancePortal::shell('discounts'));
    }

    public function store()
    {
        $rules = [
            'name'          => 'required|max_length[100]',
            'code'          => 'required|max_length[30]|alpha_dash',
            'value_type'    => 'required|in_list[' . implode(',', DiscountTypeModel::VALUE_TYPES) . ']',
            'default_value' => 'required|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/finance/discounts')->with('errors', $this->validator->getErrors());
        }

        (new DiscountTypeModel())->insert([
            'name'          => $this->request->getPost('name'),
            'code'          => strtoupper((string) $this->request->getPost('code')),
            'value_type'    => $this->request->getPost('value_type'),
            'default_value' => (float) $this->request->getPost('default_value'),
        ]);

        return redirect()->to('/finance/discounts')->with('message', 'Discount type added.');
    }

    public function toggleActive($id)
    {
        $types = new DiscountTypeModel();
        $type  = $types->find($id);

        if ($type === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $types->update($id, ['is_active' => $type['is_active'] ? 0 : 1]);

        return redirect()->to('/finance/discounts')->with('message', $type['is_active'] ? 'Discount type deactivated.' : 'Discount type activated.');
    }

    /**
     * Search students by username/student number — backs the "grant a
     * discount" search box, same pattern as Registrar\DocumentController
     * ::search()/GradeModel::searchLockedGrades().
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

    public function studentGrants($studentId)
    {
        $student = (new StudentModel())->findDetailed((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('finance/discounts/student', [
            'pageTitle'     => 'Discounts — ' . $student['student_number'],
            'student'       => $student,
            'grants'        => (new StudentDiscountModel())->forStudent((int) $studentId),
            'discountTypes' => (new DiscountTypeModel())->active(),
        ] + FinancePortal::shell('discounts'));
    }

    public function grant($studentId)
    {
        $rules = [
            'discount_type_id' => 'required|integer',
            'academic_year'    => 'permit_empty|max_length[60]',
            'override_value'   => 'permit_empty|decimal|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $student = (new StudentModel())->find((int) $studentId);

        if ($student === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $academicYear   = $this->request->getPost('academic_year') ?: null;
        $overrideValue  = $this->request->getPost('override_value') !== '' ? (float) $this->request->getPost('override_value') : null;

        $result = (new StudentDiscountModel())->grant(
            (int) $studentId,
            (int) $this->request->getPost('discount_type_id'),
            $academicYear,
            $overrideValue,
            (int) auth()->id(),
            $this->request->getPost('remarks') ?: null
        );

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        // Apply immediately to the student's current assessment(s) rather
        // than waiting for their next enrollment change to trigger a
        // recompute.
        $this->recomputeActiveTerm((int) $studentId);

        return redirect()->to('/finance/discounts/student/' . $studentId)->with('message', 'Discount granted.');
    }

    public function revoke($id)
    {
        $grants = new StudentDiscountModel();
        $grant  = $grants->find($id);

        if ($grant === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $result = $grants->revoke((int) $id, (int) auth()->id());

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/finance/discounts/student/' . $grant['student_id'])->with('message', 'Discount revoked.');
    }

    private function recomputeActiveTerm(int $studentId): void
    {
        $activeYear = (new \App\Models\AcademicYearModel())->activeFormatted();

        if ($activeYear !== null) {
            (new TuitionAssessmentService())->recompute($studentId, $activeYear);
        }
    }
}
