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
 * Live bug: a section already attends a class through a merged offering
 * (e.g. ITE 101, Mon 8:00-9:30, via course_offerings) — but
 * ChairpersonController::sync()'s "within the section, no two classes may
 * overlap" check only ever looked at $blocks, which NEVER contains
 * offering-linked meetings (they're deliberately dropped from the payload —
 * their schedule belongs to the offering, not this save). That let a
 * chairperson plot an entirely different legacy subject for the SAME
 * section at the SAME day/time in a different room, with nothing catching
 * it: the section ended up committed to two classes at once.
 *
 * @internal
 */
final class ChairpersonSyncRejectsMergedOfferingOverlapTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testPlottingALegacySubjectOnTopOfTheSectionsOwnMergedOfferingMeetingIsRejected(): void
    {
        $db = db_connect();
        $db->table('departments')->insert(['dept_id' => 1, 'code' => 'COL1', 'name' => 'College 1']);
        $db->table('sub_departments')->insert(['sub_dept_id' => 2, 'college_id' => 1, 'code' => 'SD1', 'name' => 'Unit 1']);

        $chairId = 70501;
        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => 2]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        $db->table('programs')->insert(['program_id' => 5201, 'sub_department_id' => 2, 'program_code' => 'P5201', 'program_name' => 'Owning Program', 'is_active' => 1]);

        $db->table('subjects')->insert(['subject_code' => 'ITE101', 'subject_name' => 'ITE101 Title', 'sub_department_id' => 2]);
        $db->table('subjects')->insert(['subject_code' => 'RED103', 'subject_name' => 'RED103 Title', 'sub_department_id' => 2]);

        $term = AcademicYearModel::format('1', '1st');
        $db->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);
        $db->table('rooms')->insert(['name' => 'Room 101', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '1st Floor', 'is_active' => 1]);

        $curriculum = (new CurriculumModel())->liveFor(5201);
        foreach (['ITE101', 'RED103'] as $code) {
            $subjectId = (int) db_connect()->table('subjects')->where('subject_code', $code)->get()->getRowArray()['subject_id'];
            $db->table('curriculum_subjects')->insert([
                'curriculum_id' => $curriculum['curriculum_id'], 'subject_id' => $subjectId,
                'year_level' => 1, 'semester' => 1, 'is_active' => 1,
            ]);
        }

        $sectionYear = AcademicYearModel::yearOnly($term);
        $sections    = new SectionModel();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Owning Program', 'year_level' => '1st Year', 'section' => 'A']);
        $sectionA = (int) $sections->getInsertID();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Owning Program', 'year_level' => '1st Year', 'section' => 'B']);
        $sectionB = (int) $sections->getInsertID();

        // Section B already attends ITE101 Monday 8:00-9:30 through a merged
        // offering shared with section A.
        $merge = (new CourseOfferingModel())->createMerge($term, 'ITE101', [$sectionA, $sectionB], null, 2);
        $this->assertTrue($merge['ok'], $merge['error'] ?? '');
        $syncResult = (new PlottedScheduleModel())->syncOfferingSchedule((int) $merge['offering_id'], $chairId, [
            ['day' => 0, 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30', 'room' => 'Room 201', 'faculty_id' => null],
        ]);
        $this->assertTrue($syncResult['ok'], $syncResult['error'] ?? '');

        // Now try to plot a totally different subject for section B at the
        // exact same day/time, in a DIFFERENT room — must be rejected as a
        // section conflict, not accepted.
        $this->actingAs(auth()->getProvider()->findById($chairId));
        $payload = [
            'context' => [
                'academic_year' => $term, 'program' => 'Owning Program', 'year_level' => '1st Year',
                'semester' => '1st', 'section' => 'B',
            ],
            'blocks' => [
                ['code' => 'RED103', 'time_label' => '08:00-09:30', 'room' => 'Room 101', 'start' => '08:00', 'end' => '09:30', 'day' => 0],
            ],
            csrf_token() => csrf_hash(),
        ];
        $result = $this->withSession()
            ->withBody(json_encode($payload))
            ->withBodyFormat('json')
            ->post('/chairperson/schedule/sync');

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertFalse($body['ok'] ?? true, 'Expected the save to be rejected as a section conflict, not accepted.');
        $this->assertStringContainsString('RED103', $body['error'] ?? '');
        $this->assertStringContainsString('ITE101', $body['error'] ?? '');

        $this->dontSeeInDatabase('plotted_schedules', ['subject_code' => 'RED103']);
    }
}
