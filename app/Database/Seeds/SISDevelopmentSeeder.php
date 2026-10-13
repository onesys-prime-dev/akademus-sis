<?php

namespace App\Database\Seeds;

use App\Libraries\GradeScaleEvaluator;
use App\Models\AcademicYearModel;
use App\Models\CourseOfferingModel;
use App\Models\EnrollmentModel;
use App\Models\FacultyAssignmentModel;
use App\Models\GradeModel;
use App\Models\GradingScaleModel;
use App\Models\PlottedScheduleModel;
use App\Models\SectionModel;
use App\Models\StudentModel;
use CodeIgniter\Database\Seeder;

/**
 * DEV-ONLY. Populates a real, testable student population by driving the
 * exact same production code paths a real Admission handoff / chairperson /
 * student would use — never raw inserts that bypass validation:
 *
 *   - Students are created via StudentModel::promoteFromApplicant(), the
 *     same method API\StudentAPIController::promote() calls.
 *   - Section assignment mirrors ChairpersonController::assignStudent()
 *     (there is no separate reusable service to call outside a chairperson
 *     HTTP request, so the same raw `users.section_id` update is used here).
 *   - Enrollment goes through EnrollmentModel::enrollOne() — the same
 *     transactional, conflict/capacity-checked path StudentEnrollment uses.
 *
 * Deliberately scoped to BSIT / the IT sub-department only: as of this
 * writing, BSIT is the only program with `programs.sub_department_id` set
 * (see DepartmentModel::subDeptOfUser / programsOfSubDept) — BSCS and BSHM
 * exist but aren't linked to any sub-department yet. That is a real
 * admin-configuration gap (see the "Curriculum" module in the SIS
 * development roadmap), not something this seeder should silently paper
 * over by inventing a link that doesn't exist in a real environment.
 *
 * Safe to run repeatedly: every write path here is idempotent.
 *
 *   php spark db:seed SISDevelopmentSeeder
 */
class SISDevelopmentSeeder extends Seeder
{
    private const PROGRAM_CODE  = 'BSIT';
    private const PROGRAM_LABEL = 'BS in Information Technology'; // sections/plotted_schedules store this display name, not the code — see DepartmentModel::LEGACY_PROGRAM_NAMES

    public function run()
    {
        $academicYear = (new AcademicYearModel())->activeFormatted();

        if ($academicYear === null) {
            echo "ABORTED: no academic year is marked active (academic_years.is_active). Set one active in /admin/academic-years first — every downstream table (sections, enrollments, plotted_schedules) is scoped by it.\n";

            return;
        }

        echo "Using active academic year: {$academicYear}\n";

        $sections = $this->ensureSections($academicYear);
        $this->ensurePlotting($academicYear, $sections);
        $this->seedStudents($sections);
        $this->mergeSharedOffering($academicYear, $sections);
        $this->seedGrading($academicYear, $sections);

        echo "\nDone. See docs/sis-development-roadmap/index.html -> Test Scenarios for what to verify next.\n";
    }

    /**
     * Sections used by this scenario. AIT1/AIT2 (1st Year) and AIT1 (2nd
     * Year) reuse whatever already exists for the active term (created via
     * the real chairperson UI in this dev DB) rather than duplicating them.
     * CAPTEST is new and deliberately undersized (capacity 3) to produce a
     * real "section full" rejection — see Test Scenario 4 in the roadmap.
     */
    private function ensureSections(string $academicYear): array
    {
        $sectionModel = new SectionModel();

        $wanted = [
            'ait1_1y'   => ['year_level' => '1st Year', 'section' => 'AIT1',    'capacity' => null],
            'ait2_1y'   => ['year_level' => '1st Year', 'section' => 'AIT2',    'capacity' => null],
            'ait1_2y'   => ['year_level' => '2nd Year', 'section' => 'AIT1',    'capacity' => null],
            'captest'   => ['year_level' => '1st Year', 'section' => 'CAPTEST', 'capacity' => 3],
        ];

        $result = [];

        foreach ($wanted as $key => $spec) {
            $existing = $sectionModel->forContext($academicYear, self::PROGRAM_LABEL, $spec['year_level'], $spec['section']);

            if ($existing !== null) {
                echo "Section {$spec['section']} ({$spec['year_level']}) already exists — reusing.\n";
                $result[$key] = $existing;
                continue;
            }

            $sectionModel->insert([
                'academic_year' => $academicYear,
                'program'       => self::PROGRAM_LABEL,
                'year_level'    => $spec['year_level'],
                'section'       => $spec['section'],
                'capacity'      => $spec['capacity'],
            ]);

            $result[$key] = $sectionModel->find($sectionModel->getInsertID());
            echo "Created section {$spec['section']} ({$spec['year_level']}" . ($spec['capacity'] !== null ? ", capacity {$spec['capacity']}" : '') . ").\n";
        }

        return $result;
    }

    /**
     * Which subjects each section has plotted, and which subject is
     * deliberately shared across two different sections (ITE 110) — the
     * setup for the Section Merging test scenario in the roadmap: once
     * merging is built, AIT1/1st Year and AIT2/1st Year's ITE 110 rosters
     * are exactly the two that should combine into one class.
     */
    private function plottingPlan(): array
    {
        return [
            'ait1_1y' => [
                ['subject_code' => 'ITE 102', 'subject_title' => 'Computer Programming 1', 'day' => 1, 'start' => '08:30:00', 'end' => '10:00:00', 'room' => 'Room 203'],
                ['subject_code' => 'ITE 110', 'subject_title' => 'ITE Elective 1',          'day' => 2, 'start' => '10:00:00', 'end' => '11:30:00', 'room' => 'Room 204'],
            ],
            'ait2_1y' => [
                ['subject_code' => 'ITE 101', 'subject_title' => 'Introduction to Computing', 'day' => 0, 'start' => '10:00:00', 'end' => '11:30:00', 'room' => 'Room 205'],
                ['subject_code' => 'ITE 110', 'subject_title' => 'ITE Elective 1',             'day' => 3, 'start' => '10:00:00', 'end' => '11:30:00', 'room' => 'Room 204'],
            ],
            'ait1_2y' => [
                ['subject_code' => 'ITE 103',   'subject_title' => 'Computer Programming 2',     'day' => 4, 'start' => '08:30:00', 'end' => '10:00:00', 'room' => 'Room 206'],
                ['subject_code' => 'ITE 104.1', 'subject_title' => 'Architecture and Organization', 'day' => 5, 'start' => '08:30:00', 'end' => '10:00:00', 'room' => 'Room 207'],
            ],
            'captest' => [
                ['subject_code' => 'ITE 108.1', 'subject_title' => 'Fundamentals of Database Systems', 'day' => 1, 'start' => '13:00:00', 'end' => '14:30:00', 'room' => 'Room 208'],
            ],
        ];
    }

    private function ensurePlotting(string $academicYear, array $sections): void
    {
        $plotted = new PlottedScheduleModel();

        foreach ($this->plottingPlan() as $key => $meetings) {
            $section = $sections[$key];

            foreach ($meetings as $m) {
                $exists = $plotted->where([
                    'academic_year' => $academicYear,
                    'program'       => self::PROGRAM_LABEL,
                    'year_level'    => $section['year_level'],
                    'section'       => $section['section'],
                    'subject_code'  => $m['subject_code'],
                ])->countAllResults() > 0;

                if ($exists) {
                    echo "Plotting for {$section['section']}/{$m['subject_code']} already exists — skipping.\n";
                    continue;
                }

                $plotted->insert([
                    'academic_year' => $academicYear,
                    'program'       => self::PROGRAM_LABEL,
                    'year_level'    => $section['year_level'],
                    'section'       => $section['section'],
                    'day'           => $m['day'],
                    'start_time'    => $m['start'],
                    'end_time'      => $m['end'],
                    'time_label'    => date('g:i A', strtotime($m['start'])) . ' - ' . date('g:i A', strtotime($m['end'])),
                    'subject_code'  => $m['subject_code'],
                    'subject_title' => $m['subject_title'],
                    'prof'          => 'TBA (seeded)',
                    'room'          => $m['room'],
                    'theme'         => 'slate',
                    'status'        => 'published',
                ]);

                echo "Plotted {$m['subject_code']} for {$section['section']} ({$section['year_level']}).\n";
            }
        }
    }

    /**
     * Creates students, assigns them to a section (mirroring
     * ChairpersonController::assignStudent), then block-enrolls them into
     * every subject plotted for that section via EnrollmentModel::enrollOne
     * — the real conflict/capacity-checked path.
     */
    private function seedStudents(array $sections): void
    {
        $studentModel    = new StudentModel();
        $enrollmentModel = new EnrollmentModel();
        $plan            = $this->plottingPlan();

        $cohorts = [
            'ait1_1y' => 6,
            'ait2_1y' => 5,
            'ait1_2y' => 4,
            'captest' => 4, // capacity is 3 -> the 4th is EXPECTED to be rejected
        ];

        $seq = 1;

        foreach ($cohorts as $key => $count) {
            $section = $sections[$key];

            for ($i = 1; $i <= $count; $i++, $seq++) {
                $applicantId = sprintf('DEV-APP-%05d', $seq);

                $result = $studentModel->promoteFromApplicant([
                    'applicant_id'    => $applicantId,
                    'first_name'      => 'DevStudent',
                    'last_name'       => sprintf('%s%02d', strtoupper($key), $i),
                    'email'           => sprintf('devstudent.%s@example.test', $applicantId),
                    'program_code'    => self::PROGRAM_CODE,
                    'contact_number'  => '09170000000',
                ]);

                if (! $result['ok']) {
                    echo "  FAILED to promote {$applicantId}: {$result['error']}\n";
                    continue;
                }

                $userId = (int) $result['student']['user_id'];

                // Section assignment — same write ChairpersonController::
                // assignStudent performs after validating chairperson
                // ownership (already implicitly true here: this seeder only
                // targets the one sub-department-scoped program, BSIT).
                db_connect()->table('users')->where('id', $userId)->update(['section_id' => (int) $section['section_id']]);

                echo "Student {$result['student']['student_number']} assigned to {$section['section']} ({$section['year_level']}).\n";

                foreach ($plan[$key] as $meeting) {
                    $enroll = $enrollmentModel->enrollOne($userId, (int) $section['section_id'], $meeting['subject_code'], 'block');

                    if ($enroll['ok']) {
                        echo "    enrolled in {$meeting['subject_code']}\n";
                    } elseif (str_contains($enroll['error'], 'full')) {
                        // Expected for CAPTEST's 4th student — demonstrates
                        // the real capacity gate, not a seeder bug.
                        echo "    EXPECTED rejection ({$meeting['subject_code']}): {$enroll['error']}\n";
                    } elseif (str_contains($enroll['error'], 'Already enrolled')) {
                        echo "    already enrolled in {$meeting['subject_code']} — skipping\n";
                    } else {
                        echo "    UNEXPECTED enrollment failure ({$meeting['subject_code']}): {$enroll['error']}\n";
                    }
                }
            }
        }
    }

    /**
     * The Section Merging feature (course_offerings/offering_sections) has
     * shipped code but zero real rows — plottingPlan() deliberately put
     * ITE 110 on both ait1_1y and ait2_1y for exactly this: merge those two
     * sections' ITE 110 offerings into one class, via the same
     * CourseOfferingModel::createMerge() the chairperson UI calls, so the
     * feature finally has a real merged roster to test against.
     */
    private function mergeSharedOffering(string $academicYear, array $sections): void
    {
        $offeringModel = new CourseOfferingModel();
        $subjectCode   = 'ITE 110';
        $sectionIds    = [(int) $sections['ait1_1y']['section_id'], (int) $sections['ait2_1y']['section_id']];

        if ($offeringModel->offeringForSection($sectionIds[0], $subjectCode) !== null) {
            echo "ITE 110 offering already merged — reusing.\n";

            return;
        }

        $result = $offeringModel->createMerge($academicYear, $subjectCode, $sectionIds, null);

        if ($result['ok']) {
            echo "Merged ITE 110 across AIT1/AIT2 (1st Year) into offering #{$result['offering_id']} — roster count {$result['roster_count']}.\n";
        } else {
            echo "FAILED to merge ITE 110: {$result['error']}\n";
        }
    }

    /**
     * Grading has shipped code (draft -> submitted -> locked -> change
     * request) but zero real rows — this drives the exact same model
     * methods the Faculty/Chairperson grading controllers call, on two
     * real classes: an ordinary single-section class (ITE 102) taken all
     * the way to locked plus one change request, and the merged ITE 110
     * offering (left at "submitted") so both plain and merge-aware
     * rostering are exercised.
     */
    private function seedGrading(string $academicYear, array $sections): void
    {
        $db = db_connect();

        // Same IT sub-department this whole seeder is scoped to (see class
        // docblock) — resolved by code rather than hardcoding an id, so
        // this still works if the dev DB's ids differ.
        $subDept = $db->table('sub_departments')->where('code', 'IT')->get()->getRowArray();

        $faculty = $subDept === null ? null : $db->table('users u')
            ->select('u.id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'faculty')
            ->where('u.sub_department_id', $subDept['sub_dept_id'])
            ->orderBy('u.id', 'ASC')
            ->get()
            ->getRowArray();

        $chairperson = $subDept === null ? null : $db->table('users u')
            ->select('u.id')
            ->join('auth_groups_users g', 'g.user_id = u.id')
            ->where('g.group', 'chairperson')
            ->where('u.sub_department_id', $subDept['sub_dept_id'])
            ->orderBy('u.id', 'ASC')
            ->get()
            ->getRowArray();

        if ($faculty === null || $chairperson === null) {
            echo "SKIPPED grading: no IT-department-scoped faculty/chairperson account found to grade as.\n";

            return;
        }

        $facultyId     = (int) $faculty['id'];
        $chairpersonId = (int) $chairperson['id'];

        $assignmentModel = new FacultyAssignmentModel();
        $gradeModel      = new GradeModel();

        // ITE 102 — an ordinary, unmerged class. Taken all the way to
        // locked, plus one change request, so grade_change_requests also
        // finally has a real row.
        $this->gradeClass($academicYear, $sections['ait1_1y'], 'ITE 102', $facultyId, $chairpersonId, $assignmentModel, $gradeModel, lock: true, withChangeRequest: true);

        // The merged ITE 110 offering — left at "submitted" so a locked,
        // a submitted, and (implicitly, everything else in the DB still
        // ungraded) draft/no-grade state are all represented for real.
        $this->gradeClass($academicYear, $sections['ait1_1y'], 'ITE 110', $facultyId, $chairpersonId, $assignmentModel, $gradeModel, lock: false, withChangeRequest: false);
    }

    /**
     * A spread of grade values valid for whichever grading_scales row is
     * currently active — mostly passing, one deliberately failing — so
     * this seeder never hardcodes an assumption about which scale type
     * (numeric_gwa / percentage / letter) an admin has active. Mirrors
     * GradeScaleEvaluator's own type branching exactly.
     *
     * @return list<string>
     */
    private function sampleGradeValues(): array
    {
        $scale = (new GradingScaleModel())->activeScale();

        if ($scale === null) {
            // No scale configured at all — saveOne() will reject every one
            // of these too (same as a real submission would), which is the
            // correct, honest failure mode rather than guessing a scale.
            return ['1.50', '1.75', '2.00', '2.25', '2.50', '3.00', '5.00'];
        }

        if ($scale['type'] === 'letter') {
            $options = GradeScaleEvaluator::letterOptions($scale);
            $failing = GradeScaleEvaluator::failingLetters($scale);
            $passing = array_values(array_diff($options, $failing));

            if ($passing === []) {
                return $options !== [] ? $options : ['A'];
            }

            $spread = [];
            for ($i = 0; $i < 6; $i++) {
                $spread[] = $passing[$i % count($passing)];
            }

            return $failing === [] ? $spread : array_merge($spread, [$failing[0]]);
        }

        $min     = $scale['min_value'] !== null ? (float) $scale['min_value'] : 0.0;
        $max     = $scale['max_value'] !== null ? (float) $scale['max_value'] : 100.0;
        $passing = $scale['passing_value'] !== null ? (float) $scale['passing_value'] : ($min + $max) / 2;
        $isLower = (bool) $scale['is_lower_better'];

        // Passing spread runs from the threshold toward whichever bound is
        // "better" under this scale (min when lower-is-better, else max).
        $bestBound = $isLower ? $min : $max;
        $values    = [];

        for ($i = 0; $i < 6; $i++) {
            $values[] = $this->formatScaleValue($passing + ($bestBound - $passing) * ($i / 5), $scale);
        }

        // One deliberately failing value, just past the threshold on the
        // other side, clamped to stay within the scale's own bounds.
        $margin = max(1.0, abs($passing - ($isLower ? $max : $min)) * 0.3);
        $failingValue = $isLower
            ? min($max, $passing + $margin)
            : max($min, $passing - $margin);

        $values[] = $this->formatScaleValue($failingValue, $scale);

        return $values;
    }

    private function formatScaleValue(float $value, array $scale): string
    {
        // GWA-style scales conventionally read to 2 decimals (1.00-5.00);
        // percentage/other numeric scales read more naturally as whole
        // numbers — matches how each type is actually entered in the UI.
        $decimals = $scale['type'] === 'numeric_gwa' ? 2 : 0;

        return number_format($value, $decimals, '.', '');
    }

    private function gradeClass(string $academicYear, array $section, string $subjectCode, int $facultyId, int $chairpersonId, FacultyAssignmentModel $assignmentModel, GradeModel $gradeModel, bool $lock, bool $withChangeRequest): void
    {
        $assignmentModel->upsertAssignment($academicYear, self::PROGRAM_LABEL, $section['year_level'], $section['section'], $subjectCode, $facultyId);

        $result = $gradeModel->rosterWithGrades($academicYear, self::PROGRAM_LABEL, $section['year_level'], $section['section'], $subjectCode);
        $roster = $result['roster'];

        if ($roster === []) {
            echo "No roster for {$subjectCode}/{$section['section']} — nothing to grade.\n";

            return;
        }

        // A spread of realistic values — mostly-passing with one failing —
        // generated against whichever grading scale is CURRENTLY active,
        // never a hardcoded GWA assumption. An admin can (and did, on this
        // dev DB) switch the active scale to percentage/letter at any time;
        // hardcoded GWA values would then fail GradeScaleEvaluator's
        // validation against that scale.
        $values = $this->sampleGradeValues();
        $enrollmentIds = [];
        $i = 0;

        foreach ($roster as $row) {
            if ($row['grade'] !== null && $row['grade']['status'] !== 'draft') {
                // Already submitted/locked from a previous run — idempotent, leave it.
                $enrollmentIds[] = (int) $row['enrollment_id'];
                continue;
            }

            $context = [
                'user_id'       => $row['user_id'],
                'subject_code'  => $subjectCode,
                'section_id'    => (int) $section['section_id'],
                'offering_id'   => $result['offering']['offering_id'] ?? null,
                'academic_year' => $academicYear,
            ];

            $value = $values[$i % count($values)];
            $i++;

            $save = $gradeModel->saveOne($context, (int) $row['enrollment_id'], $value, null, $facultyId);

            if ($save['ok']) {
                $enrollmentIds[] = (int) $row['enrollment_id'];
                echo "  graded {$row['username']} ({$subjectCode}): {$value}\n";
            } else {
                echo "  FAILED to grade {$row['username']} ({$subjectCode}): {$save['error']}\n";
            }
        }

        if ($enrollmentIds === []) {
            return;
        }

        $submit = $gradeModel->submitClass($enrollmentIds, $facultyId);

        if (! $submit['ok']) {
            echo "  submitClass failed for {$subjectCode}: {$submit['error']}\n";

            return;
        }

        echo "  submitted {$subjectCode} class (" . count($enrollmentIds) . " grades).\n";

        if (! $lock) {
            return;
        }

        // Dev seed only: drives the class through the full chair -> dean ->
        // registrar chain using the one seeded chairperson account as a
        // stand-in for all three stages (no dean/registrar accounts are
        // seeded) — real actor roles don't matter here, only that the
        // resulting grade rows end up status='locked' like before.
        $recommend = $gradeModel->recommendClass($enrollmentIds, $chairpersonId);

        if (! $recommend['ok']) {
            echo "  recommendClass failed for {$subjectCode}: {$recommend['error']}\n";

            return;
        }

        $note = $gradeModel->noteClass($enrollmentIds, $chairpersonId);

        if (! $note['ok']) {
            echo "  noteClass failed for {$subjectCode}: {$note['error']}\n";

            return;
        }

        $approve = $gradeModel->approveClassForRegistrar($enrollmentIds, $chairpersonId);

        if (! $approve['ok']) {
            echo "  approveClassForRegistrar failed for {$subjectCode}: {$approve['error']}\n";

            return;
        }

        $doLock = $gradeModel->lockClass($enrollmentIds, $chairpersonId);

        if (! $doLock['ok']) {
            echo "  lockClass failed for {$subjectCode}: {$doLock['error']}\n";

            return;
        }

        echo "  locked {$subjectCode} class.\n";

        if (! $withChangeRequest) {
            return;
        }

        $firstGrade = $gradeModel->where('enrollment_id', $enrollmentIds[0])->first();

        if ($firstGrade === null || $firstGrade['status'] !== 'locked') {
            return;
        }

        // Any scale-valid value different from the current one — not a
        // hardcoded GWA assumption, same reasoning as sampleGradeValues().
        $candidates = array_values(array_filter($this->sampleGradeValues(), static fn ($v) => $v !== $firstGrade['grade_value']));
        $newValue   = $candidates[0] ?? $firstGrade['grade_value'];
        $change     = $gradeModel->requestChange((int) $firstGrade['grade_id'], $newValue, 'DEV SEED: correcting a recording error.', $facultyId);

        echo $change['ok']
            ? "  filed a change request on grade #{$firstGrade['grade_id']} ({$firstGrade['grade_value']} -> {$newValue}).\n"
            : "  change request failed: {$change['error']}\n";
    }
}
