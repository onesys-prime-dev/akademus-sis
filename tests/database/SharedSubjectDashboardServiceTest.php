<?php

use App\Libraries\SharedSubjectDashboardService;
use App\Libraries\SubjectDemandService;
use App\Models\CourseOfferingModel;
use App\Models\OfferingPlacementRequestModel;
use App\Models\PlottedScheduleModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Step 6 of the shared-subject architecture work: the owning-scheduler
 * dashboard. Pure orchestration of Steps 1-5 — every assertion here checks
 * that the aggregation/grouping/filtering is correct, never new business
 * logic.
 *
 * @internal
 */
final class SharedSubjectDashboardServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const TERM       = '1 · 1st Semester';
    private const OTHER_TERM = '2 · 2nd Semester';

    private int $nextUserId  = 70000;
    private int $deptSeq     = 1;
    private int $programSeq  = 1;

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

    private function seedProgram(string $name): int
    {
        $id = $this->programSeq++;
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

    private function seedEnrollment(int $userId, int $sectionId, string $subjectCode, string $status = 'enrolled', ?int $offeringId = null): void
    {
        db_connect()->table('enrollments')->insert([
            'user_id' => $userId, 'section_id' => $sectionId, 'subject_code' => $subjectCode, 'status' => $status, 'offering_id' => $offeringId,
        ]);
    }

    private function makeOffering(string $subjectCode, array $sectionIds, ?int $capacity = null): int
    {
        $result = (new CourseOfferingModel())->createMerge(self::TERM, $subjectCode, $sectionIds, $capacity);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        return (int) $result['offering_id'];
    }

    private function seedSchedule(int $offeringId, int $day, string $start, string $end, string $room, ?string $prof = null, string $academicYear = self::TERM): void
    {
        $code = db_connect()->table('course_offerings')->select('subject_code')->where('offering_id', $offeringId)->get()->getRowArray()['subject_code'];

        db_connect()->table('plotted_schedules')->insert([
            'offering_id' => $offeringId, 'academic_year' => $academicYear, 'day' => $day,
            'start_time' => $start . ':00', 'end_time' => $end . ':00', 'time_label' => "$start-$end",
            'subject_code' => $code, 'subject_title' => $code . ' Title', 'prof' => $prof, 'room' => $room,
        ]);
    }

    // --- Authorization -----------------------------------------------------

    public function testSchedulerSeesSubjectsOwnedByTheirUnit(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $this->makeOffering('RED104', [$a, $b]);

        $result = (new SharedSubjectDashboardService())->build($chair, self::TERM);

        $this->assertCount(1, $result['subjects']);
        $this->assertSame($subjectId, $result['subjects'][0]['subject_id']);
    }

    public function testSchedulerSeesSubjectsFromDelegatedUnit(): void
    {
        $owningSubDept = $this->newSubDept();
        $this->seedSubject('RED104', $owningSubDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $this->makeOffering('RED104', [$a, $b]);

        $delegate = $this->seedChairperson($this->newSubDept());
        db_connect()->table('sub_department_schedulers')->insert(['sub_dept_id' => $owningSubDept, 'user_id' => $delegate, 'created_at' => date('Y-m-d H:i:s')]);

        $result = (new SharedSubjectDashboardService())->build($delegate, self::TERM);

        $this->assertCount(1, $result['subjects']);
    }

    public function testSchedulerDoesNotSeeUnrelatedUnitSubjects(): void
    {
        $owningSubDept = $this->newSubDept();
        $this->seedSubject('RED104', $owningSubDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $this->makeOffering('RED104', [$a, $b]);

        $unrelated = $this->seedChairperson($this->newSubDept());

        $result = (new SharedSubjectDashboardService())->build($unrelated, self::TERM);

        $this->assertCount(0, $result['subjects']);
    }

    public function testUserWithNoAuthorizedUnitsGetsEmptyResultNotAnError(): void
    {
        $userId = $this->nextUserId++;
        $this->seedUsers($userId);

        $result = (new SharedSubjectDashboardService())->build($userId, self::TERM);

        $this->assertSame([], $result['subjects']);
    }

    // --- Demand correctness --------------------------------------------------

    public function testSubjectDemandMetricsMatchSubjectDemandService(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('RED104', $subDept);
        $program   = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection('BSIT', 'A');
        $this->seedStudent($program, $curriculum, $section);

        $direct     = (new SubjectDemandService())->summary($subjectId, self::TERM);
        $dashboard  = (new SharedSubjectDashboardService())->build($chair, self::TERM, true);
        $entry      = $dashboard['subjects'][0];

        $this->assertSame($direct['expected_count'], $entry['demand']['expected_count']);
        $this->assertSame($direct['unassigned_count'], $entry['demand']['unassigned_count']);
    }

    public function testAssignedEnrolledLimitationIsSurfaced(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $this->makeOffering('RED104', [$a, $b]);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];

        $this->assertTrue($entry['demand']['assigned_equals_enrolled']);
        $this->assertContains('assigned_equals_enrolled', $entry['demand']['limitations']);
    }

    public function testProgramDemandBreakdownTotalsEqualExpected(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('RED104', $subDept);
        $progA = $this->seedProgram('BSIT');
        $progB = $this->seedProgram('BSCS');
        $curA  = $this->seedCurriculum($progA, 'CA');
        $curB  = $this->seedCurriculum($progB, 'CB');
        $this->seedCurriculumSubject($curA, $subjectId, 2, 1);
        $this->seedCurriculumSubject($curB, $subjectId, 2, 1);
        $secA = $this->seedSection('BSIT', 'A');
        $secB = $this->seedSection('BSCS', 'A');
        $this->seedStudent($progA, $curA, $secA);
        $this->seedStudent($progB, $curB, $secB);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM, true)['subjects'][0];

        $total = array_sum(array_column($entry['demand']['by_program'], 'expected_count'));
        $this->assertSame($entry['demand']['expected_count'], $total);
    }

    // --- Requests --------------------------------------------------------------

    private function makeRequestFixture(): array
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('RED104', $subDept);
        $program = $this->seedProgram('MUS');
        $requesterSubDept = $this->newSubDept();
        $requester = $this->seedChairperson($requesterSubDept);
        db_connect()->table('programs')->where('program_id', $program)->update(['sub_department_id' => $requesterSubDept]);
        $section = $this->seedSection('MUS', 'A');

        return [$chair, $subjectId, $section, $requester];
    }

    public function testPendingPlacementRequestsAreTermScoped(): void
    {
        [$chair, $subjectId, $section, $requester] = $this->makeRequestFixture();

        (new OfferingPlacementRequestModel())->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $requester, 'request_type' => 'placement']);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM, true)['subjects'][0];
        $this->assertSame(1, $entry['requests']['pending_total']);

        $otherTermEntry = (new SharedSubjectDashboardService())->build($chair, self::OTHER_TERM, true);
        $this->assertSame(0, $otherTermEntry['subjects'][0]['requests']['pending_total'] ?? 0);
    }

    public function testApprovedNotFulfilledCountIsCorrect(): void
    {
        [$chair, $subjectId, $section, $requester] = $this->makeRequestFixture();

        $requests = new OfferingPlacementRequestModel();
        $create = $requests->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $requester, 'request_type' => 'placement']);
        $this->assertTrue($create['ok'], $create['error'] ?? '');
        $approved = $requests->approve($create['id'], $chair);
        $this->assertTrue($approved['ok'], $approved['error'] ?? '');

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM, true)['subjects'][0];
        $this->assertSame(1, $entry['requests']['approved_not_fulfilled']);
    }

    public function testDeniedCancelledFulfilledRequestsDoNotCountAsPending(): void
    {
        [$chair, $subjectId, $section, $requester] = $this->makeRequestFixture();
        $section2 = $this->seedSection('MUS', 'B');
        $section3 = $this->seedSection('MUS', 'C');

        $requests = new OfferingPlacementRequestModel();
        $r1 = $requests->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $requester, 'request_type' => 'placement']);
        $requests->deny($r1['id'], $chair);

        $r2 = $requests->create(['subject_id' => $subjectId, 'section_id' => $section2, 'requested_by' => $requester, 'request_type' => 'placement']);
        $requests->cancel($r2['id'], $requester);

        $r3 = $requests->create(['subject_id' => $subjectId, 'section_id' => $section3, 'requested_by' => $requester, 'request_type' => 'additional_offering']);
        $requests->fulfillNonPlacement($r3['id'], $chair);

        $dashboard = (new SharedSubjectDashboardService())->build($chair, self::TERM, true);
        $entry = $dashboard['subjects'][0];
        $this->assertSame(0, $entry['requests']['pending_total']);
        $this->assertSame(0, $entry['requests']['approved_not_fulfilled']);
    }

    // --- Offerings -----------------------------------------------------------

    public function testSubjectWithNoOfferingRendersCorrectly(): void
    {
        [$chair, $subjectId, $section, $requester] = $this->makeRequestFixture();
        (new OfferingPlacementRequestModel())->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $requester, 'request_type' => 'placement']);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];

        $this->assertSame([], $entry['offerings']);
    }

    public function testOfferingCapacityRosterAvailableSeatsAreCorrect(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b], 10);
        $this->seedUsers($this->nextUserId);
        $this->seedEnrollment($this->nextUserId++, $a, 'RED104', 'enrolled', $offeringId);
        $this->seedUsers($this->nextUserId);
        $this->seedEnrollment($this->nextUserId++, $a, 'RED104', 'enrolled', $offeringId);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];
        $offering = $entry['offerings'][0];

        $this->assertSame(10, $offering['capacity']);
        $this->assertSame(2, $offering['roster_count']);
        $this->assertSame(8, $offering['available_seats']);
    }

    public function testOverCapacityOfferingIsClearlyIdentified(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b], 1);
        $this->seedUsers($this->nextUserId);
        $this->seedEnrollment($this->nextUserId++, $a, 'RED104', 'enrolled', $offeringId);
        $this->seedUsers($this->nextUserId);
        $this->seedEnrollment($this->nextUserId++, $a, 'RED104', 'enrolled', $offeringId);
        $this->seedUsers($this->nextUserId);
        $this->seedEnrollment($this->nextUserId++, $a, 'RED104', 'enrolled', $offeringId);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];
        $offering = $entry['offerings'][0];

        $this->assertSame(3, $offering['roster_count']);
        $this->assertSame(-2, $offering['available_seats'], 'Must not be silently clamped to 0.');
        $this->assertSame(2, $offering['over_capacity_by']);
        $this->assertContains('over_capacity', $entry['attention']);
    }

    public function testOfferingAttachedToMultipleProgramsListsAllRepresentedPrograms(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $c = $this->seedSection('NUR', 'A');
        $this->makeOffering('RED104', [$a, $b, $c]);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];
        $programs = $entry['offerings'][0]['programs'];
        sort($programs);

        $this->assertSame(['BSIT', 'MUS', 'NUR'], $programs);
    }

    // --- Schedules -------------------------------------------------------------

    public function testScheduledOfferingReturnsAllMeetingRowsExactlyOnce(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);
        $this->seedSchedule($offeringId, 0, '10:00', '11:30', 'Room 301', 'profx');
        $this->seedSchedule($offeringId, 2, '10:00', '11:30', 'Room 301', 'profx');

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];
        $offering = $entry['offerings'][0];

        $this->assertTrue($offering['is_scheduled']);
        $this->assertCount(2, $offering['meetings'], 'Two attached sections must not duplicate the two meeting rows into four.');
    }

    public function testUnscheduledOfferingIsIdentifiedCorrectly(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $this->makeOffering('RED104', [$a, $b]);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];

        $this->assertFalse($entry['offerings'][0]['is_scheduled']);
        $this->assertContains('needs_schedule', $entry['attention']);
    }

    // --- Attention states --------------------------------------------------------

    public function testSubjectWithUnassignedDemandGetsAttentionState(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('RED104', $subDept);
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection('BSIT', 'A');
        $this->seedStudent($program, $curriculum, $section);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM, true)['subjects'][0];

        $this->assertContains('unassigned_demand', $entry['attention']);
    }

    public function testSubjectWithPendingRequestsGetsAttentionState(): void
    {
        [$chair, $subjectId, $section, $requester] = $this->makeRequestFixture();
        (new OfferingPlacementRequestModel())->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $requester, 'request_type' => 'placement']);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM, true)['subjects'][0];

        $this->assertContains('pending_requests', $entry['attention']);
    }

    public function testSubjectWithUnscheduledOfferingGetsAttentionState(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $this->makeOffering('RED104', [$a, $b]);

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];

        $this->assertContains('needs_schedule', $entry['attention']);
    }

    public function testSubjectWithNoIssuesGetsNoFalseWarnings(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b], 30);
        $this->seedSchedule($offeringId, 0, '10:00', '11:30', 'Room 301', 'profx');

        $entry = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0];

        $this->assertSame([], $entry['attention']);
    }

    // --- Term scoping ----------------------------------------------------------

    public function testSelectedTermScopesEverythingConsistently(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);
        $this->seedSchedule($offeringId, 0, '10:00', '11:30', 'Room 301');

        $otherTermResult = (new SharedSubjectDashboardService())->build($chair, self::OTHER_TERM, true);

        $this->assertSame([], $otherTermResult['subjects'][0]['offerings'] ?? []);
    }

    // --- Adjustment 1/2: active-subject filtering -------------------------------

    public function testZeroDemandWithExistingOfferingIsVisible(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED108', $subDept); // no curriculum requirement anywhere -> zero demand
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $this->makeOffering('RED108', [$a, $b]);

        $result = (new SharedSubjectDashboardService())->build($chair, self::TERM);

        $this->assertCount(1, $result['subjects']);
        $this->assertSame(0, $result['subjects'][0]['demand']['expected_count']);
    }

    public function testZeroDemandWithPendingRequestIsVisible(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('RED108', $subDept);
        $requesterSubDept = $this->newSubDept();
        $requester = $this->seedChairperson($requesterSubDept);
        $program = $this->seedProgram('MUS');
        db_connect()->table('programs')->where('program_id', $program)->update(['sub_department_id' => $requesterSubDept]);
        $section = $this->seedSection('MUS', 'A');

        (new OfferingPlacementRequestModel())->create(['subject_id' => $subjectId, 'section_id' => $section, 'requested_by' => $requester, 'request_type' => 'placement']);

        $result = (new SharedSubjectDashboardService())->build($chair, self::TERM);

        $this->assertCount(1, $result['subjects']);
    }

    public function testZeroDemandNoOfferingNoRequestIsHiddenByDefault(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED108', $subDept);

        $result = (new SharedSubjectDashboardService())->build($chair, self::TERM);

        $this->assertCount(0, $result['subjects']);
        $this->assertSame(1, $result['inactive_count']);

        $withInactive = (new SharedSubjectDashboardService())->build($chair, self::TERM, true);
        $this->assertCount(1, $withInactive['subjects']);
    }

    public function testPositiveDemandWithNoOfferingIsVisibleAndFlagged(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('RED104', $subDept);
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $this->seedCurriculumSubject($curriculum, $subjectId, 2, 1);
        $section = $this->seedSection('BSIT', 'A');
        $this->seedStudent($program, $curriculum, $section);

        $result = (new SharedSubjectDashboardService())->build($chair, self::TERM);

        $this->assertCount(1, $result['subjects']);
        $this->assertSame([], $result['subjects'][0]['offerings']);
        $this->assertNotEmpty($result['subjects'][0]['attention']);
    }

    // --- Human-readable output, no raw IDs leaking as the only label -----------

    public function testOfferingLabelIsHumanReadableNotARawId(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $this->makeOffering('RED104', [$a, $b]);

        $offering = (new SharedSubjectDashboardService())->build($chair, self::TERM)['subjects'][0]['offerings'][0];

        $this->assertStringStartsWith('RED104', $offering['label']);
    }

    // --- Performance: no N+1 ----------------------------------------------------

    public function testNoQueryCountGrowthWithSubjectCount(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);

        for ($i = 0; $i < 2; $i++) {
            $code = 'SUBJ' . $i;
            $this->seedSubject($code, $subDept);
            $a = $this->seedSection('BSIT', 'A' . $i);
            $b = $this->seedSection('MUS', 'A' . $i);
            $offeringId = $this->makeOffering($code, [$a, $b]);
            $this->seedSchedule($offeringId, $i, '10:00', '11:30', 'Room ' . $i);
        }

        $smallDelta = $this->queryDelta(fn () => (new SharedSubjectDashboardService())->build($chair, self::TERM));

        for ($i = 2; $i < 12; $i++) {
            $code = 'SUBJ' . $i;
            $this->seedSubject($code, $subDept);
            $a = $this->seedSection('BSIT', 'A' . $i);
            $b = $this->seedSection('MUS', 'A' . $i);
            $offeringId = $this->makeOffering($code, [$a, $b]);
            $this->seedSchedule($offeringId, $i % 6, '10:00', '11:30', 'Room ' . $i);
        }

        $largeDelta = $this->queryDelta(fn () => (new SharedSubjectDashboardService())->build($chair, self::TERM));

        $this->assertLessThanOrEqual($smallDelta + 2, $largeDelta, 'Query count must not scale with subject count.');
    }

    private function queryDelta(callable $fn): int
    {
        $before = (int) db_connect()->query("SHOW SESSION STATUS LIKE 'Questions'")->getRow()->Value;
        $fn();
        $after = (int) db_connect()->query("SHOW SESSION STATUS LIKE 'Questions'")->getRow()->Value;

        return $after - $before;
    }
}
