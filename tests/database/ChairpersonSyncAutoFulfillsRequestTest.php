<?php

use App\Models\AcademicYearModel;
use App\Models\CurriculumModel;
use App\Models\OfferingPlacementRequestModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * A borrowing department's pending "additional offering"/"conflict report"
 * request (Shared Subject Requests) and the owning chairperson directly
 * plotting that subject on the Schedule Plotting grid (ChairpersonController::
 * sync()) used to be two completely disconnected workflows: plotting the
 * class never touched the request, so it sat "pending" forever unless
 * someone separately clicked "Mark Fulfilled" on a request that had already,
 * in effect, been acted on. sync() now closes that loop itself (see
 * OfferingPlacementRequestModel::autoFulfillForDirectPlot()).
 *
 * @internal
 */
final class ChairpersonSyncAutoFulfillsRequestTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    /** @return array{chairId: int, requesterId: int, subjectId: int, sectionId: int, term: string} */
    private function seedOwnedSubjectWithBorrowedSectionDemand(): array
    {
        $db = db_connect();

        $db->table('departments')->insert(['dept_id' => 1, 'code' => 'COL1', 'name' => 'College 1']);
        $db->table('sub_departments')->insert(['sub_dept_id' => 2, 'college_id' => 1, 'code' => 'SD1', 'name' => 'Owning Unit']);
        $db->table('sub_departments')->insert(['sub_dept_id' => 3, 'college_id' => 1, 'code' => 'SD2', 'name' => 'Borrowing Unit']);

        $chairId = 70101;
        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => 2]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        $requesterId = 70102;
        $this->seedUsers($requesterId);
        $db->table('users')->where('id', $requesterId)->update(['sub_department_id' => 3]);
        $db->table('auth_groups_users')->insert(['user_id' => $requesterId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        // Owning unit's own program (so the chairperson has a "home"
        // program too, same as any real chairperson).
        $db->table('programs')->insert([
            'program_id' => 4001, 'sub_department_id' => 2, 'program_code' => 'P4001', 'program_name' => 'Owning Program', 'is_active' => 1,
        ]);
        // Borrowing unit's program — needs the owning unit's subject in its
        // curriculum, which is what makes it show up as a "borrowing
        // program" on the owning chairperson's own Schedule Plotting grid.
        $db->table('programs')->insert([
            'program_id' => 4002, 'sub_department_id' => 3, 'program_code' => 'P4002', 'program_name' => 'Borrowing Program', 'is_active' => 1,
        ]);

        $db->table('subjects')->insert(['subject_code' => 'GEC101', 'subject_name' => 'GEC101 Title', 'sub_department_id' => 2]);
        $subjectId = (int) $db->insertID();

        $term = AcademicYearModel::format('1', '1st');
        $db->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);

        $curriculum = (new CurriculumModel())->liveFor(4002);
        $db->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculum['curriculum_id'], 'subject_id' => $subjectId,
            'year_level' => 1, 'semester' => 1, 'is_active' => 1,
        ]);

        $db->table('rooms')->insert(['name' => 'Room 101', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '1st Floor', 'is_active' => 1]);

        $sectionYear = AcademicYearModel::yearOnly($term);
        $sections = new SectionModel();
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Borrowing Program', 'year_level' => '1st Year', 'section' => 'A']);
        $sectionId = (int) $sections->getInsertID();

        return ['chairId' => $chairId, 'requesterId' => $requesterId, 'subjectId' => $subjectId, 'sectionId' => $sectionId, 'term' => $term];
    }

    public function testPlottingDirectlyAutoFulfillsAPendingAdditionalOfferingRequest(): void
    {
        ['chairId' => $chairId, 'requesterId' => $requesterId, 'subjectId' => $subjectId, 'sectionId' => $sectionId, 'term' => $term]
            = $this->seedOwnedSubjectWithBorrowedSectionDemand();

        $requests = new OfferingPlacementRequestModel();
        $created  = $requests->create([
            'subject_id' => $subjectId, 'section_id' => $sectionId, 'requested_by' => $requesterId,
            'request_type' => 'additional_offering',
        ]);
        $this->assertTrue($created['ok'], $created['error'] ?? '');

        $this->actingAs(auth()->getProvider()->findById($chairId));
        $payload = [
            'context' => [
                'academic_year' => $term, 'program' => 'Borrowing Program', 'year_level' => '1st Year',
                'semester' => '1st', 'section' => 'A',
            ],
            'blocks' => [
                ['code' => 'GEC101', 'time_label' => '08:00-09:30', 'room' => 'Room 101', 'start' => '08:00', 'end' => '09:30', 'day' => 0],
            ],
            csrf_token() => csrf_hash(),
        ];
        $result = $this->withSession()
            ->withBody(json_encode($payload))
            ->withBodyFormat('json')
            ->post('/chairperson/schedule/sync');

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'] ?? false, $body['error'] ?? json_encode($body));

        $updated = $requests->find($created['id']);
        $this->assertSame('fulfilled', $updated['status']);
        $this->assertNotNull($updated['reviewed_by']);
        $this->assertNotNull($updated['reviewed_at']);
    }

    public function testPlacementRequestIsNotAutoFulfilledByADirectPlot(): void
    {
        // A placement request means "attach my section to a specific
        // existing offering" — a plain legacy block on the grid doesn't
        // carry that meaning, so it must stay pending for a human (or
        // fulfillPlacement()) to act on.
        ['chairId' => $chairId, 'requesterId' => $requesterId, 'subjectId' => $subjectId, 'sectionId' => $sectionId, 'term' => $term]
            = $this->seedOwnedSubjectWithBorrowedSectionDemand();

        $requests = new OfferingPlacementRequestModel();
        $created  = $requests->create([
            'subject_id' => $subjectId, 'section_id' => $sectionId, 'requested_by' => $requesterId,
            'request_type' => 'placement',
        ]);
        $this->assertTrue($created['ok'], $created['error'] ?? '');

        $this->actingAs(auth()->getProvider()->findById($chairId));
        $payload = [
            'context' => [
                'academic_year' => $term, 'program' => 'Borrowing Program', 'year_level' => '1st Year',
                'semester' => '1st', 'section' => 'A',
            ],
            'blocks' => [
                ['code' => 'GEC101', 'time_label' => '08:00-09:30', 'room' => 'Room 101', 'start' => '08:00', 'end' => '09:30', 'day' => 0],
            ],
            csrf_token() => csrf_hash(),
        ];
        $result = $this->withSession()
            ->withBody(json_encode($payload))
            ->withBodyFormat('json')
            ->post('/chairperson/schedule/sync');

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok'] ?? false, $body['error'] ?? json_encode($body));

        $updated = $requests->find($created['id']);
        $this->assertSame('pending', $updated['status']);
    }
}
