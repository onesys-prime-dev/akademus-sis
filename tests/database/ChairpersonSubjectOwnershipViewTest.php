<?php

use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * @internal
 */
final class ChairpersonSubjectOwnershipViewTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testIndexRendersThePrerequisiteDialogMarkup(): void
    {
        $deptId = 93001;
        $db     = db_connect();
        $db->table('colleges')->insert(['college_id' => $deptId, 'code' => 'COL' . $deptId, 'name' => 'College ' . $deptId]);
        $db->table('departments')->insert(['dept_id' => $deptId, 'college_id' => $deptId, 'code' => 'SD' . $deptId, 'name' => 'Department ' . $deptId]);

        $chairId = 93002;
        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => $deptId]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->get('/chairperson/subject-ownership');

        $result->assertStatus(200);
        $this->assertStringContainsString('id="soPrereqDialog"', $result->getBody());
    }
}
