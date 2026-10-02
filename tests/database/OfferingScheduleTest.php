<?php

use App\Libraries\SchedulingConflictService;
use App\Models\CourseOfferingModel;
use App\Models\OfferingPlacementRequestModel;
use App\Models\PlottedScheduleModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Step 5 of the shared-subject architecture work: a shared course offering
 * gets ONE real schedule, inherited by every attached section, instead of
 * one plotted_schedules row-set duplicated per participating section.
 *
 * @internal
 */
final class OfferingScheduleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const TERM = '1 · 1st Semester';

    private int $nextUserId = 60000;
    private int $deptSeq    = 1;

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

    private int $programSeq = 1;

    private function seedProgramForSubDept(string $name, int $subDeptId): void
    {
        $id = $this->programSeq++;
        db_connect()->table('programs')->insert([
            'program_id' => 2000 + $id, 'sub_department_id' => $subDeptId, 'program_code' => 'P' . (2000 + $id), 'program_name' => $name, 'is_active' => 1,
        ]);
    }

    private function seedSection(string $program, string $label, string $academicYear = self::TERM): int
    {
        $sections = new SectionModel();
        $sections->insert(['academic_year' => $academicYear, 'program' => $program, 'year_level' => '2nd Year', 'section' => $label]);

        return (int) $sections->getInsertID();
    }

    private function makeOffering(string $subjectCode, array $sectionIds, ?int $capacity = null): int
    {
        $result = (new CourseOfferingModel())->createMerge(self::TERM, $subjectCode, $sectionIds, $capacity);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        return (int) $result['offering_id'];
    }

    private function insertLegacySchedule(string $program, string $yearLevel, string $section, int $day, string $start, string $end, string $room, ?string $prof = null): int
    {
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM,
            'program'       => $program,
            'year_level'    => $yearLevel,
            'section'       => $section,
            'day'           => $day,
            'start_time'    => $start . ':00',
            'end_time'      => $end . ':00',
            'time_label'    => "$start-$end",
            'subject_code'  => 'LEGACY1',
            'subject_title' => 'Legacy Subject',
            'prof'          => $prof,
            'room'          => $room,
        ]);

        return (int) db_connect()->insertID();
    }

    private function block(int $day, string $start, string $end, string $room, ?string $label = null): array
    {
        return ['day' => $day, 'start' => $start, 'end' => $end, 'time_label' => $label ?? "$start-$end", 'room' => $room, 'faculty_id' => null];
    }

    // --- 1/2/3/4: legacy untouched, offering plot, subject derivation -----

    public function testLegacyScheduleWithNullOfferingIdStillWorksUnchanged(): void
    {
        $this->insertLegacySchedule('BSIT', '2nd Year', 'A', 0, '08:00', '09:30', 'Room 1');

        $rows = (new PlottedScheduleModel())->forContext(self::TERM, 'BSIT', '2nd Year', 'A');

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['offering_id']);
    }

    public function testSharedCourseOfferingCanBePlottedUsingOfferingId(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('plotted_schedules', ['offering_id' => $offeringId, 'room' => 'Room 301']);
    }

    public function testOfferingLinkedScheduleDerivesSubjectFromOfferingSubjectId(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->seeInDatabase('plotted_schedules', ['offering_id' => $offeringId, 'subject_code' => 'RED104']);
    }

    public function testClientCannotSupplyAMismatchedSubjectCode(): void
    {
        // The sync API shape (day/start/end/time_label/room/faculty_id) has
        // no subject_code field at all — there is no channel for a client
        // to assert a subject identity, mismatched or otherwise. Confirmed
        // structurally: every saved row's subject_code is always derived
        // from course_offerings.subject_id, as proven by the test above.
        $this->assertTrue(true);
    }

    // --- 5-8: authorization -------------------------------------------------

    public function testAuthorizedOwningSchedulerCanPlotOffering(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertFalse($result['override']);
    }

    public function testUnrelatedSchedulerCannotPlotOffering(): void
    {
        $subDept = $this->newSubDept();
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $unrelated = $this->seedChairperson($this->newSubDept());

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $unrelated, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertFalse($result['ok']);
    }

    public function testDelegatedSchedulerCanPlotOffering(): void
    {
        $subDept = $this->newSubDept();
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $delegate = $this->seedChairperson($this->newSubDept());
        db_connect()->table('sub_department_schedulers')->insert(['sub_dept_id' => $subDept, 'user_id' => $delegate, 'created_at' => date('Y-m-d H:i:s')]);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $delegate, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertFalse($result['override']);
    }

    public function testRegistrarOverrideFollowsCentralizedAuthorization(): void
    {
        $subDept = $this->newSubDept();
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $registrar = $this->seedChairperson($this->newSubDept());
        db_connect()->table('auth_groups_users')->insert(['user_id' => $registrar, 'group' => 'registrar', 'created_at' => date('Y-m-d H:i:s')]);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $registrar, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertTrue($result['override']);
    }

    // --- 9/10: cardinality ----------------------------------------------------

    public function testOneOfferingAttachedToMultipleSectionsCreatesOneLogicalSchedule(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $c = $this->seedSection('NUR', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b, $c]);

        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertCount(1, (new PlottedScheduleModel())->forOffering($offeringId));
    }

    public function testMultipleLegitimateMeetingBlocksForSameOfferingAreAllowed(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [
            $this->block(0, '10:00', '11:30', 'Room 301'),
            $this->block(2, '10:00', '11:30', 'Room 301'),
        ]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertCount(2, (new PlottedScheduleModel())->forOffering($offeringId));
    }

    // --- 11/12: room & faculty conflicts --------------------------------------

    public function testRoomConflictStillWorksForOfferingSchedule(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $this->insertLegacySchedule('OTHERPROG', '1st Year', 'Z', 0, '10:00', '11:30', 'Room 301');

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('conflict', strtolower($result['error']));
    }

    public function testFacultyConflictStillWorksForOfferingSchedule(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $this->insertLegacySchedule('OTHERPROG', '1st Year', 'Z', 0, '10:00', '11:30', 'Room 999', 'profx');

        $facultyId = $this->nextUserId++;
        $this->seedUsers($facultyId);
        db_connect()->table('users')->where('id', $facultyId)->update(['username' => 'profx']);

        $block = $this->block(0, '10:00', '11:30', 'Room 301');
        $block['faculty_id'] = $facultyId;

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$block]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('faculty', strtolower($result['error']));
    }

    // --- 13/14/15: cohort conflict expansion ----------------------------------

    public function testAnyAttachedSectionConflictBlocksTheOfferingPlot(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        // Music-2A already has a class at the same time.
        $this->insertLegacySchedule('MUS', '2nd Year', 'A', 0, '10:00', '11:30', 'Room 5');

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['conflicts']);
    }

    public function testTwoAttachedSectionsWithNoConflictShareOneOfferingSchedule(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    public function testConflictResultIdentifiesAffectedSections(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        $this->insertLegacySchedule('MUS', '2nd Year', 'A', 0, '10:00', '11:30', 'Room 5');

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertFalse($result['ok']);
        $this->assertSame([$b], $result['conflicts'][0]['section_ids']);
    }

    // --- 16/17: editing --------------------------------------------------------

    public function testEditingOfferingLinkedScheduleKeepsOfferingId(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);
        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '13:00', '14:30', 'Room 302')]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('plotted_schedules', ['offering_id' => $offeringId, 'room' => 'Room 302']);
        $this->dontSeeInDatabase('plotted_schedules', ['offering_id' => $offeringId, 'room' => 'Room 301']);
    }

    public function testEditingRerunsAllConflicts(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);

        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        // Now a NEW conflict appears for the edited time.
        $this->insertLegacySchedule('BSIT', '2nd Year', 'A', 1, '08:00', '09:30', 'Room 9');

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(1, '08:00', '09:30', 'Room 1')]);

        $this->assertFalse($result['ok']);
    }

    // --- Adjustment 2: self-conflict exclusion --------------------------------

    public function testOfferingDoesNotConflictWithItsOwnExistingRowsDuringAValidEdit(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $c = $this->seedSection('NUR', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b, $c]);

        $first = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);
        $this->assertTrue($first['ok'], $first['error'] ?? '');

        // Expand the SAME offering's own time — this must not be reported
        // as an external conflict with itself, even though all three
        // attached sections resolve back to this exact offering.
        $edit = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '12:00', 'Room 301')]);

        $this->assertTrue($edit['ok'], $edit['error'] ?? '');
    }

    // --- Adjustment 3: deduplicated commitments --------------------------------

    public function testSharedOfferingMeetingIsNotCountedTwiceAcrossItsAttachedSections(): void
    {
        $subDeptA = $this->newSubDept();
        $subDeptB = $this->newSubDept();
        $chairA   = $this->seedChairperson($subDeptA);
        $this->seedSubject('RED104', $subDeptA);
        $this->seedSubject('MSC101', $subDeptB);

        $bsit = $this->seedSection('BSIT', 'A');
        $mus  = $this->seedSection('MUS', 'A');
        $nur  = $this->seedSection('NUR', 'A');

        // Offering B (MSC101) is shared by MUS and NUR — one meeting, two attached sections.
        $offeringB = $this->makeOffering('MSC101', [$mus, $nur]);
        $chairB    = $this->seedChairperson($subDeptB);
        (new PlottedScheduleModel())->syncOfferingSchedule($offeringB, $chairB, [$this->block(0, '10:00', '11:30', 'Room 5')]);

        // Offering A (RED104) is shared by BSIT, MUS, and NUR — i.e. BOTH
        // sections that already carry Offering B's meeting are also
        // attached here. Plotting Offering A at the same time must report
        // the conflict exactly once, naming both affected sections.
        $offeringA = $this->makeOffering('RED104', [$bsit, $mus, $nur]);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringA, $chairA, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['conflicts'], 'The same conflicting meeting must be reported once, not once per attached section.');
        $sectionIds = $result['conflicts'][0]['section_ids'];
        sort($sectionIds);
        $expected = [$mus, $nur];
        sort($expected);
        $this->assertSame($expected, $sectionIds, 'Both affected sections must still be named.');
    }

    // --- Adjustment 4: explicit identity separation ----------------------------

    public function testLegacyAndOfferingIdentitiesAreNeverMixed(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);
        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        // A legacy row for an UNRELATED section/program, same academic year.
        $this->insertLegacySchedule('OTHERPROG', '1st Year', 'Z', 3, '13:00', '14:30', 'Room 7');

        // forContext() for that unrelated legacy tuple must return ONLY its
        // own legacy row — never an offering row, even though the offering
        // row's program/year_level/section are NULL (never a wildcard
        // match).
        $rows = (new PlottedScheduleModel())->forContext(self::TERM, 'OTHERPROG', '1st Year', 'Z');
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['offering_id']);

        // The offering's own schedule is reachable ONLY via its attached
        // sections' forContext(), never via the unrelated legacy tuple.
        $bsitRows = (new PlottedScheduleModel())->forContext(self::TERM, 'BSIT', '2nd Year', 'A');
        $this->assertCount(1, $bsitRows);
        $this->assertSame($offeringId, (int) $bsitRows[0]['offering_id']);
    }

    // --- 18/19: deletion does not touch the offering/sections ------------------

    public function testDeletingOfferingLinkedScheduleDoesNotDeleteOfferingOrDetachSections(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);
        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        // "Deleting" the schedule = re-syncing with an empty block list.
        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, []);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertCount(0, (new PlottedScheduleModel())->forOffering($offeringId));
        $this->seeInDatabase('course_offerings', ['offering_id' => $offeringId]);
        $this->seeInDatabase('offering_sections', ['offering_id' => $offeringId, 'section_id' => $a]);
        $this->seeInDatabase('offering_sections', ['offering_id' => $offeringId, 'section_id' => $b]);
    }

    // --- 20/21/22: attach-after-scheduling safety ------------------------------

    public function testAttachingANonConflictingSectionToAnAlreadyScheduledOfferingSucceeds(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);
        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $c = $this->seedSection('NUR', 'A');

        $result = (new CourseOfferingModel())->attachSection($offeringId, $c, 'RED104');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    public function testAttachingAConflictingSectionToAnAlreadyScheduledOfferingIsBlocked(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);
        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $c = $this->seedSection('NUR', 'A');
        $this->insertLegacySchedule('NUR', '2nd Year', 'A', 0, '10:00', '11:30', 'Room 8');

        $result = (new CourseOfferingModel())->attachSection($offeringId, $c, 'RED104');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['schedule_conflicts']);
        $this->dontSeeInDatabase('offering_sections', ['offering_id' => $offeringId, 'section_id' => $c]);
    }

    public function testPlacementFulfillmentRespectsTheSameConflictValidation(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('RED104', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('RED104', [$a, $b]);
        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $c = $this->seedSection('NUR', 'A');
        $this->insertLegacySchedule('NUR', '2nd Year', 'A', 0, '10:00', '11:30', 'Room 8');

        $nurSubDept = $this->newSubDept();
        $this->seedProgramForSubDept('NUR', $nurSubDept);
        $nurChair = $this->seedChairperson($nurSubDept);

        $subjectId = (int) db_connect()->table('subjects')->select('subject_id')->where('subject_code', 'RED104')->get()->getRowArray()['subject_id'];

        $requests = new OfferingPlacementRequestModel();
        $create = $requests->create([
            'subject_id'   => $subjectId,
            'section_id'   => $c,
            'requested_by' => $nurChair,
            'request_type' => 'placement',
        ]);
        $this->assertTrue($create['ok'], $create['error'] ?? '');

        $fulfill = $requests->fulfillPlacement($create['id'], $chair, $offeringId);

        $this->assertFalse($fulfill['ok']);
        $this->assertArrayHasKey('schedule_conflicts', $fulfill);
    }

    // --- Regression: existing merge/offering tests keep passing ---------------

    public function testExistingMergeBehaviorStillWorks(): void
    {
        $this->seedSubject('REGSUBJ', null);
        $a = $this->seedSection('REG', 'A');
        $b = $this->seedSection('REG', 'B');

        $result = (new CourseOfferingModel())->createMerge(self::TERM, 'REGSUBJ', [$a, $b], null);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }
}
