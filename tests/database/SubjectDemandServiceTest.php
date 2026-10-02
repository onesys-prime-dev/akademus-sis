<?php

use App\Libraries\SubjectDemandService;
use App\Libraries\TermCurriculum;
use App\Models\CourseOfferingModel;
use App\Models\OfferingPlacementRequestModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Step 4 of the shared-subject architecture work: live demand aggregation.
 * Every number here is computed from real students/curriculum_subjects/
 * enrollments/grades rows — nothing is stored or cached.
 *
 * @internal
 */
final class SubjectDemandServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private int $nextUserId = 50000;

    // Deliberately short (<=20 chars): sections.academic_year is VARCHAR(20)
    // in this test environment's schema (WidenAcademicYearColumns's down()
    // narrows it back on every test's schema-refresh cycle), so a
    // full-length "AY 2026 - 2027 · 1st Semester" label would get truncated
    // once that narrowing runs against leftover test data. TermCurriculum's
    // parser only anchors on the "· Nst Semester" suffix — the prefix can be
    // anything — so these short, distinct labels parse identically.
    private const TERM       = '1 · 1st Semester';
    private const OTHER_TERM = '2 · 2nd Semester';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(1, TermCurriculum::semesterOf(self::TERM));
        $this->assertSame(2, TermCurriculum::semesterOf(self::OTHER_TERM));
    }

    private function seedProgram(string $name): int
    {
        static $seq = 1;
        $id = $seq++;
        db_connect()->table('programs')->insert(['program_id' => $id, 'program_code' => 'PRG' . $id, 'program_name' => $name, 'is_active' => 1]);

        return $id;
    }

    /**
     * A chairperson user authorized (via SchedulingAuthorization) to submit
     * placement requests for $programId's sections — creates the
     * college/sub-department chain and points the program at it.
     */
    private function seedChairpersonFor(int $programId): int
    {
        static $deptSeq = 1;
        $collegeId = $deptSeq++;
        $subDeptId = $deptSeq++;

        db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => 'Unit ' . $subDeptId]);
        db_connect()->table('programs')->where('program_id', $programId)->update(['sub_department_id' => $subDeptId]);

        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['sub_department_id' => $subDeptId]);

        return $userId;
    }

    private function seedCurriculum(int $programId, string $code): int
    {
        db_connect()->table('curriculum')->insert([
            'program_id'      => $programId,
            'curriculum_code' => $code,
            'curriculum_name' => $code,
            'academic_year'   => '2026-2027',
            'status'          => 'published',
            'is_current'      => 1,
            'is_active'       => 1,
        ]);

        return (int) db_connect()->insertID();
    }

    private function seedCurriculumSubject(int $curriculumId, int $subjectId, int $yearLevel, int $semester): void
    {
        db_connect()->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculumId,
            'subject_id'    => $subjectId,
            'year_level'    => $yearLevel,
            'semester'      => $semester,
            'is_required'   => 1,
            'is_active'     => 1,
        ]);
    }

    private function seedSubject(string $code): int
    {
        db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $code . ' Title']);

        return (int) db_connect()->insertID();
    }

    private function seedSection(string $academicYear, string $program, string $yearLevel, string $label): int
    {
        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => $academicYear,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $label,
        ]);

        return (int) $sections->getInsertID();
    }

    /**
     * Seeds an active student with a real curriculum, sectioned into
     * $sectionId, and returns their user_id.
     */
    private function seedStudent(int $programId, int $curriculumId, int $sectionId, string $status = 'active'): int
    {
        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['section_id' => $sectionId]);

        db_connect()->table('students')->insert([
            'user_id'         => $userId,
            'applicant_id'    => 'APP' . $userId,
            'student_number'  => 'SN' . $userId,
            'program_id'      => $programId,
            'curriculum_id'   => $curriculumId,
            'student_status'  => $status,
        ]);

        return $userId;
    }

    private ?int $gradingScaleId = null;

    private function seedGrade(int $userId, string $subjectCode, string $status, int $isPassing): void
    {
        // The student's own current section/enrollment row — a locked
        // grade always belongs to a real enrollment (FK, unique).
        $sectionId = (int) db_connect()->table('users')->select('section_id')->where('id', $userId)->get()->getRowArray()['section_id'];

        db_connect()->table('enrollments')->insert([
            'user_id'      => $userId,
            'section_id'   => $sectionId,
            'subject_code' => $subjectCode,
            'status'       => 'dropped', // the historical attempt; not counted as a current active enrollment
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
            'enrollment_id'    => $enrollmentId,
            'user_id'          => $userId,
            'subject_code'     => $subjectCode,
            'section_id'       => $sectionId,
            'academic_year'    => self::TERM,
            'faculty_id'       => $facultyId,
            'grading_scale_id' => $this->gradingScaleId,
            'grade_value'      => $isPassing === 1 ? '1.50' : '5.00',
            'status'           => $status,
            'is_passing'       => $isPassing,
        ]);
    }

    private function seedEnrollment(int $userId, int $sectionId, string $subjectCode, string $status = 'enrolled'): void
    {
        db_connect()->table('enrollments')->insert([
            'user_id'      => $userId,
            'section_id'   => $sectionId,
            'subject_code' => $subjectCode,
            'status'       => $status,
        ]);
    }

    // --- Expected Demand: basic rules ------------------------------------

    public function testRegularActiveStudentCurriculumRequiresSubjectThisTerm(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(1, $expected);
        $this->assertSame($student, $expected[0]['user_id']);
    }

    public function testDifferentCurriculumVersionWhereSubjectNotRequiredIsNotCounted(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculumV1 = $this->seedCurriculum($program, 'BSIT-V1');
        $curriculumV2 = $this->seedCurriculum($program, 'BSIT-V2');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculumV1, $subjectId, 2, 1); // only v1 requires it

        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $this->seedStudent($program, $curriculumV2, $section); // follows v2

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(0, $expected);
    }

    public function testSubjectInCorrectCurriculumButWrongSemesterIsNotCounted(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 2); // 2nd semester, not 1st

        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A'); // TERM is 1st semester
        $this->seedStudent($program, $curriculum, $section);

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(0, $expected);
    }

    public function testStudentAlreadyPassedSubjectIsNotCountedInExpectedDemand(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, 'RED104', 'locked', 1);

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(0, $expected);
    }

    public function testFailedSubjectStillNominallyAtRequiredTermIsCountedAsRetake(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, 'RED104', 'locked', 0); // failed, locked

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(1, $expected);
        $this->assertSame($student, $expected[0]['user_id']);
    }

    public function testInactiveStudentIsNotCounted(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $this->seedStudent($program, $curriculum, $section, 'inactive');

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(0, $expected);
    }

    // --- Assigned / Enrolled / Unassigned --------------------------------

    public function testStudentAssignedToOfferingIsCountedInExpectedAndAssigned(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedEnrollment($student, $section, 'RED104');

        $summary = (new SubjectDemandService())->summary($subjectId, self::TERM);

        $this->assertSame(1, $summary['expected_count']);
        $this->assertSame(1, $summary['assigned_count']);
    }

    public function testStudentOfficiallyEnrolledCountsPerRealSemantics(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedEnrollment($student, $section, 'RED104', 'enrolled');

        $summary = (new SubjectDemandService())->summary($subjectId, self::TERM);

        // Documented Step 4 limitation: 'enrolled' is the only active status,
        // so assigned_count and enrolled_count are the same value today.
        $this->assertSame($summary['assigned_count'], $summary['enrolled_count']);
        $this->assertTrue($summary['assigned_equals_enrolled']);
        $this->assertContains('assigned_equals_enrolled', $summary['limitations']);
    }

    public function testExpectedButNotAssignedAppearsInUnassigned(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);

        $unassigned = (new SubjectDemandService())->unassignedStudents($subjectId, self::TERM);

        $this->assertCount(1, $unassigned);
        $this->assertSame($student, $unassigned[0]['user_id']);
    }

    public function testSameStudentCannotBeCountedTwiceForTheSameSubject(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        // Data anomaly: two curriculum_subjects rows for the same
        // (curriculum, subject, semester) pair — the student must still
        // only be counted once.
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $this->seedStudent($program, $curriculum, $section);

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(1, $expected);
    }

    // --- Cross-program aggregation ----------------------------------------

    public function testTwoProgramsRequiringSharedSubjectBothContributeToAggregatedDemand(): void
    {
        $it = $this->seedProgram('BSIT');
        $cs = $this->seedProgram('BSCS');
        $curIt = $this->seedCurriculum($it, 'BSIT-C1');
        $curCs = $this->seedCurriculum($cs, 'BSCS-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curIt, $subjectId, 2, 1);
        $this->seedCurriculumSubject($curCs, $subjectId, 2, 1);

        $secIt = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $secCs = $this->seedSection(self::TERM, 'BSCS', '2nd Year', 'A');
        $this->seedStudent($it, $curIt, $secIt);
        $this->seedStudent($cs, $curCs, $secCs);

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(2, $expected);
    }

    public function testProgramBreakdownTotalsEqualTotalExpectedDemand(): void
    {
        $it = $this->seedProgram('BSIT');
        $cs = $this->seedProgram('BSCS');
        $curIt = $this->seedCurriculum($it, 'BSIT-C1');
        $curCs = $this->seedCurriculum($cs, 'BSCS-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curIt, $subjectId, 2, 1);
        $this->seedCurriculumSubject($curCs, $subjectId, 2, 1);

        $secIt = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $secCs = $this->seedSection(self::TERM, 'BSCS', '2nd Year', 'A');
        $this->seedStudent($it, $curIt, $secIt);
        $this->seedStudent($it, $curIt, $secIt);
        $this->seedStudent($cs, $curCs, $secCs);

        $service = new SubjectDemandService();
        $total = count($service->expectedStudents($subjectId, self::TERM));
        $byProgram = $service->demandByProgram($subjectId, self::TERM);

        $this->assertSame($total, array_sum(array_column($byProgram, 'expected_count')));
    }

    public function testSectionBreakdownTotalsEqualTotalExpectedDemand(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);

        $secA = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $secB = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'B');
        $this->seedStudent($program, $curriculum, $secA);
        $this->seedStudent($program, $curriculum, $secA);
        $this->seedStudent($program, $curriculum, $secB);

        $service = new SubjectDemandService();
        $total = count($service->expectedStudents($subjectId, self::TERM));
        $bySection = $service->demandBySection($subjectId, self::TERM);

        $this->assertSame($total, array_sum(array_column($bySection, 'expected_count')));
    }

    // --- Placement requests are term-scoped (adjustment 2) -----------------

    public function testPendingPlacementRequestDoesNotAutomaticallyMakeStudentsAssigned(): void
    {
        [$subjectId, $section, , , $chairperson] = $this->makeExpectedStudent();
        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'   => $subjectId,
            'section_id'   => $section,
            'requested_by' => $chairperson,
            'request_type' => 'placement',
        ]);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $summary = (new SubjectDemandService())->summary($subjectId, self::TERM);

        $this->assertSame(0, $summary['assigned_count']);
        $this->assertSame(1, $summary['placement_requests']['pending']);
    }

    public function testFulfilledPlacementRequestReflectsThroughActualOfferingRelationships(): void
    {
        [$subjectId, $section, $student, $subjectCode] = $this->makeExpectedStudent();
        $this->seedEnrollment($student, $section, $subjectCode);

        $secB = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'B');
        $offerings = new CourseOfferingModel();
        $merge = $offerings->createMerge(self::TERM, $subjectCode, [$section, $secB], null);
        $this->assertTrue($merge['ok'], $merge['error'] ?? '');

        $summary = (new SubjectDemandService())->summary($subjectId, self::TERM);

        $this->assertSame(1, $summary['assigned_count']);
    }

    public function testPlacementRequestCountsAreTermScoped(): void
    {
        [$subjectId, $section, , , $chairperson] = $this->makeExpectedStudent();

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'   => $subjectId,
            'section_id'   => $section,
            'requested_by' => $chairperson,
            'request_type' => 'placement',
        ]);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $counts = (new SubjectDemandService())->placementRequestCounts($subjectId, self::TERM);
        $this->assertSame(1, $counts['pending']);

        $otherTermCounts = (new SubjectDemandService())->placementRequestCounts($subjectId, self::OTHER_TERM);
        $this->assertSame(0, $otherTermCounts['pending']);
    }

    public function testOldTermPlacementRequestDoesNotAffectCurrentSummary(): void
    {
        $program = $this->seedProgram('BSIT');
        $chairperson = $this->seedChairpersonFor($program);
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 2); // old term's semester

        $oldSection = $this->seedSection(self::OTHER_TERM, 'BSIT', '2nd Year', 'OLD');
        $this->seedStudent($program, $curriculum, $oldSection);

        // Current-term student + requirement, for the actual summary under test.
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $currentSection = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $this->seedStudent($program, $curriculum, $currentSection);

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'   => $subjectId,
            'section_id'   => $oldSection,
            'requested_by' => $chairperson,
            'request_type' => 'placement',
        ]);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $summary = (new SubjectDemandService())->summary($subjectId, self::TERM);

        $this->assertSame(0, $summary['placement_requests']['pending']);
    }

    // --- Adjustment 1: current-section-only term resolution ----------------

    public function testHistoricalOtherTermSectionDataIsNotTreatedAsCurrentDemand(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);

        // Student's CURRENT section is in a different term than the one queried.
        $otherTermSection = $this->seedSection(self::OTHER_TERM, 'BSIT', '2nd Year', 'A');
        $this->seedStudent($program, $curriculum, $otherTermSection);

        $expected = (new SubjectDemandService())->expectedStudents($subjectId, self::TERM);

        $this->assertCount(0, $expected);

        $summary = (new SubjectDemandService())->summary($subjectId, self::TERM);
        $this->assertContains('current_section_only_term_resolution', $summary['limitations']);
    }

    // --- Subject identity / equivalency ------------------------------------

    public function testExactSubjectIdentityUsesSubjectIdNotTitleMatching(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectA = $this->seedSubject('GEC112.1');
        db_connect()->table('subjects')->insert(['subject_code' => 'GEC112.2', 'subject_name' => 'GEC112.1 Title']); // deliberately same title text
        $subjectB = (int) db_connect()->insertID();

        $this->seedCurriculumSubject($curriculum, $subjectA, 2, 1);
        // subjectB deliberately NOT added to the curriculum.

        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $this->seedStudent($program, $curriculum, $section);

        $expectedA = (new SubjectDemandService())->expectedStudents($subjectA, self::TERM);
        $expectedB = (new SubjectDemandService())->expectedStudents($subjectB, self::TERM);

        $this->assertCount(1, $expectedA);
        $this->assertCount(0, $expectedB, 'Different subject_id, similar title, must never be merged.');
    }

    // --- No N+1 -------------------------------------------------------------

    public function testSummaryQueryCountDoesNotScaleWithStudentCount(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');

        for ($i = 0; $i < 3; $i++) {
            $this->seedStudent($program, $curriculum, $section);
        }

        $smallDelta = $this->queryDelta(fn () => (new SubjectDemandService())->summary($subjectId, self::TERM));

        for ($i = 0; $i < 25; $i++) {
            $this->seedStudent($program, $curriculum, $section);
        }

        $largeDelta = $this->queryDelta(fn () => (new SubjectDemandService())->summary($subjectId, self::TERM));

        $this->assertLessThanOrEqual($smallDelta + 1, $largeDelta, 'Query count must not scale with student count.');
    }

    private function queryDelta(callable $fn): int
    {
        $before = (int) db_connect()->query("SHOW SESSION STATUS LIKE 'Questions'")->getRow()->Value;
        $fn();
        $after = (int) db_connect()->query("SHOW SESSION STATUS LIKE 'Questions'")->getRow()->Value;

        return $after - $before;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: string, 4: int} [subjectId, sectionId, studentUserId, subjectCode, chairpersonUserId]
     */
    private function makeExpectedStudent(): array
    {
        $program = $this->seedProgram('BSIT');
        $chairperson = $this->seedChairpersonFor($program);
        $curriculum = $this->seedCurriculum($program, 'BSIT-C1');
        $subjectId = $this->seedSubject('RED104');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection(self::TERM, 'BSIT', '2nd Year', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);

        return [$subjectId, $section, $student, 'RED104', $chairperson];
    }
}
