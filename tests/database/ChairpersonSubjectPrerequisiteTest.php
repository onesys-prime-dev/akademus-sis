<?php

use App\Models\SubjectPrerequisiteModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Chairperson-facing prerequisite management, scoped to the chair's own
 * authorizedSubDeptIds() for the subject being edited, with a catalog-wide
 * (never department-scoped) candidate list. See
 * docs/superpowers/specs/2026-11-24-chairperson-prereq-coreq-design.md.
 *
 * @internal
 */
final class ChairpersonSubjectPrerequisiteTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private static int $seq = 90000;

    private function seedDept(int $deptId): void
    {
        $db = db_connect();
        $db->table('colleges')->insert(['college_id' => $deptId, 'code' => 'COL' . $deptId, 'name' => 'College ' . $deptId]);
        $db->table('departments')->insert(['dept_id' => $deptId, 'college_id' => $deptId, 'code' => 'SD' . $deptId, 'name' => 'Department ' . $deptId]);
    }

    private function seedChair(int $deptId): int
    {
        $this->seedDept($deptId);

        $db     = db_connect();
        $userId = ++self::$seq;
        $this->seedUsers($userId);
        $db->table('users')->where('id', $userId)->update(['sub_department_id' => $deptId]);
        $db->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        return $userId;
    }

    private function seedSubject(int $deptId, bool $active = true): int
    {
        $db = db_connect();
        $code = 'SUB-' . (++self::$seq);
        $db->table('subjects')->insert([
            'department_id' => $deptId,
            'subject_code'  => $code,
            'subject_name'  => $code . ' Title',
            'units'         => 3,
            'contact_hours' => 3,
            'is_laboratory' => 0,
            'is_active'     => $active ? 1 : 0,
        ]);

        return (int) $db->insertID();
    }

    public function testListReturnsPrerequisitesAndCatalogWideCandidates(): void
    {
        $deptA   = 91001;
        $deptB   = 91002;
        $chairId = $this->seedChair($deptA);
        $this->seedDept($deptB);
        $subjectA = $this->seedSubject($deptA);
        $subjectB = $this->seedSubject($deptB);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->get("/chairperson/subject-ownership/{$subjectA}/prerequisites");

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['ok']);
        $this->assertContains($subjectB, array_map('intval', array_column($json['candidates'], 'subject_id')));
    }

    public function testAddPrerequisiteAcceptsACrossDepartmentSubject(): void
    {
        $deptA   = 91003;
        $deptB   = 91004;
        $chairId = $this->seedChair($deptA);
        $this->seedDept($deptB);
        $subjectA = $this->seedSubject($deptA);
        $subjectB = $this->seedSubject($deptB);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()
            ->withBody(json_encode(['prerequisite_subject_id' => $subjectB, csrf_token() => csrf_hash()]))
            ->withBodyFormat('json')
            ->post("/chairperson/subject-ownership/{$subjectA}/prerequisites/add");

        $result->assertStatus(200);
        $this->seeInDatabase('subject_prerequisites', ['subject_id' => $subjectA, 'prerequisite_subject_id' => $subjectB]);
    }

    public function testAddPrerequisiteRefusesASubjectTheChairDoesNotOwn(): void
    {
        $deptA   = 91005;
        $deptB   = 91006;
        $chairId = $this->seedChair($deptA);
        $this->seedDept($deptB);
        $subjectB = $this->seedSubject($deptB);
        $subjectC = $this->seedSubject($deptB);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()
            ->withBody(json_encode(['prerequisite_subject_id' => $subjectC, csrf_token() => csrf_hash()]))
            ->withBodyFormat('json')
            ->post("/chairperson/subject-ownership/{$subjectB}/prerequisites/add");

        $result->assertStatus(400);
        $this->dontSeeInDatabase('subject_prerequisites', ['subject_id' => $subjectB, 'prerequisite_subject_id' => $subjectC]);
    }

    public function testAddPrerequisiteSurfacesTheModelsCycleError(): void
    {
        $deptA   = 91007;
        $chairId = $this->seedChair($deptA);
        $subjectA = $this->seedSubject($deptA);
        $subjectB = $this->seedSubject($deptA);
        (new SubjectPrerequisiteModel())->addPrerequisite($subjectA, $subjectB);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()
            ->withBody(json_encode(['prerequisite_subject_id' => $subjectA, csrf_token() => csrf_hash()]))
            ->withBodyFormat('json')
            ->post("/chairperson/subject-ownership/{$subjectB}/prerequisites/add");

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertStringContainsString('circular', $json['error']);
    }

    public function testRemovePrerequisiteThatDoesNotExistReturnsCleanFailureNotA500(): void
    {
        $deptA   = 91008;
        $chairId = $this->seedChair($deptA);
        $subjectA = $this->seedSubject($deptA);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->post("/chairperson/subject-ownership/{$subjectA}/prerequisites/999999/remove", [csrf_token() => csrf_hash()]);

        $result->assertStatus(400);
    }

    public function testAnInactiveTargetSubjectIsStillEditable(): void
    {
        $deptA   = 91009;
        $chairId = $this->seedChair($deptA);
        $subjectA = $this->seedSubject($deptA, active: false);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->get("/chairperson/subject-ownership/{$subjectA}/prerequisites");

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['ok']);
    }
}
