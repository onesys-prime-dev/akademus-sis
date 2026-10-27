<?php

use App\Models\CourseOfferingModel;
use App\Models\PlottedScheduleModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Merge + schedule unification: the pieces that let a section's already-plotted
 * legacy class schedule carry forward into a newly created merged offering.
 *
 * @internal
 */
final class CourseOfferingMergeScheduleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private int $nextChairId = 60000;
    private int $deptSeq     = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUsers(100, 101, 102, 200, 201, 202);
    }

    private function makeSection(string $name, ?int $capacity = null): array
    {
        $sections = new SectionModel();

        $sections->insert([
            'academic_year' => 'AY2026-2027',
            'program'       => 'BS Information Technology',
            'year_level'    => '1st Year',
            'section'       => $name,
            'capacity'      => $capacity,
        ]);

        return $sections->find($sections->getInsertID());
    }

    private function seedSubject(string $subjectCode): void
    {
        $subjects = db_connect()->table('subjects');

        if ($subjects->where('subject_code', $subjectCode)->countAllResults() > 0) {
            return;
        }

        $subjects->insert([
            'subject_code' => $subjectCode,
            'subject_name' => $subjectCode . ' Title',
        ]);
    }

    private function plotSubject(array $section, string $subjectCode): void
    {
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => $section['academic_year'],
            'program'       => $section['program'],
            'year_level'    => $section['year_level'],
            'section'       => $section['section'],
            'day'           => 0,
            'start_time'    => '08:00:00',
            'end_time'      => '10:00:00',
            'time_label'    => '08:00-10:00',
            'subject_code'  => $subjectCode,
            'subject_title' => $subjectCode . ' Title',
            'room'          => 'Room 101',
            'theme'         => 'blue',
            'status'        => 'published',
        ]);
    }

    /**
     * A chairperson who manages the given subject: the subject is linked to a
     * fresh sub-department and the chair belongs to that same sub-department.
     */
    private function seedChairForLegacyTest(string $subjectCode): int
    {
        $collegeId = $this->deptSeq++;
        $subDeptId = $this->deptSeq++;
        db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => 'Unit ' . $subDeptId]);
        db_connect()->table('subjects')->where('subject_code', $subjectCode)->update(['sub_department_id' => $subDeptId]);

        $userId = $this->nextChairId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['sub_department_id' => $subDeptId]);

        return $userId;
    }

    public function testLegacyRowsForReturnsOnlyMatchingNonOfferingRows(): void
    {
        $model = new PlottedScheduleModel();
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => 'AY2026-2027', 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'Intro', 'room' => 'Room 201', 'status' => 'published',
        ]);
        // A different section's row must NOT come back.
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => 'AY2026-2027', 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => 'B',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'Intro', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $rows = $model->legacyRowsFor('AY2026-2027', 'BSIT', '1st Year', 'A', 'ITE101');

        $this->assertCount(1, $rows);
        $this->assertSame('Room 201', $rows[0]['room']);
    }

    public function testLegacyRowsForExcludesOfferingLinkedRows(): void
    {
        $this->seedSubject('ITE101');
        $sectionA = $this->makeSection('A');
        $sectionB = $this->makeSection('B');
        $chairId  = $this->seedChairForLegacyTest('ITE101');

        $offering = (new CourseOfferingModel())->createMerge('AY2026-2027', 'ITE101',
            [$sectionA['section_id'], $sectionB['section_id']], null);
        $this->assertTrue($offering['ok'], $offering['error'] ?? '');

        $sync = (new PlottedScheduleModel())->syncOfferingSchedule($offering['offering_id'], $chairId,
            [['day' => 0, 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30', 'room' => 'Room 201', 'faculty_id' => null]]);
        $this->assertTrue($sync['ok'], $sync['error'] ?? '');

        // Use the section's real tuple so the only reason for an empty result is
        // that the matching row is offering-linked (program/year_level/section NULL).
        $rows = (new PlottedScheduleModel())->legacyRowsFor('AY2026-2027', $sectionA['program'], $sectionA['year_level'], $sectionA['section'], 'ITE101');

        $this->assertSame([], $rows);
    }

    public function testCarryingMergeAutoCarriesIdenticalExistingSchedule(): void
    {
        $a = $this->makeSection('A'); $b = $this->makeSection('B');
        $this->seedSubject('ITE101');
        $this->plotSubject($a, 'ITE101'); // identical time/room for both, via the existing helper
        $this->plotSubject($b, 'ITE101');

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $a['academic_year'], 'ITE101', [$a['section_id'], $b['section_id']], null, 2, /* actorId */ $this->seedChairForLegacyTest('ITE101')
        );

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertTrue($result['schedule_carried']);
        $this->assertSame(0, db_connect()->table('plotted_schedules')
            ->where('offering_id', null)->where('subject_code', 'ITE101')->countAllResults()); // old legacy rows gone
        $this->assertSame(1, db_connect()->table('plotted_schedules')
            ->where('offering_id', $result['offering_id'])->countAllResults()); // one offering-scoped row now covers both
    }

    public function testCarryingMergeSkipsCarryOnConflictingExistingSchedules(): void
    {
        $a = $this->makeSection('A'); $b = $this->makeSection('B');
        $this->seedSubject('ITE101');
        $this->plotSubject($a, 'ITE101'); // 08:00-10:00 Room 101, from the shared helper
        db_connect()->table('plotted_schedules')->insert([ // B at a DIFFERENT time
            'academic_year' => $b['academic_year'], 'program' => $b['program'], 'year_level' => $b['year_level'], 'section' => $b['section'],
            'day' => 1, 'start_time' => '13:00:00', 'end_time' => '15:00:00', 'time_label' => '13:00-15:00',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 9', 'status' => 'published',
        ]);

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $a['academic_year'], 'ITE101', [$a['section_id'], $b['section_id']], null, 2, $this->seedChairForLegacyTest('ITE101')
        );

        $this->assertTrue($result['ok']); // the MERGE still succeeds
        $this->assertFalse($result['schedule_carried']);
        $this->assertSame('conflicting_existing_schedules', $result['schedule_carry_skipped_reason']);
        $this->assertSame(2, db_connect()->table('plotted_schedules')
            ->where('offering_id', null)->where('subject_code', 'ITE101')->countAllResults()); // both legacy rows left untouched
        $this->assertSame(0, db_connect()->table('plotted_schedules')
            ->where('offering_id', $result['offering_id'])->countAllResults()); // nothing carried
    }

    public function testCarryingMergeWithNoExistingScheduleBehavesLikePlainMerge(): void
    {
        $a = $this->makeSection('A'); $b = $this->makeSection('B');
        $this->seedSubject('ITE101');

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $a['academic_year'], 'ITE101', [$a['section_id'], $b['section_id']], null, 2, $this->seedChairForLegacyTest('ITE101')
        );

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['schedule_carried']);
        $this->assertArrayNotHasKey('schedule_carry_skipped_reason', $result); // "nothing to carry" is not a conflict
    }

    public function testCarryingMergeSingleSectionCarriesItsOwnSchedule(): void
    {
        $a = $this->makeSection('A');
        $this->seedSubject('ITE101');
        $this->plotSubject($a, 'ITE101');

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $a['academic_year'], 'ITE101', [$a['section_id']], null, 1, $this->seedChairForLegacyTest('ITE101')
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['schedule_carried']);
    }

    public function testCarryingMergeTreatsSameTimeDifferentFacultyAsConflicting(): void
    {
        $a = $this->makeSection('A'); $b = $this->makeSection('B');
        $this->seedSubject('ITE101');
        db_connect()->table('plotted_schedules')->insert([ // A: 08:00-09:30 Room 201, Prof. Cruz
            'academic_year' => $a['academic_year'], 'program' => $a['program'], 'year_level' => $a['year_level'], 'section' => $a['section'],
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'prof' => 'cruz', 'status' => 'published',
        ]);
        db_connect()->table('plotted_schedules')->insert([ // B: SAME time/room, DIFFERENT faculty
            'academic_year' => $b['academic_year'], 'program' => $b['program'], 'year_level' => $b['year_level'], 'section' => $b['section'],
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'prof' => 'santos', 'status' => 'published',
        ]);

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $a['academic_year'], 'ITE101', [$a['section_id'], $b['section_id']], null, 2, $this->seedChairForLegacyTest('ITE101')
        );

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['schedule_carried']);
        $this->assertSame('conflicting_existing_schedules', $result['schedule_carry_skipped_reason']);
    }

    public function testCarryingMergeUsesTermScopedAcademicYearNotSectionsYearOnlyColumn(): void
    {
        // sections.academic_year is year-only ("AY2026-2027"); plotted_schedules rows
        // (and the $academicYear this method is called with) are term-scoped
        // ("AY2026-2027 · 1st Semester"). The legacy row below is keyed to the
        // TERM-scoped string, proving the carry-forward query must use that, not
        // the section's own (year-only) academic_year column.
        $termScoped = 'AY2026-2027 · 1st Semester';
        $a = $this->makeSection('A'); // makeSection() stores 'AY2026-2027' on the section row itself
        $this->seedSubject('ITE101');
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => $termScoped, 'program' => $a['program'], 'year_level' => $a['year_level'], 'section' => $a['section'],
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $termScoped, 'ITE101', [$a['section_id']], null, 1, $this->seedChairForLegacyTest('ITE101')
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['schedule_carried']); // fails if the method mistakenly queried using $a['academic_year'] instead
    }

    public function testCarryingMergePropagatesUnderlyingCreateMergeFailureWithNoSideEffects(): void
    {
        $a = $this->makeSection('A');
        $this->seedSubject('ITE101');
        $this->plotSubject($a, 'ITE101');

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $a['academic_year'], 'ITE101', [$a['section_id']], null, 2, $this->seedChairForLegacyTest('ITE101') // minSections=2, only 1 given
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(1, db_connect()->table('plotted_schedules')
            ->where('offering_id', null)->countAllResults()); // legacy row untouched — nothing happened
    }

    public function testCarryingMergeSkipsCarryWhenSyncRejectsRoomConflict(): void
    {
        // A and B agree on a single schedule (so the method reaches the
        // syncOfferingSchedule() call at all), but that exact room/time is
        // already taken by a THIRD, unrelated offering — syncOfferingSchedule()'s
        // own roomAndFacultyConflicts() check rejects it before it ever opens
        // its own transaction. The merge must still succeed, nothing carried,
        // and — unlike the 2+-signature conflict case — no
        // schedule_carry_skipped_reason key (that's spec'd only for
        // conflicting EXISTING schedules between the merged sections
        // themselves, not a rejection by the scheduling engine).
        $a = $this->makeSection('A'); $b = $this->makeSection('B');
        $this->seedSubject('ITE101');
        $this->plotSubject($a, 'ITE101'); // 08:00-10:00 Room 101
        $this->plotSubject($b, 'ITE101'); // identical — agrees with A

        $this->seedSubject('ITE999');
        $otherOffering = new CourseOfferingModel();
        $otherOffering->insert(['academic_year' => $a['academic_year'], 'subject_code' => 'ITE999', 'subject_id' => null, 'capacity' => null]);
        $otherOfferingId = $otherOffering->getInsertID();

        db_connect()->table('plotted_schedules')->insert([
            'offering_id' => $otherOfferingId,
            'academic_year' => $a['academic_year'], 'program' => null, 'year_level' => null, 'section' => null,
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'time_label' => '08:00-10:00',
            'subject_code' => 'ITE999', 'subject_title' => 'ITE999 Title', 'room' => 'Room 101', 'status' => 'published',
        ]);

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $a['academic_year'], 'ITE101', [$a['section_id'], $b['section_id']], null, 2, $this->seedChairForLegacyTest('ITE101')
        );

        $this->assertTrue($result['ok'], $result['error'] ?? ''); // the merge still succeeds
        $this->assertFalse($result['schedule_carried']);
        $this->assertArrayNotHasKey('schedule_carry_skipped_reason', $result);
        $this->assertSame(2, db_connect()->table('plotted_schedules')
            ->where('offering_id', null)->where('subject_code', 'ITE101')->countAllResults()); // both legacy rows survive — the delete was rolled back
        $this->assertSame(0, db_connect()->table('plotted_schedules')
            ->where('offering_id', $result['offering_id'])->countAllResults()); // nothing carried onto the new offering
    }

    public function testCarryingMergeCarriesFacultyUsernameThroughToTheNewOfferingRow(): void
    {
        $a = $this->makeSection('A'); $b = $this->makeSection('B');
        $this->seedSubject('ITE101');
        $facultyUserId = 90001;
        $this->seedUsers($facultyUserId);
        $username = 'testuser' . $facultyUserId;

        foreach ([$a, $b] as $section) {
            db_connect()->table('plotted_schedules')->insert([
                'academic_year' => $section['academic_year'], 'program' => $section['program'], 'year_level' => $section['year_level'], 'section' => $section['section'],
                'day' => 0, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'time_label' => '08:00-10:00',
                'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 101', 'prof' => $username, 'status' => 'published',
            ]);
        }

        $result = (new CourseOfferingModel())->createMergeCarryingSchedule(
            $a['academic_year'], 'ITE101', [$a['section_id'], $b['section_id']], null, 2, $this->seedChairForLegacyTest('ITE101')
        );

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertTrue($result['schedule_carried']);
        $carried = db_connect()->table('plotted_schedules')->where('offering_id', $result['offering_id'])->get()->getRowArray();
        $this->assertSame($username, $carried['prof']);
    }
}
