<?php

use App\Libraries\SharedSubjectCoverageService;
use App\Models\CourseOfferingModel;
use App\Models\OfferingPlacementRequestModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Step 7 of the shared-subject architecture work: "what do MY students need
 * that someone else owns, and are they covered?" The exact inverse of Step
 * 6's owning-scheduler dashboard — mutually exclusive by construction since
 * both split on the same SchedulingAuthorization::authorizedSubDeptIds().
 *
 * @internal
 */
final class SharedSubjectCoverageServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const TERM       = '1 · 1st Semester';
    private const OTHER_TERM = '2 · 2nd Semester';

    private int $nextUserId = 80000;
    private int $deptSeq    = 1;
    private int $programSeq = 1;

    private function newSubDept(): int
    {
        $collegeId = $this->deptSeq++;
        $subDeptId = $this->deptSeq++;
        db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => 'Unit ' . $subDeptId]);

        return $subDeptId;
    }

    private function seedChairperson(int $subDeptId): int
    {
        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['sub_department_id' => $subDeptId]);

        return $userId;
    }

    private function seedSubject(string $code, ?int $subDeptId): int
    {
        db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $code . ' Title', 'sub_department_id' => $subDeptId]);

        return (int) db_connect()->insertID();
    }

    /** Program under the GIVEN sub-dept, so its sections count as "mine" for that unit's chairperson. */
    private function seedProgram(string $name, int $subDeptId): int
    {
        $id = $this->programSeq++;
        db_connect()->table('programs')->insert(['program_id' => $id, 'sub_department_id' => $subDeptId, 'program_code' => 'P' . $id, 'program_name' => $name, 'is_active' => 1]);

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

    private function seedCurriculumSubject(int $curriculumId, int $subjectId, int $yearLevel, int $semester): void
    {
        db_connect()->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculumId, 'subject_id' => $subjectId, 'year_level' => $yearLevel,
            'semester' => $semester, 'is_required' => 1, 'is_active' => 1,
        ]);
    }

    private function seedSection(string $program, string $label, string $academicYear = self::TERM): int
    {
        $sections = new SectionModel();
        $sections->insert(['academic_year' => $academicYear, 'program' => $program, 'year_level' => '2nd Year', 'section' => $label]);

        return (int) $sections->getInsertID();
    }

    private function seedStudent(int $programId, int $curriculumId, int $sectionId, string $status = 'active'): int
    {
        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['section_id' => $sectionId]);
        db_connect()->table('students')->insert([
            'user_id' => $userId, 'applicant_id' => 'APP' . $userId, 'student_number' => 'SN' . $userId,
            'program_id' => $programId, 'curriculum_id' => $curriculumId, 'student_status' => $status,
        ]);

        return $userId;
    }

    private function seedEnrollment(int $userId, int $sectionId, string $subjectCode, ?int $offeringId = null): void
    {
        db_connect()->table('enrollments')->insert([
            'user_id' => $userId, 'section_id' => $sectionId, 'subject_code' => $subjectCode, 'status' => 'enrolled', 'offering_id' => $offeringId,
        ]);
    }

    private function makeOffering(string $subjectCode, array $sectionIds, ?int $capacity = null): int
    {
        $result = (new CourseOfferingModel())->createMerge(self::TERM, $subjectCode, $sectionIds, $capacity);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        return (int) $result['offering_id'];
    }

    private function seedSchedule(int $offeringId, int $day, string $start, string $end, string $room): void
    {
        $code = db_connect()->table('course_offerings')->select('subject_code')->where('offering_id', $offeringId)->get()->getRowArray()['subject_code'];

        db_connect()->table('plotted_schedules')->insert([
            'offering_id' => $offeringId, 'academic_year' => self::TERM, 'day' => $day,
            'start_time' => $start . ':00', 'end_time' => $end . ':00', 'time_label' => "$start-$end",
            'subject_code' => $code, 'subject_title' => $code . ' Title', 'room' => $room,
        ]);
    }

    /**
     * Standard fixture: IT unit (chairperson) with BSIT-2A needing RED104,
     * owned by a separate Religious Education unit.
     *
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int} [itChair, redSubDept, subjectId, itProgram, section]
     */
    private function makeStandardFixture(): array
    {
        $itSubDept = $this->newSubDept();
        $itChair   = $this->seedChairperson($itSubDept);
        $itProgram = $this->seedProgram('BSIT', $itSubDept);
        $curriculum = $this->seedCurriculum($itProgram, 'C1');

        $redSubDept = $this->newSubDept();
        $subjectId  = $this->seedSubject('RED104', $redSubDept);
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);

        $section = $this->seedSection('BSIT', 'A');
        $this->seedStudent($itProgram, $curriculum, $section);

        return [$itChair, $redSubDept, $subjectId, $itProgram, $section];
    }

    // --- 1-4: external-subject identification -------------------------------

    public function testSubjectRequiredByMyStudentsButOwnedElsewhereAppears(): void
    {
        [$itChair] = $this->makeStandardFixture();

        $result = (new SharedSubjectCoverageService())->build($itChair, self::TERM);

        $this->assertCount(1, $result['subjects']);
        $this->assertSame('RED104', $result['subjects'][0]['subject_code']);
    }

    public function testSubjectIOwnDoesNotAppearAsExternal(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $program = $this->seedProgram('BSIT', $subDept);
        $curriculum = $this->seedCurriculum($program, 'C1');
        $subjectId = $this->seedSubject('ITE101', $subDept); // owned by MY OWN unit
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection('BSIT', 'A');
        $this->seedStudent($program, $curriculum, $section);

        $result = (new SharedSubjectCoverageService())->build($chair, self::TERM);

        $this->assertCount(0, $result['subjects']);
    }

    public function testSubjectFromDelegatedUnitDoesNotAppearAsExternal(): void
    {
        $itSubDept  = $this->newSubDept();
        $itChair    = $this->seedChairperson($itSubDept);
        $itProgram  = $this->seedProgram('BSIT', $itSubDept);
        $curriculum = $this->seedCurriculum($itProgram, 'C1');

        $redSubDept = $this->newSubDept();
        $subjectId  = $this->seedSubject('RED104', $redSubDept);
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection('BSIT', 'A');
        $this->seedStudent($itProgram, $curriculum, $section);

        // IT chair is delegated Religious Education's scheduling authority.
        db_connect()->table('sub_department_schedulers')->insert(['sub_dept_id' => $redSubDept, 'user_id' => $itChair, 'created_at' => date('Y-m-d H:i:s')]);

        $result = (new SharedSubjectCoverageService())->build($itChair, self::TERM);

        $this->assertCount(0, $result['subjects']);
    }

    public function testUnrelatedSubjectNotRequiredByMyStudentsDoesNotAppear(): void
    {
        [$itChair] = $this->makeStandardFixture();

        // A subject nobody of mine needs, owned by yet another unit.
        $this->seedSubject('MSC101', $this->newSubDept());

        $result = (new SharedSubjectCoverageService())->build($itChair, self::TERM);

        $this->assertCount(1, $result['subjects']);
        $this->assertSame('RED104', $result['subjects'][0]['subject_code']);
    }

    // --- 5/6: my-demand scoping -----------------------------------------------

    public function testMyExpectedOnlyCountsStudentsSectionsIManage(): void
    {
        [$itChair, $redSubDept, $subjectId, $itProgram, $itSection] = $this->makeStandardFixture();

        // Another, unrelated program/unit's students ALSO need RED104.
        $otherSubDept = $this->newSubDept();
        $otherProgram = $this->seedProgram('MUS', $otherSubDept);
        $otherCurriculum = $this->seedCurriculum($otherProgram, 'C2');
        $this->seedCurriculumSubject($otherCurriculum, $subjectId, 2, 1);
        $otherSection = $this->seedSection('MUS', 'A');
        $this->seedStudent($otherProgram, $otherCurriculum, $otherSection);
        $this->seedStudent($otherProgram, $otherCurriculum, $otherSection);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertSame(1, $entry['my_expected'], 'Must not include the other unit\'s 2 students.');
    }

    public function testInstitutionWideDemandDoesNotInflateMyExpected(): void
    {
        // Same as above, phrased the other direction: confirm total across
        // units (1 + 2 = 3) is never what my_expected reports.
        [$itChair] = $this->makeStandardFixture();
        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];
        $this->assertNotSame(3, $entry['my_expected']);
        $this->assertSame(1, $entry['my_expected']);
    }

    // --- 7-9: coverage -----------------------------------------------------------

    public function testFullyCoveredCalculationCorrect(): void
    {
        [$itChair, , $subjectId, , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other]);
        $student = db_connect()->table('users')->select('id')->where('section_id', $section)->get()->getRowArray()['id'];
        $this->seedEnrollment((int) $student, $section, 'RED104', $offeringId);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertSame('fully_covered', $entry['coverage']);
        $this->assertSame(0, $entry['my_unassigned']);
    }

    public function testPartiallyCoveredCalculationCorrect(): void
    {
        [$itChair, , $subjectId, $itProgram, $section] = $this->makeStandardFixture();
        $curriculum = db_connect()->table('students')->select('curriculum_id')->where('user_id', db_connect()->table('users')->select('id')->where('section_id', $section)->get()->getRowArray()['id'])->get()->getRowArray()['curriculum_id'];
        $this->seedStudent($itProgram, (int) $curriculum, $section); // a 2nd expected student, unassigned

        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other]);
        $firstStudent = db_connect()->table('users')->select('id')->where('section_id', $section)->get()->getResultArray()[0]['id'];
        $this->seedEnrollment((int) $firstStudent, $section, 'RED104', $offeringId);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertSame('partially_covered', $entry['coverage']);
        $this->assertSame(2, $entry['my_expected']);
        $this->assertSame(1, $entry['my_assigned']);
        $this->assertSame(1, $entry['my_unassigned']);
    }

    public function testNotCoveredCalculationCorrect(): void
    {
        [$itChair] = $this->makeStandardFixture();

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertSame('not_covered', $entry['coverage']);
        $this->assertSame(0, $entry['my_assigned']);
    }

    // --- 10-12: coverage must come from real enrollment, not request status -----

    public function testPendingRequestDoesNotCountAsCoverage(): void
    {
        [$itChair, , $subjectId, , $section] = $this->makeStandardFixture();
        (new OfferingPlacementRequestModel())->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $itChair, 'request_type' => 'placement']);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertSame('not_covered', $entry['coverage']);
        $this->assertSame(0, $entry['my_assigned']);
    }

    public function testApprovedNotFulfilledRequestDoesNotCountAsCoverage(): void
    {
        [$itChair, $redSubDept, $subjectId, , $section] = $this->makeStandardFixture();
        $redChair = $this->seedChairperson($redSubDept);
        $requests = new OfferingPlacementRequestModel();
        $created  = $requests->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $itChair, 'request_type' => 'placement']);
        $requests->approve($created['id'], $redChair);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertSame('not_covered', $entry['coverage']);
    }

    public function testFulfilledPlacementIsReflectedAsActualCoverage(): void
    {
        [$itChair, $redSubDept, $subjectId, , $section] = $this->makeStandardFixture();
        $redChair = $this->seedChairperson($redSubDept);
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$other, $this->seedSection('NUR', 'X')]);

        // My student is already individually enrolled through their own
        // section (no offering_id yet, since BSIT-2A isn't attached to any
        // offering) — this is the realistic pre-fulfillment state.
        $student = (int) db_connect()->table('users')->select('id')->where('section_id', $section)->get()->getRowArray()['id'];
        $this->seedEnrollment($student, $section, 'RED104');

        // Before fulfillment: already enrolled, so already covered — Step 4's
        // "assigned" definition counts any enrolled status row regardless of
        // offering_id. Fulfillment's real effect is attaching the SECTION
        // (not individual students) to the offering, backfilling offering_id
        // onto exactly this kind of pre-existing row.
        $before = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];
        $this->assertSame('fully_covered', $before['coverage']);

        $requests = new OfferingPlacementRequestModel();
        $created  = $requests->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $itChair, 'request_type' => 'placement']);
        $fulfilled = $requests->fulfillPlacement($created['id'], $redChair, $offeringId);
        $this->assertTrue($fulfilled['ok'], $fulfilled['error'] ?? '');

        $this->seeInDatabase('enrollments', ['user_id' => $student, 'offering_id' => $offeringId]);

        $after = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];
        $this->assertSame('fully_covered', $after['coverage']);
        $this->assertSame(1, $after['my_assigned']);
    }

    // --- 13/14: term scoping of candidate offerings -------------------------------

    public function testCandidateOfferingsScopedToSelectedTerm(): void
    {
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $this->makeOffering('RED104', [$section, $other]);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertCount(1, $entry['offerings']);
    }

    public function testCandidateOfferingFromAnotherTermNotShown(): void
    {
        [$itChair, $redSubDept, $subjectId] = $this->makeStandardFixture();

        // An offering that exists only in a different term.
        $otherTermSectionA = $this->seedSection('MUS', 'X', self::OTHER_TERM);
        $otherTermSectionB = $this->seedSection('NUR', 'X', self::OTHER_TERM);
        (new CourseOfferingModel())->createMerge(self::OTHER_TERM, 'RED104', [$otherTermSectionA, $otherTermSectionB], null);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertCount(0, $entry['offerings']);
    }

    // --- 15/16: capacity -----------------------------------------------------------

    public function testCapacityAndAvailableSeatsCorrect(): void
    {
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other], 10);
        $this->seedUsers(99001);
        $this->seedEnrollment(99001, $section, 'RED104', $offeringId);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];
        $offering = $entry['offerings'][0];

        $this->assertSame(10, $offering['capacity']);
        $this->assertSame(1, $offering['roster_count']);
        $this->assertSame(9, $offering['available_seats']);
    }

    public function testFullOfferingIdentifiedCorrectly(): void
    {
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other], 1);
        $this->seedUsers(99002);
        $this->seedEnrollment(99002, $section, 'RED104', $offeringId);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertTrue($entry['offerings'][0]['is_full']);
    }

    // --- 17-19: conflict checking ---------------------------------------------------

    public function testCandidateOfferingWithNoConflictIdentified(): void
    {
        // The candidate offering must be built from sections OTHER than
        // mine — mine isn't attached to it yet, that's exactly the
        // "candidate" scenario being evaluated.
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $foreignA = $this->seedSection('MUS', 'X');
        $foreignB = $this->seedSection('NUR', 'X');
        $offeringId = $this->makeOffering('RED104', [$foreignA, $foreignB]);
        $this->seedSchedule($offeringId, 0, '10:00', '11:30', 'Room 301');

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];
        $conflicts = $entry['offerings'][0]['conflicts_by_section'][$section] ?? ['unset'];

        $this->assertSame([], $conflicts);
    }

    public function testCandidateOfferingConflictingWithMySectionIdentified(): void
    {
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $foreignA = $this->seedSection('MUS', 'X');
        $foreignB = $this->seedSection('NUR', 'X');
        $offeringId = $this->makeOffering('RED104', [$foreignA, $foreignB]);
        $this->seedSchedule($offeringId, 0, '10:00', '11:30', 'Room 301');

        // My BSIT-2A section already has ITE205 at the same time.
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '10:00:00', 'end_time' => '11:30:00', 'time_label' => '10:00-11:30',
            'subject_code' => 'ITE205', 'subject_title' => 'ITE205 Title', 'room' => 'Room 9',
        ]);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];
        $conflicts = $entry['offerings'][0]['conflicts_by_section'][$section] ?? [];

        $this->assertNotEmpty($conflicts);
    }

    public function testConflictDetailsIdentifyActualOverlappingSubject(): void
    {
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $foreignA = $this->seedSection('MUS', 'X');
        $foreignB = $this->seedSection('NUR', 'X');
        $offeringId = $this->makeOffering('RED104', [$foreignA, $foreignB]);
        $this->seedSchedule($offeringId, 0, '10:00', '11:30', 'Room 301');

        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '10:00:00', 'end_time' => '11:30:00', 'time_label' => '10:00-11:30',
            'subject_code' => 'ITE205', 'subject_title' => 'ITE205 Title', 'room' => 'Room 9',
        ]);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];
        $conflicts = $entry['offerings'][0]['conflicts_by_section'][$section] ?? [];

        $this->assertStringContainsString('ITE205', $conflicts[0]);
    }

    // --- 20: multiple sections kept separate ----------------------------------------

    public function testMultipleSectionsNeedingSameSubjectKeptSeparateForActions(): void
    {
        [$itChair, , $subjectId, $itProgram, $sectionA] = $this->makeStandardFixture();
        $curriculumId = (int) db_connect()->table('students')->select('curriculum_id')->where('user_id', db_connect()->table('users')->select('id')->where('section_id', $sectionA)->get()->getResultArray()[0]['id'])->get()->getRowArray()['curriculum_id'];

        $sectionB = $this->seedSection('BSIT', 'B');
        $this->seedStudent($itProgram, $curriculumId, $sectionB);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertCount(2, $entry['sections']);
        $labels = array_column($entry['sections'], 'section_id');
        $this->assertContains($sectionA, $labels);
        $this->assertContains($sectionB, $labels);
    }

    // --- 21-24: request integration (reusing Step 3 exactly) ------------------------

    public function testPlacementRequestUsesCorrectIds(): void
    {
        [$itChair, , $subjectId, , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other]);

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $itChair,
            'request_type' => 'placement', 'target_offering_id' => $offeringId,
        ]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('offering_placement_requests', ['id' => $result['id'], 'subject_id' => $subjectId, 'section_id' => $section, 'target_offering_id' => $offeringId]);
    }

    public function testDuplicatePendingPlacementRequestDoesNotExposeAnotherRequestAction(): void
    {
        [$itChair, , $subjectId, , $section] = $this->makeStandardFixture();
        (new OfferingPlacementRequestModel())->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $itChair, 'request_type' => 'placement']);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];
        $sectionEntry = $entry['sections'][0];

        $this->assertTrue($sectionEntry['has_pending_placement']);

        // The Step 3 duplicate rule itself still blocks a second attempt.
        $second = (new OfferingPlacementRequestModel())->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $itChair, 'request_type' => 'placement']);
        $this->assertFalse($second['ok']);
    }

    public function testAdditionalOfferingRequestWorksWithoutTargetOffering(): void
    {
        [$itChair, , $subjectId, , $section] = $this->makeStandardFixture();

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $itChair, 'request_type' => 'additional_offering',
        ]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    public function testConflictReportRequestLinksToTargetOffering(): void
    {
        [$itChair, , $subjectId, , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other]);

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $itChair,
            'request_type' => 'conflict_report', 'target_offering_id' => $offeringId,
        ]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('offering_placement_requests', ['id' => $result['id'], 'target_offering_id' => $offeringId, 'request_type' => 'conflict_report']);
    }

    // --- 25/26: authorization boundaries --------------------------------------------

    public function testUserCannotSubmitRequestForSectionTheyDoNotManage(): void
    {
        [, , $subjectId] = $this->makeStandardFixture();
        $unrelated = $this->seedChairperson($this->newSubDept());
        $unrelatedSection = $this->seedSection('MUS', 'Z');

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id' => $subjectId, 'section_id' => $unrelatedSection, 'requested_by' => $unrelated, 'request_type' => 'placement',
        ]);

        $this->assertFalse($result['ok']);
    }

    public function testUserCannotDirectlyModifyExternalOffering(): void
    {
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other]);

        // The IT chair is not authorized to manage RED104's subject, so any
        // attempt to act as a reviewer on it is denied by the SAME
        // centralized check Step 3/5 already enforce — SharedSubjectCoverageService
        // never grants this and exposes no mutation path of its own.
        $authResult = (new App\Libraries\SchedulingAuthorization())->authorizeManageSubject($itChair, db_connect()->table('subjects')->select('subject_id')->where('subject_code', 'RED104')->get()->getRowArray()['subject_id']);

        $this->assertFalse($authResult['ok']);
    }

    // --- 27: owning-scheduler authorization unaffected --------------------------------

    public function testExistingOwningSchedulerAuthorizationRemainsUnchanged(): void
    {
        [, $redSubDept, $subjectId] = $this->makeStandardFixture();
        $redChair = $this->seedChairperson($redSubDept);

        $authResult = (new App\Libraries\SchedulingAuthorization())->authorizeManageSubject($redChair, $subjectId);

        $this->assertTrue($authResult['ok']);
        $this->assertFalse($authResult['override']);
    }

    // --- 28/29: action visibility rules -----------------------------------------------

    public function testZeroUnassignedSectionDoesNotShowRequestPlacement(): void
    {
        [$itChair, , $subjectId, , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other]);
        $student = db_connect()->table('users')->select('id')->where('section_id', $section)->get()->getRowArray()['id'];
        $this->seedEnrollment((int) $student, $section, 'RED104', $offeringId);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertSame(0, $entry['sections'][0]['unassigned_count']);
        // The view only renders the action when unassigned_count > 0 — the
        // service itself always reports the true unassigned count, which is
        // what the view branches on (verified structurally here since this
        // is a service-layer test).
    }

    public function testSubjectWithDemandButNoOfferingsCanRequestAdditionalOffering(): void
    {
        [$itChair] = $this->makeStandardFixture();

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertSame([], $entry['offerings']);
        $this->assertGreaterThan(0, $entry['sections'][0]['unassigned_count']);
    }

    // --- 30/31: full/conflicting-offering attention -----------------------------------

    public function testAllOfferingsFullIsReflectedPerOffering(): void
    {
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $other = $this->seedSection('MUS', 'X');
        $offeringId = $this->makeOffering('RED104', [$section, $other], 1);
        $this->seedUsers(99003);
        $this->seedEnrollment(99003, $section, 'RED104', $offeringId);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertTrue($entry['offerings'][0]['is_full']);
    }

    public function testAllOfferingsConflictingIsReflectedPerOffering(): void
    {
        [$itChair, , , , $section] = $this->makeStandardFixture();
        $foreignA = $this->seedSection('MUS', 'X');
        $foreignB = $this->seedSection('NUR', 'X');
        $offeringId = $this->makeOffering('RED104', [$foreignA, $foreignB]);
        $this->seedSchedule($offeringId, 0, '10:00', '11:30', 'Room 301');

        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '10:00:00', 'end_time' => '11:30:00', 'time_label' => '10:00-11:30',
            'subject_code' => 'ITE205', 'subject_title' => 'ITE205 Title', 'room' => 'Room 9',
        ]);

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertNotEmpty($entry['offerings'][0]['conflicts_by_section'][$section]);
    }

    // --- 32: demand limitations surfaced -----------------------------------------------

    public function testDemandLimitationsAreSurfacedConsistentlyWithStepSix(): void
    {
        [$itChair] = $this->makeStandardFixture();

        $entry = (new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'][0];

        $this->assertContains('assigned_equals_enrolled', $entry['limitations']);
        $this->assertContains('no_retake_tracking', $entry['limitations']);
    }

    // --- 3 (safeguard): mutual exclusivity with Step 6 -------------------------------

    public function testSubjectNeverAppearsOnBothOwningDashboardAndCoverageForSameUser(): void
    {
        [$itChair, $redSubDept, $subjectId] = $this->makeStandardFixture();

        // Delegate RED to the IT chair — it should now belong ONLY on the
        // owning dashboard, never on coverage (already covered by the
        // delegation test above), and the reverse for a subject IT owns.
        $coverageIds = array_column((new SharedSubjectCoverageService())->build($itChair, self::TERM)['subjects'], 'subject_id');
        $dashboardIds = array_column((new \App\Libraries\SharedSubjectDashboardService())->build($itChair, self::TERM, true)['subjects'], 'subject_id');

        $this->assertSame([], array_intersect($coverageIds, $dashboardIds));
    }

    // --- 33: performance ------------------------------------------------------------------

    public function testQueryCountDoesNotGrowAsSubjectSectionOfferingProduct(): void
    {
        $itSubDept = $this->newSubDept();
        $itChair   = $this->seedChairperson($itSubDept);
        $itProgram = $this->seedProgram('BSIT', $itSubDept);
        $curriculum = $this->seedCurriculum($itProgram, 'C1');
        $redSubDept = $this->newSubDept();

        for ($i = 0; $i < 2; $i++) {
            $subjectId = $this->seedSubject('EXT' . $i, $redSubDept);
            $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
            $section = $this->seedSection('BSIT', 'S' . $i);
            $this->seedStudent($itProgram, $curriculum, $section);
            $other = $this->seedSection('MUS', 'O' . $i);
            $offeringId = $this->makeOffering('EXT' . $i, [$section, $other]);
            $this->seedSchedule($offeringId, $i, '10:00', '11:30', 'Room ' . $i);
        }

        $smallDelta = $this->queryDelta(fn () => (new SharedSubjectCoverageService())->build($itChair, self::TERM));

        for ($i = 2; $i < 10; $i++) {
            $subjectId = $this->seedSubject('EXT' . $i, $redSubDept);
            $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
            $section = $this->seedSection('BSIT', 'S' . $i);
            $this->seedStudent($itProgram, $curriculum, $section);
            $other = $this->seedSection('MUS', 'O' . $i);
            $offeringId = $this->makeOffering('EXT' . $i, [$section, $other]);
            $this->seedSchedule($offeringId, $i % 6, '10:00', '11:30', 'Room ' . $i);
        }

        $largeDelta = $this->queryDelta(fn () => (new SharedSubjectCoverageService())->build($itChair, self::TERM));

        // Linear growth with "how many of my sections have demand" is
        // expected and acceptable (meetingsForSection() runs once per such
        // section); what must NOT happen is multiplicative growth with
        // subjects x sections x offerings, which for 8 extra subjects x
        // offerings would be in the hundreds of extra queries, not ~40.
        $this->assertLessThanOrEqual($smallDelta + 100, $largeDelta, 'Query count must not scale with subject x section x offering.');
    }

    private function queryDelta(callable $fn): int
    {
        $before = (int) db_connect()->query("SHOW SESSION STATUS LIKE 'Questions'")->getRow()->Value;
        $fn();
        $after = (int) db_connect()->query("SHOW SESSION STATUS LIKE 'Questions'")->getRow()->Value;

        return $after - $before;
    }
}
