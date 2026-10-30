<?php

use App\Models\AcademicYearModel;
use App\Models\CourseOfferingModel;
use App\Models\CurriculumModel;
use App\Models\PlottedScheduleModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Live bug: a room genuinely booked by a merged/offering-linked class (e.g.
 * ITE 101 in Room 201, Mon 8:00-9:30, via course_offerings) showed as
 * "Free all day" in the Room Overview and room-picker modals — both of
 * which call PlottedScheduleModel::forOtherContexts() WITH $deptPrograms —
 * while the save-time conflict check (sync(), which calls it WITHOUT
 * $deptPrograms) correctly rejected plotting into that same room/time as
 * "Room conflict." The display and the save-time check disagreed because
 * forOtherContexts() only merged in offering-linked rows on the
 * $deptPrograms === null path.
 *
 * @internal
 */
final class PlottedScheduleForOtherContextsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testOfferingLinkedRowsAreIncludedEvenWithDeptProgramsScoped(): void
    {
        $db = db_connect();
        $db->table('departments')->insert(['dept_id' => 1, 'code' => 'COL1', 'name' => 'College 1']);
        $db->table('sub_departments')->insert(['sub_dept_id' => 2, 'college_id' => 1, 'code' => 'SD1', 'name' => 'Unit 1']);

        $chairId = 70301;
        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => 2]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        $db->table('programs')->insert(['program_id' => 5001, 'sub_department_id' => 2, 'program_code' => 'P5001', 'program_name' => 'Owning Program', 'is_active' => 1]);

        $db->table('subjects')->insert(['subject_code' => 'ITE101', 'subject_name' => 'ITE101 Title', 'sub_department_id' => 2]);

        $term = AcademicYearModel::format('1', '1st');
        $db->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);

        $curriculum = (new CurriculumModel())->liveFor(5001);
        $db->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculum['curriculum_id'], 'subject_id' => (int) $db->table('subjects')->where('subject_code', 'ITE101')->get()->getRowArray()['subject_id'],
            'year_level' => 1, 'semester' => 1, 'is_active' => 1,
        ]);

        $sectionYear = AcademicYearModel::yearOnly($term);
        $sections = new SectionModel();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Owning Program', 'year_level' => '1st Year', 'section' => 'A']);
        $sectionA = (int) $sections->getInsertID();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Owning Program', 'year_level' => '1st Year', 'section' => 'B']);
        $sectionB = (int) $sections->getInsertID();

        // Section A is merged into an offering with a real Room 201 Monday
        // slot — a genuine, live commitment on that room.
        $merge = (new CourseOfferingModel())->createMerge($term, 'ITE101', [$sectionA, $sectionB], null, 2);
        $this->assertTrue($merge['ok'], $merge['error'] ?? '');
        $syncResult = (new PlottedScheduleModel())->syncOfferingSchedule((int) $merge['offering_id'], $chairId, [
            ['day' => 0, 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30', 'room' => 'Room 201', 'faculty_id' => null],
        ]);
        $this->assertTrue($syncResult['ok'], $syncResult['error'] ?? '');

        // A DIFFERENT, unrelated program/section's view, scoped to a
        // department that doesn't own "Owning Program" at all — exactly
        // what the Room Overview / room picker pass as $deptPrograms.
        $others = (new PlottedScheduleModel())->forOtherContexts(
            $term,
            'Some Other Program',
            '2nd Year',
            'Z',
            ['Some Other Program'] // $deptPrograms: deliberately excludes "Owning Program"
        );

        $room201Monday = array_filter(
            $others,
            static fn (array $row) => $row['room'] === 'Room 201' && (int) $row['day'] === 0 && $row['subject_code'] === 'ITE101'
        );

        $this->assertNotEmpty(
            $room201Monday,
            'Room 201 is genuinely booked by the ITE101 offering — it must show up as occupied even in a department-scoped "others" query, or the Room Overview/picker will lie about its availability.'
        );
    }
}
