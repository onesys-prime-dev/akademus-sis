<?php

use App\Libraries\SchedulingAuthorization;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Pins SchedulingAuthorization's behavior: a user's authorized sub-departments
 * default to just their own (today's ownSection() behavior, unchanged), and
 * sub_department_schedulers only ever adds to that set, never on its own,
 * never for a different user.
 *
 * @internal
 */
final class SchedulingAuthorizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private function seedDept(int $deptId, string $code, string $name): void
    {
        db_connect()->table('departments')->insert([
            'dept_id' => $deptId,
            'code'    => $code,
            'name'    => $name,
        ]);
    }

    private function seedSubDept(int $subDeptId, int $collegeId, string $code, string $name): void
    {
        db_connect()->table('sub_departments')->insert([
            'sub_dept_id' => $subDeptId,
            'college_id'  => $collegeId,
            'code'        => $code,
            'name'        => $name,
        ]);
    }

    private function seedProgram(int $programId, int $subDeptId, string $code, string $name): void
    {
        db_connect()->table('programs')->insert([
            'program_id'        => $programId,
            'sub_department_id' => $subDeptId,
            'program_code'      => $code,
            'program_name'      => $name,
            'is_active'         => 1,
        ]);
    }

    private function seedSection(int $sectionId, string $program): void
    {
        db_connect()->table('sections')->insert([
            'section_id'    => $sectionId,
            'academic_year' => '2026-2027',
            'program'       => $program,
            'year_level'    => '1',
            'section'       => 'A',
        ]);
    }

    private function seedUser(int $id, ?int $subDeptId = null): void
    {
        db_connect()->table('users')->insert([
            'id'                => $id,
            'username'          => "authuser{$id}",
            'active'            => 1,
            'sub_department_id' => $subDeptId,
        ]);
    }

    public function testUserWithOwnSubDeptCanAccessOwnSection(): void
    {
        $this->seedDept(1, 'COL-A', 'College A');
        $this->seedSubDept(1, 1, 'IT', 'Information Technology');
        $this->seedProgram(1, 1, 'BSIT', 'BS in Information Technology');
        $this->seedSection(1, 'BS in Information Technology');
        $this->seedUser(100, 1);

        $auth = new SchedulingAuthorization();

        $this->assertSame([1], $auth->authorizedSubDeptIds(100));
        $this->assertNotNull($auth->ownSection(100, 1));
    }

    public function testUserFromAnotherSubDeptIsDenied(): void
    {
        $this->seedDept(1, 'COL-A', 'College A');
        $this->seedSubDept(1, 1, 'IT', 'Information Technology');
        $this->seedSubDept(2, 1, 'CS', 'Computer Science');
        $this->seedProgram(1, 1, 'BSIT', 'BS in Information Technology');
        $this->seedSection(1, 'BS in Information Technology');
        $this->seedUser(101, 2);

        $auth = new SchedulingAuthorization();

        $this->assertSame([2], $auth->authorizedSubDeptIds(101));
        $this->assertNull($auth->ownSection(101, 1));
    }

    public function testDelegationGrantsNoAccessToUnrelatedUser(): void
    {
        $this->seedDept(1, 'COL-A', 'College A');
        $this->seedSubDept(1, 1, 'IT', 'Information Technology');
        $this->seedSubDept(2, 1, 'CS', 'Computer Science');
        $this->seedProgram(1, 1, 'BSIT', 'BS in Information Technology');
        $this->seedSection(1, 'BS in Information Technology');
        $this->seedUser(102, 2);
        $this->seedUser(103, 2);

        // Delegate sub-dept 1 to user 102 only.
        db_connect()->table('sub_department_schedulers')->insert([
            'sub_dept_id' => 1,
            'user_id'     => 102,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $auth = new SchedulingAuthorization();

        // 103 got no delegation of their own: unaffected by 102's grant.
        $this->assertSame([2], $auth->authorizedSubDeptIds(103));
        $this->assertNull($auth->ownSection(103, 1));
    }

    public function testDelegatedUserRetainsOwnAccessAndGainsDelegatedUnit(): void
    {
        $this->seedDept(1, 'COL-A', 'College A');
        $this->seedSubDept(1, 1, 'IT', 'Information Technology');
        $this->seedSubDept(2, 1, 'MUSIC', 'Music');
        $this->seedProgram(1, 1, 'BSIT', 'BS in Information Technology');
        $this->seedProgram(2, 2, 'BMUS', 'BM in Music');
        $this->seedSection(1, 'BS in Information Technology'); // owned by sub-dept 1 (IT)
        $this->seedSection(2, 'BM in Music');                  // owned by sub-dept 2 (Music)
        $this->seedUser(104, 1); // belongs to IT (sub-dept 1)

        // Explicitly delegate Music (sub-dept 2) scheduling authority to user 104.
        db_connect()->table('sub_department_schedulers')->insert([
            'sub_dept_id' => 2,
            'user_id'     => 104,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $auth = new SchedulingAuthorization();

        $authorized = $auth->authorizedSubDeptIds(104);
        sort($authorized);
        $this->assertSame([1, 2], $authorized);

        // Retains access to their own (IT) section...
        $this->assertNotNull($auth->ownSection(104, 1));
        // ...and gains access to the delegated (Music) section.
        $this->assertNotNull($auth->ownSection(104, 2));
    }
}
