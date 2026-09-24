<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Deny-by-default permission matrix for student records: the registrar and
 * admin may look up and manage students; every other role may do neither.
 * (Route filters are covered by hitting the live URLs; this pins the matrix.)
 *
 * @internal
 */
final class RegistrarPermissionsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private function userIn(string $group, int $id)
    {
        $db = db_connect();
        $db->table('users')->insert(['id' => $id, 'username' => "acl{$id}", 'active' => 1]);
        $db->table('auth_groups_users')->insert(['user_id' => $id, 'group' => $group, 'created_at' => date('Y-m-d H:i:s')]);

        return auth()->getProvider()->findById($id);
    }

    public function testRegistrarCanViewAndManageStudents(): void
    {
        $registrar = $this->userIn('registrar', 1001);

        $this->assertTrue($registrar->can('students.view'));
        $this->assertTrue($registrar->can('students.manage'));
    }

    public function testAdminCanViewAndManageStudents(): void
    {
        $admin = $this->userIn('admin', 1002);

        $this->assertTrue($admin->can('students.view'));
        $this->assertTrue($admin->can('students.manage'));
    }

    /**
     * @dataProvider otherRoles
     */
    public function testEveryOtherRoleIsDenied(string $group): void
    {
        $user = $this->userIn($group, 1003);

        $this->assertFalse($user->can('students.view'));
        $this->assertFalse($user->can('students.manage'));
    }

    public static function otherRoles(): array
    {
        return [['faculty'], ['student'], ['chairperson'], ['dean']];
    }

    public function testRegistrarIsNotAnAdministrator(): void
    {
        $registrar = $this->userIn('registrar', 1004);

        $this->assertFalse($registrar->can('admin.access'));
        $this->assertFalse($registrar->can('users.create'));
        $this->assertFalse($registrar->can('users.delete'));
    }
}
