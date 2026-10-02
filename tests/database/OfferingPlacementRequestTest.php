<?php

use App\Libraries\SchedulingAuthorization;
use App\Models\AuditLogModel;
use App\Models\CourseOfferingModel;
use App\Models\OfferingPlacementRequestModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Step 3 of the shared-subject architecture work: the request/placement
 * workflow. A non-owning scheduler requests placement into a subject another
 * unit owns; the owning unit's scheduler (or an authorized override)
 * decides. Requesters never get direct write access to another unit's
 * offering.
 *
 * @internal
 */
final class OfferingPlacementRequestTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private int $nextDeptId = 1;
    private int $nextSubDeptId = 1;
    private int $nextProgramId = 1;

    private function seedCollege(): int
    {
        $id = $this->nextDeptId++;
        db_connect()->table('departments')->insert(['dept_id' => $id, 'code' => 'COL' . $id, 'name' => 'College ' . $id]);

        return $id;
    }

    private function seedSubDept(int $collegeId, string $code): int
    {
        $id = $this->nextSubDeptId++;
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $id, 'college_id' => $collegeId, 'code' => $code, 'name' => $code . ' Unit']);

        return $id;
    }

    private function seedProgram(int $subDeptId, string $name): void
    {
        $id = $this->nextProgramId++;
        db_connect()->table('programs')->insert([
            'program_id'        => $id,
            'sub_department_id' => $subDeptId,
            'program_code'      => 'PRG' . $id,
            'program_name'      => $name,
            'is_active'         => 1,
        ]);
    }

    private function seedSubject(string $subjectCode, ?int $subDeptId): int
    {
        db_connect()->table('subjects')->insert([
            'subject_code'      => $subjectCode,
            'subject_name'      => $subjectCode . ' Title',
            'sub_department_id' => $subDeptId,
        ]);

        return (int) db_connect()->insertID();
    }

    private function seedSection(string $program, string $name): array
    {
        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => 'AY2026-2027',
            'program'       => $program,
            'year_level'    => '1st Year',
            'section'       => $name,
        ]);

        return $sections->find($sections->getInsertID());
    }

    private function seedUser(int $id, ?int $subDeptId = null): void
    {
        db_connect()->table('users')->insert([
            'id'                => $id,
            'username'          => 'user' . $id,
            'active'            => 1,
            'sub_department_id' => $subDeptId,
        ]);
    }

    private function assignGroup(int $userId, string $group): void
    {
        db_connect()->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => $group, 'created_at' => date('Y-m-d H:i:s')]);
    }

    private function delegate(int $subDeptId, int $userId): void
    {
        db_connect()->table('sub_department_schedulers')->insert(['sub_dept_id' => $subDeptId, 'user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    }

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

    private function makeOffering(string $subjectCode, ?int $capacity = null): int
    {
        // Two throwaway sections just to satisfy createMerge()'s "2+" rule.
        $a = $this->seedSection('OFFERING-SEED', 'SeedA-' . uniqid());
        $b = $this->seedSection('OFFERING-SEED', 'SeedB-' . uniqid());

        $result = (new CourseOfferingModel())->createMerge('AY2026-2027', $subjectCode, [$a['section_id'], $b['section_id']], $capacity);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        return (int) $result['offering_id'];
    }

    // --- Basic authorization, requester side -------------------------------

    public function testAuthorizedChairpersonSubmitsPlacementRequestForOwnSection(): void
    {
        $college = $this->seedCollege();
        $it      = $this->seedSubDept($college, 'IT');
        $this->seedProgram($it, 'BSIT');
        $this->seedUser(100, $it);
        $section = $this->seedSection('BSIT', 'A');
        $subjectId = $this->seedSubject('RED104', null);

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'   => $subjectId,
            'section_id'   => $section['section_id'],
            'requested_by' => 100,
            'request_type' => 'placement',
        ]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('offering_placement_requests', ['id' => $result['id'], 'requesting_sub_dept_id' => $it, 'status' => 'pending']);
    }

    public function testUnauthorizedChairpersonSubmitsForAnotherUnitsSection(): void
    {
        $college = $this->seedCollege();
        $it = $this->seedSubDept($college, 'IT');
        $cs = $this->seedSubDept($college, 'CS');
        $this->seedProgram($it, 'BSIT');
        $this->seedProgram($cs, 'BSCS');
        $this->seedUser(101, $cs);
        $section = $this->seedSection('BSIT', 'A'); // owned by IT
        $subjectId = $this->seedSubject('RED104', null);

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'   => $subjectId,
            'section_id'   => $section['section_id'],
            'requested_by' => 101,
            'request_type' => 'placement',
        ]);

        $this->assertFalse($result['ok']);
    }

    public function testDelegatedSchedulerSubmitsForDelegatedSection(): void
    {
        $college = $this->seedCollege();
        $it = $this->seedSubDept($college, 'IT');
        $cs = $this->seedSubDept($college, 'CS');
        $this->seedProgram($it, 'BSIT');
        $this->seedProgram($cs, 'BSCS');
        $this->seedUser(102, $cs);
        $this->delegate($it, 102); // CS scheduler delegated IT
        $section = $this->seedSection('BSIT', 'A'); // owned by IT
        $subjectId = $this->seedSubject('RED104', null);

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'   => $subjectId,
            'section_id'   => $section['section_id'],
            'requested_by' => 102,
            'request_type' => 'placement',
        ]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('offering_placement_requests', ['id' => $result['id'], 'requesting_sub_dept_id' => $it]);
    }

    public function testRequestForNonexistentSubjectRejected(): void
    {
        $college = $this->seedCollege();
        $it = $this->seedSubDept($college, 'IT');
        $this->seedProgram($it, 'BSIT');
        $this->seedUser(103, $it);
        $section = $this->seedSection('BSIT', 'A');

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'   => 999999,
            'section_id'   => $section['section_id'],
            'requested_by' => 103,
            'request_type' => 'placement',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Subject not found', $result['error']);
    }

    public function testRequestWithTargetOfferingFromDifferentSubjectRejected(): void
    {
        $college = $this->seedCollege();
        $it = $this->seedSubDept($college, 'IT');
        $this->seedProgram($it, 'BSIT');
        $this->seedUser(104, $it);
        $section = $this->seedSection('BSIT', 'A');

        $this->seedSubject('SUBJ-A', null);
        $subjectB = $this->seedSubject('SUBJ-B', null);
        $offeringForA = $this->makeOffering('SUBJ-A');

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'         => $subjectB,
            'section_id'         => $section['section_id'],
            'requested_by'       => 104,
            'request_type'       => 'placement',
            'target_offering_id' => $offeringForA,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('different subject', $result['error']);
    }

    public function testDuplicatePendingRequestPrevented(): void
    {
        $college = $this->seedCollege();
        $it = $this->seedSubDept($college, 'IT');
        $this->seedProgram($it, 'BSIT');
        $this->seedUser(105, $it);
        $section = $this->seedSection('BSIT', 'A');
        $subjectId = $this->seedSubject('RED104', null);

        $requests = new OfferingPlacementRequestModel();
        $data = ['subject_id' => $subjectId, 'section_id' => $section['section_id'], 'requested_by' => 105, 'request_type' => 'placement'];

        $first = $requests->create($data);
        $this->assertTrue($first['ok']);

        $second = $requests->create($data);
        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('already exists', $second['error']);
    }

    // --- Reviewer-side authorization ----------------------------------------

    public function testOwningSchedulerSeesReviewsRequest(): void
    {
        [$requestId, , $owningUser] = $this->makePendingPlacementRequest();

        $requests = new OfferingPlacementRequestModel();
        $result   = $requests->approve($requestId, $owningUser);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertFalse($result['override']);
    }

    public function testUnrelatedSchedulerAttemptsReviewDenied(): void
    {
        [$requestId] = $this->makePendingPlacementRequest();

        $college = $this->seedCollege();
        $unrelated = $this->seedSubDept($college, 'UNRELATED');
        $this->seedUser(9001, $unrelated);

        $result = (new OfferingPlacementRequestModel())->approve($requestId, 9001);

        $this->assertFalse($result['ok']);
    }

    // --- Registrar/Admin override, resolved through the centralized layer --

    public function testRegistrarOverrideAuthorizationResolvedThroughCentralizedLayer(): void
    {
        [$requestId] = $this->makePendingPlacementRequest();

        $this->seedUser(9002);
        $this->assignGroup(9002, 'registrar');

        $auth = new SchedulingAuthorization();
        $this->assertTrue($auth->canOverrideScheduling(9002));
        $this->assertFalse($auth->canManageSubject(9002, (new OfferingPlacementRequestModel())->find($requestId)['subject_id']));

        $result = (new OfferingPlacementRequestModel())->approve($requestId, 9002, 'Registrar stepping in.');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertTrue($result['override']);
    }

    // --- Placement fulfillment -----------------------------------------------

    public function testPlacementFulfillmentAttachesSectionToCorrectOffering(): void
    {
        [$requestId, $request, $owningUser, $sectionId, $subjectCode] = $this->makePendingPlacementRequest();
        $offeringId = $this->makeOffering($subjectCode);

        $result = (new OfferingPlacementRequestModel())->fulfillPlacement($requestId, $owningUser, $offeringId);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('offering_sections', ['offering_id' => $offeringId, 'section_id' => $sectionId]);
    }

    public function testPlacementFulfillmentUpdatesRequestStatusToFulfilled(): void
    {
        [$requestId, , $owningUser, , $subjectCode] = $this->makePendingPlacementRequest();
        $offeringId = $this->makeOffering($subjectCode);

        (new OfferingPlacementRequestModel())->fulfillPlacement($requestId, $owningUser, $offeringId);

        $this->seeInDatabase('offering_placement_requests', ['id' => $requestId, 'status' => 'fulfilled', 'target_offering_id' => $offeringId]);
    }

    public function testAttachFailureLeavesRequestStatusUnaffected(): void
    {
        [$requestId, $request, $owningUser, $sectionId, $subjectCode] = $this->makePendingPlacementRequest();
        $offeringA = $this->makeOffering($subjectCode);
        $offeringB = $this->makeOffering($subjectCode);

        // Attach the section to offering A directly (outside the request flow).
        $attach = (new CourseOfferingModel())->attachSection($offeringA, $sectionId, $subjectCode);
        $this->assertTrue($attach['ok'], $attach['error'] ?? '');

        // Fulfilling the request against offering B must fail (section already
        // attached elsewhere for this subject) — and the transaction must
        // leave the request's status untouched (still pending), not
        // half-fulfilled.
        $result = (new OfferingPlacementRequestModel())->fulfillPlacement($requestId, $owningUser, $offeringB);

        $this->assertFalse($result['ok']);
        $this->seeInDatabase('offering_placement_requests', ['id' => $requestId, 'status' => 'pending']);
        // No duplicate placement: exactly one offering_sections row for this section+subject.
        $this->assertSame(1, db_connect()->table('offering_sections')->where('section_id', $sectionId)->where('subject_code', $subjectCode)->countAllResults());
    }

    // --- Capacity override (adjustment 2) ------------------------------------

    public function testCapacityOverflowBlockedWithoutOverride(): void
    {
        [$requestId, , $owningUser, $sectionId, $subjectCode] = $this->makePendingPlacementRequest();
        $this->enrollStudent(30001, $sectionId, $subjectCode);
        $offeringId = $this->makeOffering($subjectCode, 0); // capacity already at its ceiling

        $result = (new OfferingPlacementRequestModel())->fulfillPlacement($requestId, $owningUser, $offeringId);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['requires_override'] ?? false);
        $this->seeInDatabase('offering_placement_requests', ['id' => $requestId, 'status' => 'pending']);
        // The blocked attach never happened: the requester's section was never added to this offering.
        $this->assertSame(0, db_connect()->table('offering_sections')->where('offering_id', $offeringId)->where('section_id', $sectionId)->countAllResults());
    }

    public function testCapacityOverflowSucceedsWithAuthorizedExplicitOverride(): void
    {
        [$requestId, , $owningUser, $sectionId, $subjectCode] = $this->makePendingPlacementRequest();
        $this->enrollStudent(30002, $sectionId, $subjectCode);
        $offeringId = $this->makeOffering($subjectCode, 0);

        $result = (new OfferingPlacementRequestModel())->fulfillPlacement(
            $requestId,
            $owningUser,
            $offeringId,
            true,
            'Music has no other viable slot this term.'
        );

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertTrue($result['over_capacity']);
        $this->seeInDatabase('offering_placement_requests', ['id' => $requestId, 'status' => 'fulfilled']);
    }

    public function testCapacityOverrideWithoutReasonIsRejected(): void
    {
        [$requestId, , $owningUser, $sectionId, $subjectCode] = $this->makePendingPlacementRequest();
        $this->enrollStudent(30003, $sectionId, $subjectCode);
        $offeringId = $this->makeOffering($subjectCode, 0);

        $result = (new OfferingPlacementRequestModel())->fulfillPlacement($requestId, $owningUser, $offeringId, true, '');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('reason is required', $result['error']);
    }

    public function testCapacityOverrideIsAuditLoggedWithTheProvidedReason(): void
    {
        [$requestId, , $owningUser, $sectionId, $subjectCode] = $this->makePendingPlacementRequest();
        $this->enrollStudent(30004, $sectionId, $subjectCode);
        $offeringId = $this->makeOffering($subjectCode, 0);

        $result = (new OfferingPlacementRequestModel())->fulfillPlacement(
            $requestId,
            $owningUser,
            $offeringId,
            true,
            'Music has no other viable slot this term.'
        );

        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $log = (new AuditLogModel())
            ->where('action', 'placement_request.capacity_override')
            ->where('entity_id', (string) $requestId)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Music has no other viable slot this term.', $log['reason']);
    }

    // --- Cancellation ---------------------------------------------------------

    public function testRequesterCancelsOwnPendingRequest(): void
    {
        [$requestId, , , , , $requester] = $this->makePendingPlacementRequest();

        $result = (new OfferingPlacementRequestModel())->cancel($requestId, $requester);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('offering_placement_requests', ['id' => $requestId, 'status' => 'cancelled']);
    }

    public function testCancelAlreadyFulfilledRequestDenied(): void
    {
        [$requestId, , $owningUser, , $subjectCode, $requester] = $this->makePendingPlacementRequest();
        $offeringId = $this->makeOffering($subjectCode);

        $fulfill = (new OfferingPlacementRequestModel())->fulfillPlacement($requestId, $owningUser, $offeringId);
        $this->assertTrue($fulfill['ok'], $fulfill['error'] ?? '');

        $result = (new OfferingPlacementRequestModel())->cancel($requestId, $requester);

        $this->assertFalse($result['ok']);
        $this->seeInDatabase('offering_placement_requests', ['id' => $requestId, 'status' => 'fulfilled']);
    }

    // --- Audit logging ----------------------------------------------------------

    public function testAuditRecordCreatedForKeyStateChanges(): void
    {
        [$requestId, , $owningUser] = $this->makePendingPlacementRequest();

        (new OfferingPlacementRequestModel())->approve($requestId, $owningUser);

        $created = (new AuditLogModel())->where('action', 'placement_request.created')->where('entity_id', (string) $requestId)->first();
        $approved = (new AuditLogModel())->where('action', 'placement_request.approved')->where('entity_id', (string) $requestId)->first();

        $this->assertNotNull($created);
        $this->assertNotNull($approved);
    }

    // --- Regression: existing shared-offering behavior is untouched ----------

    public function testExistingCourseOfferingMergeBehaviorStillWorks(): void
    {
        $this->seedSubject('REGRESSION1', null);
        $a = $this->seedSection('REG', 'A');
        $b = $this->seedSection('REG', 'B');

        $result = (new CourseOfferingModel())->createMerge('AY2026-2027', 'REGRESSION1', [$a['section_id'], $b['section_id']], null);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    /**
     * Builds a pending placement request from a fresh IT section, for a
     * subject owned by a fresh Religious Education unit, and returns
     * [requestId, requestRow, owningUserId, sectionId, subjectCode, requesterId].
     */
    private function makePendingPlacementRequest(): array
    {
        $college = $this->seedCollege();
        $it  = $this->seedSubDept($college, 'IT');
        $red = $this->seedSubDept($college, 'RELED');
        $this->seedProgram($it, 'BSIT');

        static $userSeq = 20000;
        $requester = $userSeq++;
        $owner     = $userSeq++;

        $this->seedUser($requester, $it);
        $this->seedUser($owner, $red);

        $section   = $this->seedSection('BSIT', 'Sec' . uniqid());
        $subjectId = $this->seedSubject('SHARED' . uniqid(), $red);

        $result = (new OfferingPlacementRequestModel())->create([
            'subject_id'   => $subjectId,
            'section_id'   => $section['section_id'],
            'requested_by' => $requester,
            'request_type' => 'placement',
        ]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $request = (new OfferingPlacementRequestModel())->find($result['id']);
        $subjectCode = db_connect()->table('subjects')->select('subject_code')->where('subject_id', $subjectId)->get()->getRowArray()['subject_code'];

        return [$result['id'], $request, $owner, (int) $section['section_id'], $subjectCode, $requester];
    }
}
