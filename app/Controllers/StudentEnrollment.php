<?php

namespace App\Controllers;

use App\Models\AcademicYearModel;
use App\Models\EnrollmentModel;
use App\Models\SectionModel;

class StudentEnrollment extends BaseController
{
    /** Mirrors ChairpersonController::COLORS so blocks use the same accent per subject. */
    private const COLORS = ['blue', 'purple', 'cyan', 'red', 'green', 'yellow'];

    /**
     * Per-subject enrollment: the student names an exact section + subject
     * (e.g. an irregular student picking a specific class offering, possibly
     * outside their own cohort). Validated against real plotted-schedule
     * data inside EnrollmentModel — not blindly trusted.
     */
    public function enroll()
    {
        $userId  = (int) auth()->id();
        $payload = $this->request->getJSON(true);

        if (! is_array($payload)) {
            return $this->fail('Invalid payload.');
        }

        foreach (['academic_year', 'program', 'year_level', 'section', 'subject_code'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                return $this->fail("Missing field: {$key}");
            }
        }

        if (! $this->isActiveYear($payload['academic_year'])) {
            return $this->fail('This academic year is not active. Only the active term accepts new enrollments.');
        }

        if ((new AcademicYearModel())->isEnlistmentOpen()) {
            return $this->fail('Online enlistment is open — use the Enlistment wizard to build your schedule.');
        }

        $section = (new SectionModel())->forContext(
            $payload['academic_year'],
            $payload['program'],
            $payload['year_level'],
            $payload['section']
        );

        if ($section === null) {
            return $this->fail('That section does not exist.');
        }

        $result = (new EnrollmentModel())->enrollOne($userId, (int) $section['section_id'], $payload['subject_code'], 'manual');

        if (! $result['ok']) {
            return $this->fail($result['error']);
        }

        return $this->response->setJSON(['ok' => true, 'enrollment_id' => $result['enrollment_id']]);
    }

    /**
     * Block enrollment: enroll the student into every subject plotted for
     * THEIR OWN section. The cohort is never taken from the request — it's
     * resolved server-side from users.section_id (see
     * 2026-07-30-020000_AddSectionIdToUsers.php), the minimum student->cohort
     * link that exists today. A caller cannot block-enroll into an arbitrary
     * cohort by passing one in.
     */
    public function enrollBlock()
    {
        $userId = (int) auth()->id();

        $me = db_connect()->table('users')->select('section_id')->where('id', $userId)->get()->getRowArray();

        if (empty($me['section_id'])) {
            return $this->fail('Your account is not assigned to a section yet. Contact your administrator.');
        }

        $sections = new SectionModel();
        $section  = $sections->find((int) $me['section_id']);

        if ($section === null) {
            return $this->fail('Your assigned section no longer exists. Contact your administrator.');
        }

        if (! $this->isActiveYear($section['academic_year'])) {
            return $this->fail('This academic year is not active. Only the active term accepts new enrollments.');
        }

        if ((new AcademicYearModel())->isEnlistmentOpen()) {
            return $this->fail('Online enlistment is open — use the Enlistment wizard to build your schedule.');
        }

        $enrollments = new EnrollmentModel();
        $subjects    = $enrollments->subjectsFor($section);

        if ($subjects === []) {
            return $this->fail('No subjects are scheduled for your section yet.');
        }

        $enrolled = [];
        $failed   = [];

        foreach ($subjects as $subjectCode) {
            $result = $enrollments->enrollOne($userId, (int) $section['section_id'], $subjectCode, 'block');

            if ($result['ok']) {
                $enrolled[] = $subjectCode;
            } else {
                $failed[] = ['subject_code' => $subjectCode, 'error' => $result['error']];
            }
        }

        return $this->response->setJSON(['ok' => true, 'enrolled' => $enrolled, 'failed' => $failed]);
    }

    /**
     * Student drops one of their OWN enrollments. Only the active term can
     * be changed, and never one that already has a grade (enforced in
     * EnrollmentModel::drop). Plain form post + redirect, like the rest of
     * the portal's non-JSON actions.
     */
    public function drop()
    {
        $userId = (int) auth()->id();

        if (! $this->validate(['enrollment_id' => 'required|is_natural_no_zero', 'reason' => 'permit_empty|max_length[255]'])) {
            return redirect()->to('/student/schedule')->with('errors', ['Invalid request.']);
        }

        $enrollmentId = (int) $this->request->getPost('enrollment_id');
        $enrollments  = new EnrollmentModel();

        $mine = db_connect()->table('enrollments e')
            ->select('sec.academic_year, e.subject_code')
            ->join('sections sec', 'sec.section_id = e.section_id')
            ->where('e.enrollment_id', $enrollmentId)
            ->where('e.user_id', $userId)
            ->get()
            ->getRowArray();

        if ($mine === null) {
            return redirect()->to('/student/schedule')->with('errors', ['Enrollment not found.']);
        }

        if (! $this->isActiveYear($mine['academic_year'])) {
            return redirect()->to('/student/schedule')->with('errors', ['Only subjects in the active term can be dropped.']);
        }

        $reason = trim((string) $this->request->getPost('reason')) ?: 'Dropped by student';
        $result = $enrollments->drop($enrollmentId, $reason, $userId, $userId);

        if (! $result['ok']) {
            return redirect()->to('/student/schedule')->with('errors', [$result['error']]);
        }

        return redirect()->to('/student/schedule')->with('message', "{$mine['subject_code']} dropped.");
    }

    /**
     * The student's own schedule: every meeting for every subject they're
     * actively enrolled in.
     */
    public function schedule()
    {
        $userId = (int) auth()->id();

        $rows = db_connect()->table('enrollments e')
            ->select('e.subject_code, s.academic_year, s.program, s.year_level, s.section')
            ->join('sections s', 's.section_id = e.section_id')
            ->where('e.user_id', $userId)
            ->where('e.status', 'enrolled')
            ->orderBy('s.academic_year', 'DESC')
            ->get()
            ->getResultArray();

        $meetings = [];

        foreach ($rows as $row) {
            $classMeetings = db_connect()->table('plotted_schedules')
                ->select('day, start_time, end_time, time_label, subject_title, room, prof, theme')
                ->where('academic_year', $row['academic_year'])
                ->where('program', $row['program'])
                ->where('year_level', $row['year_level'])
                ->where('section', $row['section'])
                ->where('subject_code', $row['subject_code'])
                ->orderBy('day')
                ->get()
                ->getResultArray();

            foreach ($classMeetings as $meeting) {
                $meetings[] = array_merge($meeting, [
                    'subject_code'  => $row['subject_code'],
                    'academic_year' => $row['academic_year'],
                    'program'       => $row['program'],
                    'year_level'    => $row['year_level'],
                    'section'       => $row['section'],
                ]);
            }
        }

        $blocks = array_map(function (array $m): array {
            return [
                'day'        => (int) $m['day'],
                'code'       => $m['subject_code'],
                'title'      => $m['subject_title'],
                'room'       => $m['room'],
                'section'    => $m['section'],
                'year_level' => $m['year_level'],
                'time_label' => $m['time_label'],
                'start'      => substr($m['start_time'], 0, 5),
                'end'        => substr($m['end_time'], 0, 5),
                'color'      => self::COLORS[array_sum(array_map('ord', str_split($m['subject_code']))) % count(self::COLORS)],
            ];
        }, $meetings);

        $activeYear = (new AcademicYearModel())->activeFormatted();

        // "My Enrolled Subjects": droppable only in the active term and
        // while no grade exists for it.
        $enrolled = array_map(static function (array $e) use ($activeYear): array {
            $e['can_drop'] = $e['academic_year'] === $activeYear && (int) $e['grade_count'] === 0;

            return $e;
        }, (new EnrollmentModel())->activeForStudent($userId));

        return view('student/schedule', [
            'pageTitle' => 'My Schedule',
            'meetings'  => $meetings,
            'blocks'    => $blocks,
            'enrolled'  => $enrolled,
        ]);
    }

    private function isActiveYear(string $academicYear): bool
    {
        return $academicYear === (new AcademicYearModel())->activeFormatted();
    }

    private function fail(string $message)
    {
        return $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $message]);
    }
}
