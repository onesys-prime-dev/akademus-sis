<?php

namespace App\Database\Seeds;

use App\Models\AcademicYearModel;
use App\Models\EnrollmentModel;
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
    private const PROGRAM_LABEL = 'BS in Information Technology'; // sections/plotted_schedules/curriculum_presets store this display name, not the code — see DepartmentModel::LEGACY_PROGRAM_NAMES

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
}
