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
    protected $namespace = ['CodeIgniter\Shield', 'App'];

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
}
