<?php

use App\Libraries\StudentAccountNormalizer;
use App\Models\CurriculumModel;
use App\Models\EnrollmentModel;
use App\Models\ProgramModel;
use App\Models\SectionModel;
use App\Models\StudentModel;
use App\Models\TuitionAssessmentModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Coverage for bringing a pre-Admissions-handoff `users` account (student
 * group) up to current SIS behavior — see App\Libraries\
 * StudentAccountNormalizer. Mirrors EnrollmentModelTest's fixture style
 * (real plotted_schedules + catalog subjects, never bare codes).
 *
 * @internal
 */
final class StudentAccountNormalizerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private const TERM = 'AY2026-2027';

    private function makeProgram(string $code = 'BSIT'): int
    {
        $programs = new ProgramModel();
        $programs->insert(['program_code' => $code, 'program_name' => 'BS ' . $code, 'is_active' => 1]);

        return (int) $programs->getInsertID();
    }

    private function makeSection(int $programId, string $program, string $section = 'A'): array
    {
        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => self::TERM,
            'program'       => $program,
            'year_level'    => '1st Year',
            'section'       => $section,
            'capacity'      => null,
        ]);

        return $sections->find($sections->getInsertID());
    }

    /**
     * Plots a meeting AND wires the subject into the program's live
     * curriculum (curriculum_subjects) — enrollOne() refuses a subject
     * that's merely plotted but outside the student's assigned curriculum
     * (EnrollmentModel::outsideCurriculum()), exactly as it should in
     * production; the fixture has to be real enough to pass that same gate.
     */
    private function plotSubject(array $section, string $code, int $day, string $start, string $end, int $programId): void
    {
        $subjectId = db_connect()->table('subjects')->where('subject_code', $code)->get()->getRowArray()['subject_id'] ?? null;

        if ($subjectId === null) {
            db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $code . ' Title', 'units' => 3, 'is_active' => 1]);
            $subjectId = db_connect()->insertID();
        }

        $curriculumId = (int) (new CurriculumModel())->liveFor($programId)['curriculum_id'];

        if (db_connect()->table('curriculum_subjects')->where(['curriculum_id' => $curriculumId, 'subject_id' => $subjectId])->countAllResults() === 0) {
            db_connect()->table('curriculum_subjects')->insert([
                'curriculum_id' => $curriculumId, 'subject_id' => $subjectId,
                'year_level' => 1, 'semester' => 1, 'is_required' => 1, 'is_active' => 1,
            ]);
        }

        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => $section['academic_year'], 'program' => $section['program'],
            'year_level' => $section['year_level'], 'section' => $section['section'],
            'day' => $day, 'start_time' => $start, 'end_time' => $end, 'time_label' => "{$start}-{$end}",
            'subject_code' => $code, 'subject_title' => $code . ' Title', 'room' => 'Room 101',
            'theme' => 'blue', 'status' => 'published',
        ]);
    }

    private function makeStudentUser(int $id, ?int $sectionId): void
    {
        $this->seedUsers($id);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);

        if ($sectionId !== null) {
            db_connect()->table('users')->where('id', $id)->update(['section_id' => $sectionId]);
        }
    }

    public function testLegacyAccountIsRepairedToFullyMatchANewlyPromotedStudent(): void
    {
        $programId = $this->makeProgram();
        $section   = $this->makeSection($programId, 'BS BSIT');
        $this->plotSubject($section, 'IT101', 0, '08:00:00', '09:30:00', $programId);
        $this->plotSubject($section, 'IT102', 1, '08:00:00', '09:30:00', $programId);
        db_connect()->table('tuition_rates')->insert(['program_id' => $programId, 'academic_year' => self::TERM, 'per_unit_rate' => 500, 'installment_count' => 3]);

        $this->makeStudentUser(101, (int) $section['section_id']);

        $normalizer = new StudentAccountNormalizer();
        $before     = $normalizer->classify(101);
        $this->assertSame(StudentAccountNormalizer::STATUS_REPAIRABLE, $before['status']);

        $result = $normalizer->normalize(101, true);
        $this->assertTrue($result['ok']);

        // students row: program + CURRENT live curriculum (CurriculumModel::liveFor), identity preserved.
        $student = (new StudentModel())->where('user_id', 101)->first();
        $this->assertNotNull($student);
        $this->assertSame($programId, (int) $student['program_id']);
        $this->assertSame((int) (new CurriculumModel())->liveFor($programId)['curriculum_id'], (int) $student['curriculum_id']);
        $this->assertSame('active', $student['student_status']);
        $this->assertStringStartsWith('LEGACY-', $student['applicant_id'], 'no real Admissions record exists, so this must never look like one');
        $this->assertMatchesRegularExpression('/^STU-\d{4}-\d{5}$/', $student['student_number'], 'same generator shape a real promotion uses');

        // enrollment: one row per plotted subject, via the real block-enroll path.
        $this->seeInDatabase('enrollments', ['user_id' => 101, 'subject_code' => 'IT101', 'status' => 'enrolled', 'method' => 'block']);
        $this->seeInDatabase('enrollments', ['user_id' => 101, 'subject_code' => 'IT102', 'status' => 'enrolled', 'method' => 'block']);
        $this->assertSame(2, (new EnrollmentModel())->where('user_id', 101)->where('status', 'enrolled')->countAllResults());

        // tuition: real computed totals (6 units * 500), never fabricated.
        $assessment = (new TuitionAssessmentModel())->forStudentYear((int) $student['student_id'], self::TERM);
        $this->assertNotNull($assessment);
        $this->assertSame(6.0, (float) $assessment['total_units']);
        $this->assertSame(3000.0, (float) $assessment['tuition_amount']);
        $this->assertNotSame('unassessed', $assessment['status']);

        // payment schedule, since this program has a configured installment plan.
        $this->assertGreaterThan(0, db_connect()->table('payment_schedules')->where('assessment_id', $assessment['assessment_id'])->countAllResults());

        // fully normalized now reads as 'ok', not merely "no longer broken".
        $after = $normalizer->classify(101);
        $this->assertSame(StudentAccountNormalizer::STATUS_OK, $after['status']);
    }

    public function testRunningNormalizeTwiceNeverDuplicatesRecords(): void
    {
        $programId = $this->makeProgram();
        $section   = $this->makeSection($programId, 'BS BSIT');
        $this->plotSubject($section, 'IT101', 0, '08:00:00', '09:30:00', $programId);
        db_connect()->table('tuition_rates')->insert(['program_id' => $programId, 'academic_year' => self::TERM, 'per_unit_rate' => 500, 'installment_count' => 1]);
        $this->makeStudentUser(102, (int) $section['section_id']);

        $normalizer = new StudentAccountNormalizer();
        $normalizer->normalize(102, true);
        $normalizer->normalize(102, true); // already 'ok' by now — classify() gates normalize() on repairable/ok only.

        $this->assertSame(1, (new StudentModel())->where('user_id', 102)->countAllResults());
        $this->assertSame(1, (new EnrollmentModel())->where('user_id', 102)->countAllResults());
        $student = (new StudentModel())->where('user_id', 102)->first();
        $this->assertSame(1, (new TuitionAssessmentModel())->where('student_id', $student['student_id'])->countAllResults());
    }

    public function testNoTuitionRateLeavesAnHonestUnassessedPlaceholderNotAFabricatedTotal(): void
    {
        $programId = $this->makeProgram('BSCS');
        $section   = $this->makeSection($programId, 'BS BSCS');
        $this->plotSubject($section, 'CS101', 0, '08:00:00', '09:30:00', $programId);
        // Deliberately no tuition_rates row for this program/year.
        $this->makeStudentUser(103, (int) $section['section_id']);

        $result = (new StudentAccountNormalizer())->normalize(103, true);
        $this->assertTrue($result['ok']);

        $student    = (new StudentModel())->where('user_id', 103)->first();
        $assessment = (new TuitionAssessmentModel())->forStudentYear((int) $student['student_id'], self::TERM);
        $this->assertSame('unassessed', $assessment['status']);
        $this->assertSame(0.0, (float) $assessment['total_amount'], 'an unpriced program must never get a guessed total');
    }

    public function testAmbiguousAccountWithNoSectionIsFlaggedNotTouched(): void
    {
        $this->makeStudentUser(104, null);

        $classification = (new StudentAccountNormalizer())->classify(104);
        $this->assertSame(StudentAccountNormalizer::STATUS_AMBIGUOUS, $classification['status']);

        $result = (new StudentAccountNormalizer())->normalize(104, true);
        $this->assertFalse($result['ok'], 'an ambiguous account must be refused, never guessed at');
        $this->assertSame(0, (new StudentModel())->where('user_id', 104)->countAllResults());
    }

    public function testNonStudentUserIsNeverTouched(): void
    {
        $this->seedUsers(105); // no student-group membership at all

        $classification = (new StudentAccountNormalizer())->classify(105);
        $this->assertSame(StudentAccountNormalizer::STATUS_NOT_STUDENT, $classification['status']);

        $result = (new StudentAccountNormalizer())->normalize(105, true);
        $this->assertFalse($result['ok']);
        $this->assertSame(0, (new StudentModel())->where('user_id', 105)->countAllResults());
    }

    public function testCurrentAdmissionsPromotionStillProducesAFullyOkAccount(): void
    {
        $this->makeProgram('BSN');

        $promoted = (new StudentModel())->promoteFromApplicant([
            'applicant_id' => 'REAL-APPLICANT-1',
            'first_name'   => 'Real',
            'last_name'    => 'Applicant',
            'email'        => 'real.applicant@example.com',
            'program_code' => 'BSN',
            'program_name' => 'BS BSN',
        ]);

        $this->assertTrue($promoted['ok']);
        $this->assertStringStartsWith('STU-', $promoted['student']['student_number']);
        $this->assertSame('REAL-APPLICANT-1', $promoted['student']['applicant_id'], 'a real promotion keeps its real Admissions id — never rewritten to LEGACY-*');

        // No section yet — that's a later, separate step (Sections page),
        // not something this normalizer invents or flags as broken. A
        // freshly promoted student with a complete students row and
        // nothing yet to cross-check against reads as 'ok', same as any
        // other account with nothing left to repair.
        $classification = (new StudentAccountNormalizer())->classify((int) $promoted['student']['user_id']);
        $this->assertSame(StudentAccountNormalizer::STATUS_OK, $classification['status']);
    }
}
