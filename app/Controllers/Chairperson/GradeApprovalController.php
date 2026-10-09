<?php

namespace App\Controllers\Chairperson;

use App\Controllers\BaseController;
use App\Libraries\SchedulingAuthorization;
use App\Models\DepartmentModel;
use App\Models\GradeChangeRequestModel;
use App\Models\GradeModel;

/**
 * Chairperson-side grade locking + change-request approval — the "official"
 * step after faculty submits. Kept as its own controller for the same
 * reason Chairperson\OfferingController is: ChairpersonController is
 * already 2256 lines and flagged as high regression risk.
 */
class GradeApprovalController extends BaseController
{
    public function index()
    {
        // Every department this chairperson is authorized for (home +
        // delegated) — see SchedulingAuthorization::authorizedSubDeptIds().
        // subDeptOfUser() alone would silently drop a delegated
        // department's classes from this entire page.
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());

        if ($subDeptIds === []) {
            return view('chairperson/grade_approvals', [
                'pageTitle'      => 'Grade Approvals',
                'user'           => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
                'deptName'       => null,
                'classes'        => [],
                'lockedClasses'  => [],
                'changeRequests' => [],
            ]);
        }

        $deptModel = new DepartmentModel();
        $deptNames = array_column(
            db_connect()->table('sub_departments')->whereIn('sub_dept_id', $subDeptIds)->orderBy('name')->get()->getResultArray(),
            'name'
        );
        $programs = [];

        foreach ($subDeptIds as $id) {
            $programs = array_merge($programs, $deptModel->programsOfSubDept($id));
        }

        $programs   = array_values(array_unique($programs));
        $gradeModel = new GradeModel();

        return view('chairperson/grade_approvals', [
            'pageTitle'      => 'Grade Approvals',
            'user'           => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => (count($deptNames) === 1 ? $deptNames[0] . ' - ' : '') . 'Chairperson'],
            'deptName'       => count($deptNames) === 1 ? $deptNames[0] : implode(', ', $deptNames),
            'classes'        => $gradeModel->submittedClasses($programs),
            'lockedClasses'  => $gradeModel->lockedClasses($programs),
            'changeRequests' => (new GradeChangeRequestModel())->pendingForPrograms($programs),
        ]);
    }

    public function show()
    {
        $context = $this->contextFromPayload($this->request->getGet());

        if ($context === null || $this->ownProgram($context['program']) === null) {
            return redirect()->to('/chairperson/grade-approvals')->with('error', 'That class does not belong to your department.');
        }

        $result = (new GradeModel())->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);

        return view('chairperson/grade_approval_class', [
            'pageTitle' => 'Grade Approvals — ' . $context['subject_code'],
            'user'      => ['name' => auth()->user()->username ?? 'Chairperson', 'role' => 'Chairperson'],
            'context'   => $context,
            'section'   => $result['section'],
            'offering'  => $result['offering'],
            'roster'    => $result['roster'],
        ]);
    }

    public function lock()
    {
        $chairId = (int) auth()->id();
        $payload = $this->request->getJSON(true);
        $context = $this->contextFromPayload(is_array($payload) ? $payload : []);

        if ($context === null || $this->ownProgram($context['program']) === null) {
            return $this->fail('That class does not belong to your department.');
        }

        $gradeModel = new GradeModel();
        $result     = $gradeModel->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);

        if ($result['roster'] === []) {
            return $this->fail('This class has no enrolled students.');
        }

        $enrollmentIds = array_map(static fn (array $r) => (int) $r['enrollment_id'], $result['roster']);
        $locked        = $gradeModel->lockClass($enrollmentIds, $chairId);

        if (! $locked['ok']) {
            return $this->fail($locked['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    public function approveChange()
    {
        return $this->reviewChange('approve');
    }

    public function rejectChange()
    {
        return $this->reviewChange('reject');
    }

    private function reviewChange(string $action)
    {
        $chairId = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['request_id'])) {
            return $this->fail('Missing request.');
        }

        $requestId = (int) $payload['request_id'];
        $request   = (new GradeChangeRequestModel())->find($requestId);

        if ($request === null) {
            return $this->fail('Request not found.');
        }

        $grade = (new GradeModel())->find((int) $request['grade_id']);

        if ($grade === null || $this->ownSection((int) $grade['section_id']) === null) {
            return $this->fail('That request does not belong to your department.');
        }

        $notes = isset($payload['notes']) ? (string) $payload['notes'] : null;

        $result = $action === 'approve'
            ? (new GradeModel())->approveChangeRequest($requestId, $chairId, $notes)
            : (new GradeModel())->rejectChangeRequest($requestId, $chairId, $notes);

        if (! $result['ok']) {
            return $this->fail($result['error'] ?? 'Could not process the request.');
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * The section's program, only if it belongs to the logged-in
     * chairperson's own department — mirrors
     * Chairperson\OfferingController::ownSection().
     */
    private function ownSection(int $sectionId): ?array
    {
        $section = (new \App\Models\SectionModel())->find($sectionId);

        if ($section === null) {
            return null;
        }

        return $this->ownProgram($section['program']) !== null ? $section : null;
    }

    private function ownProgram(string $program): ?string
    {
        $subDeptIds = (new SchedulingAuthorization())->authorizedSubDeptIds((int) auth()->id());

        if ($subDeptIds === []) {
            return null;
        }

        $deptModel = new DepartmentModel();
        $programs  = [];

        foreach ($subDeptIds as $id) {
            $programs = array_merge($programs, $deptModel->programsOfSubDept($id));
        }

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
