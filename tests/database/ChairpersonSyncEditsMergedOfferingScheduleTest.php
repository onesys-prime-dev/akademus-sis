<?php

use App\Models\AcademicYearModel;
use App\Models\CourseOfferingModel;
use App\Models\CurriculumModel;
use App\Models\PlottedScheduleModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Before this fix, ChairpersonController::sync() unconditionally dropped any
 * block whose code belonged to a merged offering — so dragging/resizing a
 * merged class's tile in the Schedule Plotting grid looked like it saved
 * (the request returned ok:true) but silently changed nothing: the offering's
 * stored schedule stayed exactly as it was. This reproduces a real edit (a
 * new room and a later start time) and asserts it actually lands on the
 * offering's own plotted_schedules row.
 *
 * @internal
 */
final class ChairpersonSyncEditsMergedOfferingScheduleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testEditingAMergedBlockInTheGridPersistsToTheOfferingsSchedule(): void
    {
        $db = db_connect();
        $db->table('colleges')->insert(['college_id' => 1, 'code' => 'COL1', 'name' => 'College 1']);
        $db->table('departments')->insert(['dept_id' => 2, 'college_id' => 1, 'code' => 'SD1', 'name' => 'Unit 1']);

        $chairId = 70601;
        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => 2]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        $db->table('programs')->insert(['program_id' => 6001, 'department_id' => 2, 'program_code' => 'P6001', 'program_name' => 'BSIT', 'is_active' => 1]);

        $db->table('subjects')->insert(['subject_code' => 'ITE101', 'subject_name' => 'ITE101 Title', 'department_id' => 2]);
        $subjectId = (int) $db->insertID();

        $term = AcademicYearModel::format('1', '1st');
        $db->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);

        $curriculum = (new CurriculumModel())->liveFor(6001);
        $db->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculum['curriculum_id'], 'subject_id' => $subjectId,
            'year_level' => 2, 'semester' => 1, 'is_active' => 1,
        ]);

        $db->table('rooms')->insert(['name' => 'Room 201', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '2nd Floor', 'is_active' => 1]);
        $db->table('rooms')->insert(['name' => 'Room 305', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '3rd Floor', 'is_active' => 1]);

        $sectionYear = AcademicYearModel::yearOnly($term);
        $sections    = new SectionModel();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A']);
        $sectionA = (int) $sections->getInsertID();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B']);
        $sectionB = (int) $sections->getInsertID();

        $merge = (new CourseOfferingModel())->createMerge($term, 'ITE101', [$sectionA, $sectionB], null, 2);
        $this->assertTrue($merge['ok'], $merge['error'] ?? '');
        $offeringId = (int) $merge['offering_id'];

        $syncResult = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chairId, [
            ['day' => 0, 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30', 'room' => 'Room 201', 'faculty_id' => null],
        ]);
        $this->assertTrue($syncResult['ok'], $syncResult['error'] ?? '');

        // Grid save for section B, moving the merged class to a new room and
        // a later start time — a real edit, not a resubmit of the same block.
        $this->actingAs(auth()->getProvider()->findById($chairId));
        $payload = [
            'context' => [
                'academic_year' => $term, 'program' => 'BSIT', 'year_level' => '2nd Year',
                'semester' => '1st', 'section' => 'B',
            ],
            'blocks' => [
                ['code' => 'ITE101', 'time_label' => '09:00-10:30', 'room' => 'Room 305', 'start' => '09:00', 'end' => '10:30', 'day' => 0],
            ],
            csrf_token() => csrf_hash(),
        ];
        $result = $this->withSession()
            ->withBody(json_encode($payload))
            ->withBodyFormat('json')
            ->post('/chairperson/schedule/sync');

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'] ?? false, $body['error'] ?? json_encode($body));

        $this->seeNumRecords(1, 'plotted_schedules', ['subject_code' => 'ITE101']);
        $this->seeInDatabase('plotted_schedules', [
            'offering_id' => $offeringId, 'subject_code' => 'ITE101',
            'room' => 'Room 305', 'start_time' => '09:00:00', 'end_time' => '10:30:00',
        ]);
        $this->dontSeeInDatabase('plotted_schedules', ['subject_code' => 'ITE101', 'room' => 'Room 201']);
    }
}
