<?php

namespace App\Controllers\Faculty;

use App\Controllers\BaseController;
use App\Models\FacultyAssignmentModel;
use App\Models\GradeChangeRequestModel;
use App\Models\GradeModel;

/**
 * Faculty-side grade entry. One "class" here is exactly one
 * faculty_assignments row (academic_year+program+year_level+section+subject_code)
 * — the same identity TeachingLoads uses for a claimed load. The roster
 * behind it may be a single section or a merged offering; GradeModel hides
 * that distinction from this controller.
 */
class GradingController extends BaseController
{
    public function index()
    {
        $facultyId   = (int) auth()->id();
        $assignments = (new FacultyAssignmentModel())
            ->where('user_id', $facultyId)
            ->orderBy('academic_year', 'DESC')
            ->findAll();

        $gradeModel = new GradeModel();
        $classes    = [];

        foreach ($assignments as $a) {
            $result = $gradeModel->rosterWithGrades($a['academic_year'], $a['program'], $a['year_level'], $a['section'], $a['subject_code']);
            $roster = $result['roster'];

            $graded = 0;
            $counts = ['submitted' => 0, 'chair_recommended' => 0, 'dean_noted' => 0, 'registrar_approved' => 0, 'locked' => 0];

            foreach ($roster as $row) {
                if ($row['grade'] === null) {
                    continue;
                }

                $graded++;
                $status = $row['grade']['status'];

                if (isset($counts[$status])) {
                    $counts[$status]++;
                }
            }

            $rosterCount = count($roster);
            // "In review" covers every stage past submitted but short of
            // locked — the faculty view only needs to know the grade has
            // left their hands, not exactly which office currently has it
            // (the chairperson/dean/registrar queues show that detail).
            $inReview = $counts['chair_recommended'] + $counts['dean_noted'] + $counts['registrar_approved'];

            $classes[] = [
                'assignment'    => $a,
                'student_count' => $rosterCount,
                'graded_count'  => $graded,
                'submitted'     => $counts['submitted'] === $rosterCount && $rosterCount > 0,
                'in_review'     => $inReview === $rosterCount && $rosterCount > 0,
                'locked'        => $counts['locked'] === $rosterCount && $rosterCount > 0,
            ];
        }

        return view('faculty/grading/index', [
            'pageTitle' => 'Grading',
            'user'      => ['name' => auth()->user()->username ?? 'Faculty'],
            'classes'   => $classes,
        ]);
    }

    public function show()
    {
        $facultyId = (int) auth()->id();
        $context   = $this->requireContext();

        if ($context === null) {
            return redirect()->to('/faculty/grading')->with('error', 'Missing class context.');
        }

        $owns = (new FacultyAssignmentModel())->where(array_merge($context, ['user_id' => $facultyId]))->first();

        if ($owns === null) {
            return redirect()->to('/faculty/grading')->with('error', 'You are not assigned to that class.');
        }

        $result = (new GradeModel())->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);

        return view('faculty/grading/class', [
            'pageTitle' => 'Grading — ' . $context['subject_code'],
            'user'      => ['name' => auth()->user()->username ?? 'Faculty'],
            'context'   => $context,
            'section'   => $result['section'],
            'offering'  => $result['offering'],
            'roster'    => $result['roster'],
        ]);
    }

    public function saveDraft()
    {
        $facultyId = (int) auth()->id();
        $payload   = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['grades']) || ! is_array($payload['grades'])) {
            return $this->fail('No grades provided.');
        }

        $context = $this->contextFromPayload($payload);

        if ($context === null) {
            return $this->fail('Missing class context.');
        }

        $owns = (new FacultyAssignmentModel())->where(array_merge($context, ['user_id' => $facultyId]))->first();

        if ($owns === null) {
            return $this->fail('You are not assigned to that class.');
        }

        $result     = (new GradeModel())->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);
        $rosterById = [];

        foreach ($result['roster'] as $row) {
            $rosterById[(int) $row['enrollment_id']] = $row;
        }

        $gradeModel = new GradeModel();
        $errors     = [];

        foreach ($payload['grades'] as $entry) {
            $enrollmentId = (int) ($entry['enrollment_id'] ?? 0);

            if (! isset($rosterById[$enrollmentId])) {
                $errors[] = "Student (enrollment #{$enrollmentId}) is not on this class's roster.";
                continue;
            }

            $value   = (string) ($entry['value'] ?? '');
            $remarks = isset($entry['remarks']) ? (string) $entry['remarks'] : null;

            if ($value === '') {
                continue;
            }

            $rowContext = [
                'user_id'       => $rosterById[$enrollmentId]['user_id'],
                'subject_code'  => $context['subject_code'],
                'section_id'    => $result['section']['section_id'],
                'offering_id'   => $result['offering']['offering_id'] ?? null,
                'academic_year' => $context['academic_year'],
            ];

            $saved = $gradeModel->saveOne($rowContext, $enrollmentId, $value, $remarks, $facultyId, auth()->user()->can('calendar.override'));

            if (! $saved['ok']) {
                $errors[] = "{$rosterById[$enrollmentId]['username']}: {$saved['error']}";
            }
        }

        return $this->response->setJSON(['ok' => $errors === [], 'errors' => $errors]);
    }

    public function submit()
    {
        $facultyId = (int) auth()->id();
        $payload   = $this->request->getJSON(true);
        $context   = $this->contextFromPayload(is_array($payload) ? $payload : []);

        if ($context === null) {
            return $this->fail('Missing class context.');
        }

        $owns = (new FacultyAssignmentModel())->where(array_merge($context, ['user_id' => $facultyId]))->first();

        if ($owns === null) {
            return $this->fail('You are not assigned to that class.');
        }

        $gradeModel = new GradeModel();
        $result     = $gradeModel->rosterWithGrades($context['academic_year'], $context['program'], $context['year_level'], $context['section'], $context['subject_code']);

        if ($result['roster'] === []) {
            return $this->fail('This class has no enrolled students yet.');
        }

        $enrollmentIds = array_map(static fn (array $r) => (int) $r['enrollment_id'], $result['roster']);
        $submitted     = $gradeModel->submitClass($enrollmentIds, $facultyId, auth()->user()->can('calendar.override'));

        if (! $submitted['ok']) {
            return $this->fail($submitted['error']);
        }

        return $this->response->setJSON(['ok' => true]);
    }

    /**
     * Queue of correction forms the registrar has issued against this
     * faculty member's locked grades, awaiting their response — step 2
     * (FACULTY_SUBMITTED) of the 5-step chain.
     */
    public function changeRequests()
    {
        $facultyId = (int) auth()->id();

        return view('faculty/grading/change_requests', [
            'pageTitle' => 'Grade Correction Requests',
            'user'      => ['name' => auth()->user()->username ?? 'Faculty'],
            'requests'  => (new GradeChangeRequestModel())->awaitingFacultyResponse($facultyId),
        ]);
    }

    /**
     * Faculty's response to an issued correction form — new value, reason,
     * and optional supporting documents.
     */
    public function submitChangeResponse()
    {
        $facultyId = (int) auth()->id();
        $requestId = (int) $this->request->getPost('request_id');
        $value     = (string) $this->request->getPost('requested_value');
        $reason    = (string) $this->request->getPost('reason');

        if ($requestId === 0 || trim($value) === '' || trim($reason) === '') {
            return redirect()->back()->with('errors', ['New value and reason are both required.']);
        }

        $files = $this->request->getFileMultiple('attachments') ?? [];

        $result = (new GradeChangeRequestModel())->submitFacultyResponse($requestId, $value, $reason, $facultyId, $files);

        if (! $result['ok']) {
            return redirect()->back()->with('errors', [$result['error']]);
        }

        return redirect()->to('/faculty/grading/change-requests')->with('message', 'Response submitted to the chairperson.');
    }

    public function requestChange()
    {
        $facultyId = (int) auth()->id();
        $payload   = $this->request->getJSON(true);

        if (! is_array($payload) || empty($payload['grade_id']) || empty($payload['requested_value']) || empty($payload['reason'])) {
            return $this->fail('Grade, requested value, and reason are all required.');
        }

        $result = (new GradeModel())->requestChange(
            (int) $payload['grade_id'],
            (string) $payload['requested_value'],
            (string) $payload['reason'],
            $facultyId
        );

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true, 'request_id' => $result['request_id']]);
    }

    /**
     * @return array{academic_year:string,program:string,year_level:string,section:string,subject_code:string}|null
     */
    private function requireContext(): ?array
    {
        $get = $this->request->getGet();

        return $this->contextFromPayload($get);
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
