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
 * Reproduces a live bug: the Schedule Plotting grid renders a merged
 * subject's class by merging its offering-linked plotted_schedules row into
 * the section's own view (PlottedScheduleModel::forContext()). Any grid save
 * from that page (ChairpersonController::sync()) resubmits every block it
 * currently renders, including that merged one — which, before this fix,
 * sync() wrote straight back out as a brand-new LEGACY row, duplicating the
 * offering's own schedule on the section's grid (doubled hours, a false
 * "Section conflict: X and X overlap" error, and a faculty load bar counting
 * the same class twice).
 *
 * @internal
 */
final class ChairpersonSyncMergedSubjectTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    /** @return array{chairId: int, sectionA: int, sectionB: int, offeringId: int, term: string} */
    private function seedMergedOffering(): array
    {
        $collegeId = 1;
        $subDeptId = 2;
        db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL1', 'name' => 'College 1']);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD1', 'name' => 'Unit 1']);

        $chairId = 70001;
        $this->seedUsers($chairId);
        db_connect()->table('users')->where('id', $chairId)->update(['sub_department_id' => $subDeptId]);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        db_connect()->table('programs')->insert([
            'program_id' => 3001, 'sub_department_id' => $subDeptId, 'program_code' => 'P3001', 'program_name' => 'BSIT', 'is_active' => 1,
        ]);

        db_connect()->table('subjects')->insert(['subject_code' => 'ITE101', 'subject_name' => 'ITE101 Title', 'sub_department_id' => $subDeptId]);
        $subjectId = (int) db_connect()->insertID();

        $term = AcademicYearModel::format('1', '1st');
        db_connect()->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);

        $curriculum = (new CurriculumModel())->liveFor(3001);
        db_connect()->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculum['curriculum_id'], 'subject_id' => $subjectId,
            'year_level' => 2, 'semester' => 1, 'is_active' => 1,
        ]);

        db_connect()->table('rooms')->insert(['name' => 'Room 201', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '2nd Floor', 'is_active' => 1]);

        $sectionYear = AcademicYearModel::yearOnly($term);

        $sections = new SectionModel();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A']);
        $sectionA = (int) $sections->getInsertID();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B']);
        $sectionB = (int) $sections->getInsertID();

        // Section B is already merged into an offering for ITE101, with a
        // real schedule — exactly the state the grid renders via
        // forContext()'s legacy+offering merge.
        $merge = (new CourseOfferingModel())->createMerge($term, 'ITE101', [$sectionA, $sectionB], null, 2);
        $this->assertTrue($merge['ok'], $merge['error'] ?? '');
        $offeringId = (int) $merge['offering_id'];

        $syncResult = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chairId, [
            ['day' => 0, 'start' => '08:00', 'end' => '09:30', 'time_label' => '08:00-09:30', 'room' => 'Room 201', 'faculty_id' => null],
        ]);
        $this->assertTrue($syncResult['ok'], $syncResult['error'] ?? '');

        $this->seeNumRecords(1, 'plotted_schedules', ['subject_code' => 'ITE101']);

        return ['chairId' => $chairId, 'sectionA' => $sectionA, 'sectionB' => $sectionB, 'offeringId' => $offeringId, 'term' => $term];
    }

    public function testSyncDoesNotDuplicateAMergedSubjectAsANewLegacyRow(): void
    {
        ['chairId' => $chairId, 'offeringId' => $offeringId, 'term' => $term] = $this->seedMergedOffering();

        // Grid save for section B, resubmitting exactly the block the grid is
        // currently showing — the same meeting the offering already owns.
        //
        // Sent as a raw JSON body (not via post()'s $params) because
        // FeatureTestTrait's $params path also populates $_POST verbatim,
        // and the InvalidChars filter chokes on a nested int ('day') inside
        // that array with a TypeError — a test-harness quirk (see
        // OfferingControllerMergeAcceptTest::
        // testCreateMergeSkipsCarryWhenSelectedSectionsConflict for the same
        // issue elsewhere), not something sync() itself can route around:
        // its block validation requires 'day' to be a real int.
        $this->actingAs(auth()->getProvider()->findById($chairId));
        $payload = [
            'context' => [
                'academic_year' => $term, 'program' => 'BSIT', 'year_level' => '2nd Year',
                'semester' => '1st', 'section' => 'B',
            ],
            'blocks' => [
                ['code' => 'ITE101', 'time_label' => '08:00-09:30', 'room' => 'Room 201', 'start' => '08:00', 'end' => '09:30', 'day' => 0],
            ],
            csrf_token() => csrf_hash(),
        ];
        $result = $this->withSession()
            ->withBody(json_encode($payload))
            ->withBodyFormat('json')
            ->post('/chairperson/schedule/sync');

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'] ?? false, $body['error'] ?? json_encode($body));

        // Still exactly one row for ITE101: the offering's own. No second,
        // legacy (offering_id IS NULL) row for section B.
        $this->seeNumRecords(1, 'plotted_schedules', ['subject_code' => 'ITE101']);
        $this->seeInDatabase('plotted_schedules', ['offering_id' => $offeringId, 'subject_code' => 'ITE101']);
        $this->dontSeeInDatabase('plotted_schedules', ['offering_id' => null, 'subject_code' => 'ITE101', 'section' => 'B']);
    }

    public function testSyncOmittingAMergedBlockUnmergesTheSectionInsteadOfLeavingItStuck(): void
    {
        // Live bug: removeBlock() in chairperson-plotting.js only ever
        // spliced the block out of the client's local array and resaved —
        // for a merged subject, that resubmit never touches the offering's
        // own row (replaceDraft() only manages this section's LEGACY rows),
        // so the "removed" class silently reappeared on next reload, with
        // no error and no way to actually clear it from the section.
        ['chairId' => $chairId, 'sectionB' => $sectionB, 'offeringId' => $offeringId, 'term' => $term] = $this->seedMergedOffering();

        $this->actingAs(auth()->getProvider()->findById($chairId));
        $payload = [
            'context' => [
                'academic_year' => $term, 'program' => 'BSIT', 'year_level' => '2nd Year',
                'semester' => '1st', 'section' => 'B',
            ],
            'blocks' => [], // the chairperson removed ITE101 from section B's grid
            csrf_token() => csrf_hash(),
        ];
        $result = $this->withSession()
            ->withBody(json_encode($payload))
            ->withBodyFormat('json')
            ->post('/chairperson/schedule/sync');

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'] ?? false, $body['error'] ?? json_encode($body));

        // Section B is really out of the merge now — not just hidden
        // locally until the next reload brings it back.
        $this->dontSeeInDatabase('offering_sections', ['offering_id' => $offeringId, 'section_id' => $sectionB]);
        // Section A is still attached; the offering and its schedule survive.
        $this->seeNumRecords(1, 'plotted_schedules', ['subject_code' => 'ITE101']);
        $this->seeInDatabase('plotted_schedules', ['offering_id' => $offeringId, 'subject_code' => 'ITE101']);
    }
}
