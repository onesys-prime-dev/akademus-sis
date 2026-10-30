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
 * Live bug: an offering-linked plotted_schedules row always has NULL
 * section/year_level/program (by design — see PlottedScheduleModel's
 * docblock). ChairpersonController::rowToBlock() used to pass those nulls
 * straight through, and the client (chairperson-plotting.js's
 * overviewSectionLabel()/overviewClassRow()) filled the gap by falling back
 * to the VIEWER'S OWN currently-selected section/program. That made an
 * offering's class look like it belonged to whichever section a
 * chairperson happened to be looking from — including a fully UNATTACHED
 * offering (every section unmerged from it, schedule left behind — see
 * CourseOfferingModel::unmergeSection()), which isn't any section's
 * commitment any more. A chairperson seeing "B · 2nd Yr" on a class with
 * zero actual connection to section B understandably assumed a real
 * conflict existed (or didn't) where none did.
 *
 * @internal
 */
final class RoomOverviewUnattachedOfferingLabelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testAnUnattachedOfferingsScheduleIsNeverLabeledWithTheViewersOwnSection(): void
    {
        $db = db_connect();
        $db->table('departments')->insert(['dept_id' => 1, 'code' => 'COL1', 'name' => 'College 1']);
        $db->table('sub_departments')->insert(['sub_dept_id' => 2, 'college_id' => 1, 'code' => 'SD1', 'name' => 'Unit 1']);

        $chairId = 70601;
        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => 2]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        $db->table('programs')->insert(['program_id' => 5301, 'sub_department_id' => 2, 'program_code' => 'P5301', 'program_name' => 'Viewer Program', 'is_active' => 1]);
        $db->table('subjects')->insert(['subject_code' => 'ITE101', 'subject_name' => 'ITE101 Title', 'sub_department_id' => 2]);

        $term = AcademicYearModel::format('1', '1st');
        $db->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);
        $db->table('rooms')->insert(['name' => 'Room 101', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '1st Floor', 'is_active' => 1]);

        $curriculum = (new CurriculumModel())->liveFor(5301);
        $subjectId  = (int) $db->table('subjects')->where('subject_code', 'ITE101')->get()->getRowArray()['subject_id'];
        $db->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculum['curriculum_id'], 'subject_id' => $subjectId,
            'year_level' => 1, 'semester' => 1, 'is_active' => 1,
        ]);

        $sectionYear = AcademicYearModel::yearOnly($term);
        $sections    = new SectionModel();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Viewer Program', 'year_level' => '1st Year', 'section' => 'A']);
        $viewerSectionId = (int) $sections->getInsertID();

        // A SEPARATE, throwaway section merges into an offering, gets a real
        // schedule, then unmerges — leaving the offering's schedule behind,
        // attached to NO section at all (the documented "sectionless but
        // still scheduled" outcome).
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Viewer Program', 'year_level' => '3rd Year', 'section' => 'Z']);
        $throwawaySectionId = (int) $sections->getInsertID();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Viewer Program', 'year_level' => '3rd Year', 'section' => 'Y']);
        $throwawaySectionId2 = (int) $sections->getInsertID();

        $offerings = new CourseOfferingModel();
        $merge     = $offerings->createMerge($term, 'ITE101', [$throwawaySectionId, $throwawaySectionId2], null, 2);
        $this->assertTrue($merge['ok'], $merge['error'] ?? '');
        $offeringId = (int) $merge['offering_id'];

        $syncResult = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chairId, [
            ['day' => 0, 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30', 'room' => 'Room 101', 'faculty_id' => null],
        ]);
        $this->assertTrue($syncResult['ok'], $syncResult['error'] ?? '');

        $offerings->unmergeSection($offeringId, $throwawaySectionId);
        $offerings->unmergeSection($offeringId, $throwawaySectionId2);
        $this->assertSame(0, (int) $db->table('offering_sections')->where('offering_id', $offeringId)->countAllResults());
        // Schedule is left behind, not deleted.
        $this->assertSame(1, (int) $db->table('plotted_schedules')->where('offering_id', $offeringId)->countAllResults());

        // Now view a COMPLETELY UNRELATED section's grid (Viewer Program,
        // 1st Year, A) — the orphaned ITE101 class still occupies Room 101
        // at the same time and must show up as a ghost "other" block (it's
        // a genuine room commitment), but must NEVER be labeled as if it
        // belonged to section A.
        $this->actingAs(auth()->getProvider()->findById($chairId));
        $result = $this->withSession()->get('/chairperson/schedule/state?' . http_build_query([
            'academic_year' => $term, 'program' => 'Viewer Program', 'year_level' => '1st Year', 'section' => 'A',
        ]));
        $result->assertStatus(200);

        $body   = json_decode($result->response()->getJSON(), true);
        $ghosts = array_values(array_filter($body['others'], static fn (array $b) => $b['code'] === 'ITE101'));
        $this->assertNotEmpty($ghosts, 'The orphaned offering\'s room commitment must still show up as a ghost block.');
        $this->assertNotSame('A', $ghosts[0]['section'], 'An unattached offering must never be labeled with the VIEWER\'s own section.');
        $this->assertSame('Unattached', $ghosts[0]['year_level']);
    }
}
