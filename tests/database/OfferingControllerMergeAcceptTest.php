<?php

use App\Models\AcademicYearModel;
use App\Models\CourseOfferingModel;
use App\Models\PlottedScheduleModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * HTTP-level tests for the chairperson's merge-suggestion flow
 * (OfferingController::mergeSuggestions and, in later steps, the accept
 * endpoints that consume its response).
 *
 * @internal
 */
final class OfferingControllerMergeAcceptTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private const TERM = '1 · 1st Semester';

    private int $nextUserId = 60000;
    private int $deptSeq    = 1;
    private int $programSeq = 1;

    private function seedChairperson(?int $subDeptId = null): int
    {
        if ($subDeptId === null) {
            $collegeId = $this->deptSeq++;
            $subDeptId = $this->deptSeq++;
            db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
            db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => 'Unit ' . $subDeptId]);
        }

        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['sub_department_id' => $subDeptId]);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        return $userId;
    }

    private function newSubDept(): int
    {
        $collegeId = $this->deptSeq++;
        $subDeptId = $this->deptSeq++;
        db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => 'Unit ' . $subDeptId]);

        return $subDeptId;
    }

    private function seedSubject(string $code, ?int $subDeptId): int
    {
        db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $code . ' Title', 'sub_department_id' => $subDeptId]);

        return (int) db_connect()->insertID();
    }

    private function seedProgramForSubDept(string $name, int $subDeptId): void
    {
        $id = $this->programSeq++;
        db_connect()->table('programs')->insert([
            'program_id' => 2000 + $id, 'sub_department_id' => $subDeptId, 'program_code' => 'P' . (2000 + $id), 'program_name' => $name, 'is_active' => 1,
        ]);
    }

    /** Sections are keyed by the year-only part of the term (see AcademicYearModel::yearOnly()), unlike plotted rows. */
    private function seedSection(string $program, string $label, string $academicYear = '1'): int
    {
        $sections = new SectionModel();
        $sections->insert(['academic_year' => $academicYear, 'program' => $program, 'year_level' => '2nd Year', 'section' => $label]);

        return (int) $sections->getInsertID();
    }

    /** minSections=1 so a single-section "existing offering" (the acceptMergeSuggestion join-branch fixture) can be seeded directly. */
    private function makeOffering(string $subjectCode, array $sectionIds, ?int $capacity = null): int
    {
        $result = (new CourseOfferingModel())->createMerge(self::TERM, $subjectCode, $sectionIds, $capacity, 1);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        return (int) $result['offering_id'];
    }

    private function block(int $day, string $start, string $end, string $room, ?string $label = null): array
    {
        return ['day' => $day, 'start' => $start, 'end' => $end, 'time_label' => $label ?? "$start-$end", 'room' => $room, 'faculty_id' => null];
    }

    /**
     * CSRF is on globally (see Config\Filters), so every POST in these tests
     * must carry a valid token; forcing the JSON body format here (instead
     * of requiring every call site to chain withBodyFormat('json')) keeps
     * the accept endpoint's own JSON-body payload shape and the CSRF token
     * living together in the same request body, which Security::verify()
     * already knows how to read (its "check the raw JSON body" branch).
     */
    private function withCsrf(array $params): array
    {
        $this->withBodyFormat('json');

        return $params + [csrf_token() => csrf_hash()];
    }

    /** seedChairperson()/newSubDept() return raw ids; Shield's actingAs() needs the User entity. */
    private function actingAsChair(int $userId): void
    {
        $this->actingAs(auth()->getProvider()->findById($userId));
    }

    public function testMergeSuggestionsIncludesOfferingOrTargetSectionId(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedProgramForSubDept('BSIT', $subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $this->seedSection('BSIT', 'B');
        db_connect()->table('rooms')->insert(['name' => 'Room 201', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '2nd Floor', 'is_active' => 1]);
        // plot ITE101 for section A only (legacy row, no offering) — mirrors the live bug scenario
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $this->actingAs(auth()->getProvider()->findById($chair));
        $result = $this->withSession()->get('/chairperson/offerings/merge-suggestions?' . http_build_query([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok']);
        $this->assertNotEmpty($body['suggestions']);
        $this->assertNull($body['suggestions'][0]['offering_id']);
        $this->assertSame($sectionA, $body['suggestions'][0]['target_section_id']);
    }

    public function testMergeSuggestionsForAnOfferingCarriesOfferingIdAndNoTargetSection(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedProgramForSubDept('BSIT', $subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $this->seedSection('BSIT', 'B');
        $sectionC = $this->seedSection('BSIT', 'C');
        db_connect()->table('rooms')->insert(['name' => 'Room 201', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '2nd Floor', 'is_active' => 1]);

        // A real offering for ITE101 spanning sections A and C (B is not attached), with its one plotted schedule.
        $merge = (new CourseOfferingModel())->createMerge(self::TERM, 'ITE101', [$sectionA, $sectionC], 40);
        $this->assertTrue($merge['ok'], $merge['error'] ?? '');
        $offeringId = (int) $merge['offering_id'];
        db_connect()->table('plotted_schedules')->insert([
            'offering_id' => $offeringId, 'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => null,
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $this->actingAs(auth()->getProvider()->findById($chair));
        $result = $this->withSession()->get('/chairperson/offerings/merge-suggestions?' . http_build_query([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok']);
        $this->assertNotEmpty($body['suggestions']);
        $this->assertSame($offeringId, $body['suggestions'][0]['offering_id']);
        $this->assertNull($body['suggestions'][0]['target_section_id']);
    }

    public function testAcceptMergeSuggestionJoinsExistingOffering(): void
    {
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');
        $offering = $this->makeOffering('ITE101', [$sectionA]); // existing offering, section A only
        (new PlottedScheduleModel())->syncOfferingSchedule($offering, $chair, [$this->block(0, '08:00', '09:30', 'Room 201')]);

        $this->actingAsChair($chair);
        $result = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $this->withCsrf([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'subject_code' => 'ITE101', 'offering_id' => $offering, 'target_section_id' => null,
            'meetings' => [['day' => '0', 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30']],
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'], $body['error'] ?? '');
        $this->seeInDatabase('offering_sections', ['offering_id' => $offering, 'section_id' => $sectionB]);
    }

    public function testAcceptMergeSuggestionJoinRemovesJoiningSectionsDuplicateLegacyRows(): void
    {
        // Stale/replayed suggestion: the section accepting the join already
        // has its own legacy row for the same subject (at a different time —
        // attachSection() itself blocks an exact time clash). After joining
        // an offering that HAS a schedule, that legacy row is a duplicate of
        // the subject's real class and would otherwise render twice on the
        // section's grid.
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');
        $offering = $this->makeOffering('ITE101', [$sectionA]);
        (new PlottedScheduleModel())->syncOfferingSchedule($offering, $chair, [$this->block(0, '08:00', '09:30', 'Room 201')]);
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'day' => 1, 'start_time' => '13:00:00', 'end_time' => '14:30:00', 'time_label' => '13:00-14:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 9', 'status' => 'published',
        ]);

        $this->actingAsChair($chair);
        $result = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $this->withCsrf([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'subject_code' => 'ITE101', 'offering_id' => $offering, 'target_section_id' => null,
            'meetings' => [['day' => '0', 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30']],
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'], $body['error'] ?? '');
        $this->seeInDatabase('offering_sections', ['offering_id' => $offering, 'section_id' => $sectionB]);
        // The offering's single real schedule row is all that's left.
        $this->seeNumRecords(1, 'plotted_schedules', ['subject_code' => 'ITE101']);
        $this->seeInDatabase('plotted_schedules', ['offering_id' => $offering, 'subject_code' => 'ITE101']);
        $this->dontSeeInDatabase('plotted_schedules', ['offering_id' => null, 'subject_code' => 'ITE101']);
    }

    public function testAcceptMergeSuggestionJoinRemovesStaleFacultyAssignmentForJoiningSection(): void
    {
        // Same stale/replayed-suggestion scenario as the duplicate-legacy-row
        // test above, but the joining section's own legacy row also had a
        // faculty assignment behind it (what ChairpersonController::sync()
        // would have created when it was originally plotted) — accepting
        // the join must not leave that faculty credited with a class that
        // no longer exists outside the offering it just joined.
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');
        $offering = $this->makeOffering('ITE101', [$sectionA]);
        (new PlottedScheduleModel())->syncOfferingSchedule($offering, $chair, [$this->block(0, '08:00', '09:30', 'Room 201')]);

        $facultyUserId = 90500;
        $this->seedUsers($facultyUserId);
        $facultyUsername = 'testuser' . $facultyUserId;

        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'day' => 1, 'start_time' => '13:00:00', 'end_time' => '14:30:00', 'time_label' => '13:00-14:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 9', 'prof' => $facultyUsername, 'status' => 'published',
        ]);
        db_connect()->table('faculty_assignments')->insert([
            'user_id' => $facultyUserId, 'academic_year' => self::TERM, 'program' => 'BSIT',
            'year_level' => '2nd Year', 'section' => 'B', 'subject_code' => 'ITE101',
            'method' => 'assigned', 'status' => 'assigned',
        ]);

        $this->actingAsChair($chair);
        $result = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $this->withCsrf([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'subject_code' => 'ITE101', 'offering_id' => $offering, 'target_section_id' => null,
            'meetings' => [['day' => '0', 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30']],
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'], $body['error'] ?? '');
        $this->dontSeeInDatabase('faculty_assignments', [
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B', 'subject_code' => 'ITE101',
        ]);
    }

    public function testAcceptMergeSuggestionCreatesOfferingFromLegacyTarget(): void
    {
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $this->actingAsChair($chair);
        $result = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $this->withCsrf([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'subject_code' => 'ITE101', 'offering_id' => null, 'target_section_id' => $sectionA,
            'meetings' => [['day' => '0', 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30']],
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'], $body['error'] ?? '');
        $this->assertTrue($body['schedule_carried']);
        $this->seeInDatabase('offering_sections', ['offering_id' => $body['offering_id'], 'section_id' => $sectionA]);
        $this->seeInDatabase('offering_sections', ['offering_id' => $body['offering_id'], 'section_id' => $sectionB]);
        $this->dontSeeInDatabase('plotted_schedules', ['offering_id' => null, 'subject_code' => 'ITE101']);
    }

    public function testAcceptMergeSuggestionIsSafeWhenJoiningSectionAlreadyPlottedTheSubject(): void
    {
        // mergeSuggestions() itself filters this case out (OfferingController.php:402-404),
        // but acceptMergeSuggestion() must not corrupt data if called anyway with a
        // stale suggestion (e.g. accepted from a second browser tab after the
        // chairperson plotted it manually in the first).
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');
        foreach ([['A', $sectionA], ['B', $sectionB]] as [$label, $id]) {
            db_connect()->table('plotted_schedules')->insert([
                'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => $label,
                'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
                'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
            ]);
        }

        $this->actingAsChair($chair);
        $result = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $this->withCsrf([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'subject_code' => 'ITE101', 'offering_id' => null, 'target_section_id' => $sectionA,
            'meetings' => [['day' => '0', 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30']],
        ]));

        // Either a clean failure (preferred: "already plotted") or a successful,
        // non-duplicating merge is acceptable — a 500 or a duplicate offering is not.
        $body = json_decode($result->response()->getJSON(), true);
        if ($body['ok']) {
            $this->assertSame(1, db_connect()->table('course_offerings')->where('subject_code', 'ITE101')->countAllResults());
        }
    }

    public function testAcceptMergeSuggestionSecondAcceptOfSameTargetFailsCleanly(): void
    {
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');
        $sectionC = $this->seedSection('BSIT', 'C');
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $this->actingAsChair($chair);
        $payload = fn (int $section, string $label) => $this->withCsrf([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => $label,
            'subject_code' => 'ITE101', 'offering_id' => null, 'target_section_id' => $sectionA,
            'meetings' => [['day' => '0', 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30']],
        ]);

        $first = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $payload($sectionB, 'B'));
        $this->assertTrue(json_decode($first->response()->getJSON(), true)['ok']);

        // A second accept naming Section A as the target again (simulating a
        // second tab that hadn't refreshed its suggestion yet) must fail cleanly,
        // not create a second offering or double-attach.
        $second = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $payload($sectionC, 'C'));
        $secondBody = json_decode($second->response()->getJSON(), true);
        if ($secondBody['ok']) {
            $this->assertSame(1, db_connect()->table('course_offerings')->where('subject_code', 'ITE101')->countAllResults());
        }
    }

    public function testAcceptMergeSuggestionSurfacesCapacityOverrideDistinctlyFromGenericFailure(): void
    {
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');
        $offering = $this->makeOffering('ITE101', [$sectionA], 0); // capacity already at its ceiling
        $this->enrollStudent(970001, $sectionB, 'ITE101'); // an incoming student who'd push the join over that ceiling

        $this->actingAsChair($chair);
        $result = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $this->withCsrf([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'subject_code' => 'ITE101', 'offering_id' => $offering, 'target_section_id' => null,
            'meetings' => [],
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertFalse($body['ok']);
        $this->assertArrayHasKey('requires_override', $body); // distinct from a plain error, per attachSection()'s own contract
    }

    /** Mirrors OfferingPlacementRequestTest's own helper — an enrolled student who'd count toward an offering's roster if attached. */
    private function enrollStudent(int $studentId, int $sectionId, string $subjectCode): void
    {
        $this->seedUsers($studentId);
        db_connect()->table('enrollments')->insert([
            'user_id'      => $studentId,
            'section_id'   => $sectionId,
            'subject_code' => $subjectCode,
            'status'       => 'enrolled',
        ]);
    }

    public function testAcceptMergeSuggestionRejectsUnownedSubject(): void
    {
        $myDept = $this->newSubDept();
        $otherDept = $this->newSubDept();
        $chair = $this->seedChairperson($myDept);
        $this->seedSubject('RED999', $otherDept); // owned by a DIFFERENT department
        $sectionB = $this->seedSection('BSIT', 'B');

        $this->actingAsChair($chair);
        $result = $this->withSession()->post('/chairperson/offerings/merge-suggestions/accept', $this->withCsrf([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'subject_code' => 'RED999', 'offering_id' => null, 'target_section_id' => $sectionB,
            'meetings' => [],
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertFalse($body['ok']);
    }

    public function testPlotDemandCarriesExistingScheduleForward(): void
    {
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $this->actingAsChair($chair);
        $result = $this->withSession()->post('/chairperson/offerings/plot-demand', $this->withCsrf([
            'academic_year' => self::TERM, 'subject_code' => 'ITE101', 'section_id' => $sectionA,
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'], $body['error'] ?? '');
        $this->assertTrue($body['schedule_carried']);
    }

    public function testCreateMergeSkipsCarryWhenSelectedSectionsConflict(): void
    {
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedProgramForSubDept('BSIT', $subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');
        db_connect()->table('plotted_schedules')->insert([ /* A at 08:00-09:30 Room 201 */
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);
        db_connect()->table('plotted_schedules')->insert([ /* B at a DIFFERENT time */
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
            'day' => 1, 'start_time' => '13:00:00', 'end_time' => '14:30:00', 'time_label' => '13:00-14:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 9', 'status' => 'published',
        ]);

        $this->actingAsChair($chair);
        // section_ids must be strings here, not raw ints: FeatureTestTrait's JSON
        // body path leaves array elements unfiltered by filter_var() (only
        // top-level scalars get coerced), so a raw int leaf trips the
        // InvalidChars filter's mb_check_encoding() call with a TypeError.
        $result = $this->withSession()->post('/chairperson/offerings/create', $this->withCsrf([
            'academic_year' => self::TERM, 'subject_code' => 'ITE101', 'section_ids' => [(string) $sectionA, (string) $sectionB],
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'], $body['error'] ?? '');
        $this->assertFalse($body['schedule_carried']);
    }

    public function testNeedsPlottingFlagsSectionsAlreadyOnTheGrid(): void
    {
        $subDept = $this->newSubDept();
        $chair = $this->seedChairperson($subDept);
        $this->seedProgramForSubDept('BSIT', $subDept);
        $programId = (int) db_connect()->table('programs')->where('sub_department_id', $subDept)->get()->getRowArray()['program_id'];
        $subjectId = $this->seedSubject('ITE101', $subDept);

        // Active term: needsPlottingList() resolves it live via
        // AcademicYearModel::activeFormatted(), so a real active row must
        // exist — self::TERM is just a short literal, not something any
        // seeder makes active on its own.
        db_connect()->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);
        $activeYear = (new AcademicYearModel())->activeFormatted();
        $this->assertNotNull($activeYear);

        // Curriculum demand fixture (mirrors SubjectDemandServiceTest::makeExpectedStudent()):
        // a 2nd-year, 1st-semester requirement plus one active student sectioned
        // into it, so summary()'s unassigned_count > 0 gate at
        // OfferingController.php:219-221 actually lets section A's row through.
        db_connect()->table('curriculum')->insert([
            'program_id' => $programId, 'curriculum_code' => 'C1', 'curriculum_name' => 'C1',
            'academic_year' => '2026-2027', 'status' => 'published', 'is_current' => 1, 'is_active' => 1,
        ]);
        $curriculumId = (int) db_connect()->insertID();
        db_connect()->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculumId, 'subject_id' => $subjectId, 'year_level' => 2, 'semester' => 1,
            'is_required' => 1, 'is_active' => 1,
        ]);

        $sectionA = $this->seedSection('BSIT', 'A', AcademicYearModel::yearOnly($activeYear));

        $studentId = 980001;
        $this->seedUsers($studentId);
        db_connect()->table('users')->where('id', $studentId)->update(['section_id' => $sectionA]);
        db_connect()->table('students')->insert([
            'user_id' => $studentId, 'applicant_id' => 'APP' . $studentId, 'student_number' => 'SN' . $studentId,
            'program_id' => $programId, 'curriculum_id' => $curriculumId, 'student_status' => 'active',
        ]);

        // Section A already has a real class time plotted on the chairperson's
        // own Schedule Plotting grid — a legacy (offering_id IS NULL) row,
        // never backed by a course_offerings row at all.
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => $activeYear, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $this->actingAsChair($chair);
        $result = $this->withSession()->get('/chairperson/offerings');

        // The Needs Plotting <select>'s option text for section A's row carries the
        // new suffix (offerings.php:142-144 renders options server-side).
        $result->assertSee('Already on the schedule');
    }
}
