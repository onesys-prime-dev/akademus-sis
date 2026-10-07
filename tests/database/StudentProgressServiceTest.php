<?php

use App\Libraries\StudentProgressService;
use App\Models\AcademicYearModel;
use App\Models\SectionModel;
use App\Models\SubjectSubstitutionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Chairperson "Student Progress" batch: curriculum-completion calculation,
 * graduation-candidate detection, and missing-prerequisite surfacing. Built
 * on the same primitives SubjectRequirementServiceTest exercises
 * (curriculum_subjects, subject_substitutions, locked grades) — this file
 * checks the NEW aggregation layered on top of them, not those rules again.
 *
 * @internal
 */
final class StudentProgressServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    // Short label/semester, matching the workaround SubjectDemandServiceTest
    // already documents: plotted_schedules/faculty_assignments/sections'
    // academic_year column is VARCHAR(20) once WidenAcademicYearColumns's
    // down() narrows it back on this test environment's schema-refresh
    // cycle, so a full-length "AY 2026 - 2027 · 1st Semester" label risks
    // truncation against leftover test data. AcademicYearModel::format()
    // still drives the string (unlike SubjectDemandServiceTest's free-text
    // term) since this service resolves "the active term" dynamically via
    // AcademicYearModel::activeFormatted() rather than taking it as a param.
    private string $term;

    private int $nextUserId = 91000;
    private ?int $gradingScaleId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUsers(1);

        db_connect()->table('academic_years')->insert([
            'label' => '1', 'semester' => '1', 'is_active' => 1,
        ]);

        $this->term = AcademicYearModel::format('1', '1');
    }

    private function seedSubject(string $code, float $units = 3.0): int
    {
        db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $code . ' Title', 'units' => $units]);

        return (int) db_connect()->insertID();
    }

    private function seedProgram(string $name): int
    {
        static $seq = 1;
        $id = $seq++;
        db_connect()->table('programs')->insert(['program_id' => $id, 'program_code' => 'P' . $id, 'program_name' => $name, 'is_active' => 1]);

        return $id;
    }

    private function seedCurriculum(int $programId, string $code): int
    {
        db_connect()->table('curriculum')->insert([
            'program_id' => $programId, 'curriculum_code' => $code, 'curriculum_name' => $code,
            'academic_year' => '2026-2027', 'status' => 'published', 'is_current' => 1, 'is_active' => 1,
        ]);

        return (int) db_connect()->insertID();
    }

    private function seedCurriculumSubject(int $curriculumId, int $subjectId, int $yearLevel = 2, int $semester = 1): void
    {
        db_connect()->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculumId, 'subject_id' => $subjectId, 'year_level' => $yearLevel,
            'semester' => $semester, 'is_required' => 1, 'is_active' => 1,
        ]);
    }

    private function seedSection(string $program, string $label): int
    {
        $sections = new SectionModel();
        $sections->insert(['academic_year' => $this->term, 'program' => $program, 'year_level' => '2nd Year', 'section' => $label]);

        return (int) $sections->getInsertID();
    }

    private function seedStudent(int $programId, int $curriculumId, int $sectionId): int
    {
        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['section_id' => $sectionId]);
        db_connect()->table('students')->insert([
            'user_id' => $userId, 'applicant_id' => 'APP' . $userId, 'student_number' => 'SN' . $userId,
            'program_id' => $programId, 'curriculum_id' => $curriculumId, 'student_status' => 'active',
        ]);

        return $userId;
    }

    private function seedGrade(int $userId, int $sectionId, string $subjectCode, bool $passing, string $lockedAt = '2026-01-01 00:00:00'): void
    {
        db_connect()->table('enrollments')->insert([
            'user_id' => $userId, 'section_id' => $sectionId, 'subject_code' => $subjectCode, 'status' => 'dropped',
        ]);
        $enrollmentId = (int) db_connect()->insertID();

        if ($this->gradingScaleId === null) {
            db_connect()->table('grading_scales')->insert([
                'name' => 'GWA', 'type' => 'numeric_gwa', 'min_value' => 1.0, 'max_value' => 5.0,
                'passing_value' => 3.0, 'is_lower_better' => 1, 'is_active' => 1,
            ]);
            $this->gradingScaleId = (int) db_connect()->insertID();
        }

        $facultyId = $this->nextUserId++;
        $this->seedUsers($facultyId);

        db_connect()->table('grades')->insert([
            'enrollment_id' => $enrollmentId, 'user_id' => $userId, 'subject_code' => $subjectCode, 'section_id' => $sectionId,
            'academic_year' => $this->term, 'faculty_id' => $facultyId, 'grading_scale_id' => $this->gradingScaleId,
            'grade_value' => $passing ? '1.50' : '5.00', 'status' => 'locked', 'is_passing' => $passing ? 1 : 0,
            'locked_at' => $lockedAt,
        ]);
    }

    private function seedEnrollment(int $userId, int $sectionId, string $subjectCode): void
    {
        db_connect()->table('enrollments')->insert([
            'user_id' => $userId, 'section_id' => $sectionId, 'subject_code' => $subjectCode, 'status' => 'enrolled',
        ]);
    }

    public function testStudentWhoPassedEveryRequiredSubjectIsFullyCompleteAndACandidate(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $a = $this->seedSubject('A', 3.0);
        $b = $this->seedSubject('B', 4.0);
        $this->seedCurriculumSubject($curriculum, $a);
        $this->seedCurriculumSubject($curriculum, $b);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, $section, 'A', true);
        $this->seedGrade($student, $section, 'B', true);

        $progress = (new StudentProgressService())->forStudent($this->studentIdFor($student));

        $this->assertSame(100.0, $progress['completion_pct']);
        $this->assertSame(7.0, $progress['completed_units']);
        $this->assertCount(2, $progress['passed']);
        $this->assertSame(0, $progress['deficiency_count']);
        $this->assertTrue($progress['is_candidate']);
        $this->assertSame([], $progress['remaining']);
    }

    public function testFailedRequiredSubjectIsADeficiencyAndBlocksCandidacy(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $a = $this->seedSubject('A');
        $this->seedCurriculumSubject($curriculum, $a);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, $section, 'A', false);

        $progress = (new StudentProgressService())->forStudent($this->studentIdFor($student));

        $this->assertSame(1, $progress['deficiency_count']);
        $this->assertCount(1, $progress['failed']);
        $this->assertSame('A', $progress['failed'][0]['code']);
        $this->assertFalse($progress['is_candidate']);
    }

    public function testPassingRetakeAfterAnEarlierFailureCountsAsPassedPermanently(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $a = $this->seedSubject('A');
        $this->seedCurriculumSubject($curriculum, $a);
        $section = $this->seedSection('BSIT', 'A');
        $retakeSection = $this->seedSection('BSIT', 'B');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, $section, 'A', false, '2025-01-01 00:00:00');
        $this->seedGrade($student, $retakeSection, 'A', true, '2026-01-01 00:00:00');

        $progress = (new StudentProgressService())->forStudent($this->studentIdFor($student));

        $this->assertSame(0, $progress['deficiency_count']);
        $this->assertCount(1, $progress['passed']);
        $this->assertTrue($progress['is_candidate']);
    }

    public function testSubjectCurrentlyEnrolledIsInProgressNotADeficiencyAndStillACandidate(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $a = $this->seedSubject('A');
        $this->seedCurriculumSubject($curriculum, $a);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedEnrollment($student, $section, 'A');

        $progress = (new StudentProgressService())->forStudent($this->studentIdFor($student));

        $this->assertCount(1, $progress['in_progress']);
        $this->assertSame(0, $progress['deficiency_count']);
        $this->assertTrue($progress['is_candidate'], 'Only subjects currently in progress should still count as a graduation candidate.');
    }

    public function testPassingAnActiveSubstituteCountsTowardTheRequiredSubject(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $required = $this->seedSubject('OLD-CS301');
        $substitute = $this->seedSubject('CS310');
        (new SubjectSubstitutionModel())->createOrReactivate($required, $substitute, null, 1);
        $this->seedCurriculumSubject($curriculum, $required);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, $section, 'CS310', true);

        $progress = (new StudentProgressService())->forStudent($this->studentIdFor($student));

        $this->assertSame(100.0, $progress['completion_pct']);
        $this->assertCount(1, $progress['passed']);
        $this->assertSame('CS310', $progress['passed'][0]['via']);
        $this->assertTrue($progress['is_candidate']);
    }

    public function testNotStartedSubjectWithUnmetPrerequisiteSurfacesIt(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $prereq = $this->seedSubject('CS101');
        $target = $this->seedSubject('CS201');
        db_connect()->table('subject_prerequisites')->insert(['subject_id' => $target, 'prerequisite_subject_id' => $prereq]);
        $this->seedCurriculumSubject($curriculum, $target);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        // Prerequisite never attempted.

        $progress = (new StudentProgressService())->forStudent($this->studentIdFor($student));

        $this->assertCount(1, $progress['remaining']);
        $this->assertSame('not_started', $progress['remaining'][0]['status']);
        $this->assertSame(['CS101'], $progress['remaining'][0]['missing_prerequisites']);
        $this->assertFalse($progress['is_candidate']);
    }

    public function testStudentWithNoCurriculumAssignedHasNoCompletionPercentage(): void
    {
        $program = $this->seedProgram('BSIT');
        $section = $this->seedSection('BSIT', 'A');
        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['section_id' => $section]);
        db_connect()->table('students')->insert([
            'user_id' => $userId, 'applicant_id' => 'APP' . $userId, 'student_number' => 'SN' . $userId,
            'program_id' => $program, 'curriculum_id' => null, 'student_status' => 'active',
        ]);

        $progress = (new StudentProgressService())->forStudent($this->studentIdFor($userId));

        $this->assertNull($progress['completion_pct']);
        $this->assertFalse($progress['is_candidate']);
    }

    public function testSummarizeStudentsMatchesForStudentForTheSamePopulation(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $a = $this->seedSubject('A');
        $this->seedCurriculumSubject($curriculum, $a);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, $section, 'A', true);

        $studentRow = db_connect()->table('students')->where('user_id', $student)->get()->getRowArray();

        $single = (new StudentProgressService())->forStudent((int) $studentRow['student_id']);
        $summary = (new StudentProgressService())->summarizeStudents([$studentRow])[(int) $studentRow['student_id']];

        $this->assertSame($single['completion_pct'], $summary['completion_pct']);
        $this->assertSame($single['is_candidate'], $summary['is_candidate']);
        $this->assertSame($single['deficiency_count'], $summary['deficiency_count']);
    }

    private function studentIdFor(int $userId): int
    {
        return (int) db_connect()->table('students')->where('user_id', $userId)->get()->getRowArray()['student_id'];
    }
}
