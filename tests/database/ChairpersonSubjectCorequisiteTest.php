<?php

use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Chairperson-facing corequisite management, scoped to the chair's own
 * authorizedSubDeptIds() for the subject being edited, with a catalog-wide
 * (never department-scoped) candidate list. Mutual A<->B pairings are
 * valid and expected — unlike prerequisites, corequisites never reject a
 * cycle. See
 * docs/superpowers/specs/2026-11-24-chairperson-prereq-coreq-design.md.
 *
 * @internal
 */
final class ChairpersonSubjectCorequisiteTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private static int $seq = 92000;

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
        $db   = db_connect();
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

    public function testListReturnsCorequisitesAndCatalogWideCandidates(): void
    {
        $deptA   = 92001;
        $deptB   = 92002;
        $chairId = $this->seedChair($deptA);
        $this->seedDept($deptB);
        $subjectA = $this->seedSubject($deptA);
        $subjectB = $this->seedSubject($deptB);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->get("/chairperson/subject-ownership/{$subjectA}/corequisites");

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['ok']);
        $this->assertContains($subjectB, array_map('intval', array_column($json['candidates'], 'subject_id')));
    }

    public function testAddCorequisiteAcceptsACrossDepartmentSubject(): void
    {
        $deptA   = 92003;
        $deptB   = 92004;
        $chairId = $this->seedChair($deptA);
        $this->seedDept($deptB);
        $subjectA = $this->seedSubject($deptA);
        $subjectB = $this->seedSubject($deptB);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()
            ->withBody(json_encode(['corequisite_subject_id' => $subjectB, csrf_token() => csrf_hash()]))
            ->withBodyFormat('json')
            ->post("/chairperson/subject-ownership/{$subjectA}/corequisites/add");

        $result->assertStatus(200);
        $this->seeInDatabase('subject_corequisites', ['subject_id' => $subjectA, 'corequisite_subject_id' => $subjectB]);
    }

    public function testAddCorequisiteRefusesASubjectTheChairDoesNotOwn(): void
    {
        $deptA   = 92005;
        $deptB   = 92006;
        $chairId = $this->seedChair($deptA);
        $this->seedDept($deptB);
        $subjectB = $this->seedSubject($deptB);
        $subjectC = $this->seedSubject($deptB);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()
            ->withBody(json_encode(['corequisite_subject_id' => $subjectC, csrf_token() => csrf_hash()]))
            ->withBodyFormat('json')
            ->post("/chairperson/subject-ownership/{$subjectB}/corequisites/add");

        $result->assertStatus(400);
        $this->dontSeeInDatabase('subject_corequisites', ['subject_id' => $subjectB, 'corequisite_subject_id' => $subjectC]);
    }

    public function testAddCorequisiteAcceptsAMutualPairingInBothDirections(): void
    {
        $deptA   = 92007;
        $chairId = $this->seedChair($deptA);
        $subjectA = $this->seedSubject($deptA);
        $subjectB = $this->seedSubject($deptA);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $first = $this->withSession()
            ->withBody(json_encode(['corequisite_subject_id' => $subjectB, csrf_token() => csrf_hash()]))
            ->withBodyFormat('json')
            ->post("/chairperson/subject-ownership/{$subjectA}/corequisites/add");
        $first->assertStatus(200);

        $second = $this->withSession()
            ->withBody(json_encode(['corequisite_subject_id' => $subjectA, csrf_token() => csrf_hash()]))
            ->withBodyFormat('json')
            ->post("/chairperson/subject-ownership/{$subjectB}/corequisites/add");
        $second->assertStatus(200);

        $this->seeInDatabase('subject_corequisites', ['subject_id' => $subjectA, 'corequisite_subject_id' => $subjectB]);
        $this->seeInDatabase('subject_corequisites', ['subject_id' => $subjectB, 'corequisite_subject_id' => $subjectA]);
    }

    public function testRemoveCorequisiteThatDoesNotExistReturnsCleanFailureNotA500(): void
    {
        $deptA   = 92008;
        $chairId = $this->seedChair($deptA);
        $subjectA = $this->seedSubject($deptA);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->post("/chairperson/subject-ownership/{$subjectA}/corequisites/999999/remove", [csrf_token() => csrf_hash()]);

        $result->assertStatus(400);
    }

    public function testAnInactiveTargetSubjectIsStillEditable(): void
    {
        $deptA   = 92009;
        $chairId = $this->seedChair($deptA);
        $subjectA = $this->seedSubject($deptA, active: false);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->get("/chairperson/subject-ownership/{$subjectA}/corequisites");

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['ok']);
    }
}
