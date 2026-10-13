<?php

namespace App\Controllers\Dean;

use App\Controllers\BaseController;
use App\Libraries\DeanPortal;
use App\Libraries\SchedulingAuthorization;
use App\Models\GradeModel;

/**
 * Dean-side grade noting — the second stage after the chairperson's
 * recommendation. Mirrors Chairperson\GradeApprovalController's shape
 * exactly, scoped by college (authorizedDepartmentIds()) instead of
 * sub-department (authorizedSubDeptIds()).
 */
class GradeApprovalController extends BaseController
{
    public function index()
    {
        $collegeIds = (new SchedulingAuthorization())->authorizedDepartmentIds((int) auth()->id());

        if ($collegeIds === []) {
            return view('dean/grade_approvals', [
                'pageTitle'    => 'Grade Approvals',
                'collegeName'  => null,
                'classes'      => [],
                'notedClasses' => [],
            ] + DeanPortal::shell('grade-approvals'));
        }

        $collegeNames = array_column(
            db_connect()->table('departments')->whereIn('dept_id', $collegeIds)->orderBy('name')->get()->getResultArray(),
            'name'
        );

        $programs   = DeanController::programsOfColleges($collegeIds);
        $gradeModel = new GradeModel();

        return view('dean/grade_approvals', [
            'pageTitle'    => 'Grade Approvals',
            'collegeName'  => count($collegeNames) === 1 ? $collegeNames[0] : implode(', ', $collegeNames),
            'classes'      => $gradeModel->recommendedForDean($programs),
            'notedClasses' => $gradeModel->notedClasses($programs),
        ] + DeanPortal::shell('grade-approvals'));
    }

    public function show()
    {
        $context = $this->contextFromPayload($this->request->getGet());

        if ($context === null || $this->ownProgram($context['program']) === null) {
            return redirect()->to('/dean/grade-approvals')->with('error', 'That class does not belong to your college.');
        }

        $result = (new GradeModel())->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);

        return view('dean/grade_approval_class', [
            'pageTitle' => 'Grade Approvals — ' . $context['subject_code'],
            'context'   => $context,
            'section'   => $result['section'],
            'offering'  => $result['offering'],
            'roster'    => $result['roster'],
        ] + DeanPortal::shell('grade-approvals'));
    }

    public function note()
    {
        $deanId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);
        $context = $this->contextFromPayload(is_array($payload) ? $payload : []);

        if ($context === null || $this->ownProgram($context['program']) === null) {
            return $this->fail('That class does not belong to your college.');
        }

        $gradeModel = new GradeModel();
        $result     = $gradeModel->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);

        if ($result['roster'] === []) {
            return $this->fail('This class has no enrolled students.');
        }

        $comments      = isset($payload['comments']) ? (string) $payload['comments'] : null;
        $enrollmentIds = array_map(static fn (array $r) => (int) $r['enrollment_id'], $result['roster']);
        $noted         = $gradeModel->noteClass($enrollmentIds, $deanId, $comments);

        if (! $noted['ok']) {
            return $this->fail($noted['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    private function ownProgram(string $program): ?string
    {
        $collegeIds = (new SchedulingAuthorization())->authorizedDepartmentIds((int) auth()->id());

        if ($collegeIds === []) {
            return null;
        }

        $programs = DeanController::programsOfColleges($collegeIds);

        return in_array($program, $programs, true) ? $program : null;
    }

    private function contextFromPayload(array $payload): ?array
    {
        $keys    = ['academic_year', 'program', 'year_level', 'section', 'subject_code'];
        $context = [];

        foreach ($keys as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                return null;
            }

            $context[$key] = $payload[$key];
        }

        return $context;
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}
