<?php

use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * HTTP coverage for the NEW canonical college/department routes introduced
 * by the Admin\DepartmentController -> Admin\CollegeController /
 * Admin\SubDepartmentController -> Admin\DepartmentController rename (see
 * app/Config/Routes.php's "colleges"/"departments" groups). Everything else
 * in the suite that exercises this surface (AcademicHierarchyHardeningTest's
 * duplicate-code checks, for instance) only ever POSTs through the OLD
 * pre-rename alias paths (e.g. "/admin/departments/{id}/sub-departments"),
 * so the canonical paths themselves — including the one genuinely new path,
 * "/admin/colleges/{id}/departments" — had zero direct HTTP coverage.
 *
 * Deliberately narrow: the dean/chair assignment actions (storeDean,
 * storeChair, removeDean, removeChair) and the faculty-affiliation routes
 * create real Shield user accounts and are exercised at the model level
 * elsewhere; only the CRUD surface (index/show/store/update/delete plus the
 * nested department/program creation and department transfer) is covered
 * here.
 *
 * @internal
 */
final class CanonicalCollegeDepartmentRoutesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private static int $seq = 70000;

    private function newCollege(string $name, ?string $code = null): int
    {
        $id = ++self::$seq;
        db_connect()->table('colleges')->insert(['college_id' => $id, 'code' => $code ?? 'COL' . $id, 'name' => $name]);

        return $id;
    }

    private function newDept(int $collegeId, string $name, ?string $code = null): int
    {
        $id = ++self::$seq;
        db_connect()->table('departments')->insert(['dept_id' => $id, 'college_id' => $collegeId, 'code' => $code ?? 'SD' . $id, 'name' => $name, 'is_active' => 1]);

        return $id;
    }

    private function actingAsAdmin(): int
    {
        $id = ++self::$seq;
        $this->seedUsers($id);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'admin', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById($id));

        return $id;
    }

    private function postAs(string $url, array $data = [])
    {
        return $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->withSession()->post($url, $data);
    }

    // =====================================================================
    // AUTHORIZATION GUARD
    // =====================================================================

    public function testCanonicalCollegesRouteRequiresAdmin(): void
    {
        $response = $this->get('/admin/colleges');
        $response->assertRedirect(); // not authenticated -> redirected, never 200
    }

    // =====================================================================
    // COLLEGES
    // =====================================================================

    public function testCollegesIndexListsColleges(): void
    {
        $this->newCollege('Index Test College');
        $this->actingAsAdmin();

        $response = $this->withSession()->get('/admin/colleges');

        $response->assertStatus(200);
        $response->assertSee('Index Test College');
    }

    public function testStoreCollegeCreatesANewRow(): void
    {
        $this->actingAsAdmin();

        $this->postAs('/admin/colleges/store', ['code' => 'CANON', 'name' => 'Canonical College']);

        $this->seeInDatabase('colleges', ['code' => 'CANON', 'name' => 'Canonical College']);
    }

    public function testShowCollegeDisplaysItsDepartments(): void
    {
        $college = $this->newCollege('Show Test College');
        $this->newDept($college, 'Nested Department');
        $this->actingAsAdmin();

        $response = $this->withSession()->get("/admin/colleges/{$college}");

        $response->assertStatus(200);
        $response->assertSee('Nested Department');
    }

    public function testUpdateCollegeChangesItsFields(): void
    {
        $college = $this->newCollege('Old Name', 'OLDC');
        $this->actingAsAdmin();

        $this->postAs("/admin/colleges/{$college}/update", ['code' => 'NEWC', 'name' => 'New Name']);

        $this->seeInDatabase('colleges', ['college_id' => $college, 'code' => 'NEWC', 'name' => 'New Name']);
    }

    /**
     * The one genuinely NEW canonical path ("colleges/{id}/departments" —
     * the old equivalent was "departments/{id}/sub-departments"), not just
     * a renamed existing one.
     */
    public function testStoreDepartmentUnderCollegeCreatesANestedRow(): void
    {
        $college = $this->newCollege('Parent College');
        $this->actingAsAdmin();

        $this->postAs("/admin/colleges/{$college}/departments", ['dept_code' => 'NEWD', 'dept_name' => 'Brand New Department']);

        $this->seeInDatabase('departments', ['college_id' => $college, 'code' => 'NEWD', 'name' => 'Brand New Department']);
    }

    public function testDeleteCollegeIsBlockedWhileItHasDepartments(): void
    {
        $college = $this->newCollege('Occupied College');
        $this->newDept($college, 'Blocking Department');
        $this->actingAsAdmin();

        $this->postAs("/admin/colleges/{$college}/delete");

        $this->seeInDatabase('colleges', ['college_id' => $college]);
    }

    public function testDeleteCollegeSucceedsWhenEmpty(): void
    {
        $college = $this->newCollege('Empty College');
        $this->actingAsAdmin();

        $this->postAs("/admin/colleges/{$college}/delete");

        $this->dontSeeInDatabase('colleges', ['college_id' => $college]);
    }

    // =====================================================================
    // DEPARTMENTS
    // =====================================================================

    public function testShowDepartmentDisplaysItsPrograms(): void
    {
        $college = $this->newCollege('College For Dept Show');
        $dept    = $this->newDept($college, 'Dept With A Program');
        db_connect()->table('programs')->insert(['department_id' => $dept, 'program_code' => 'SHOWP', 'program_name' => 'Shown Program', 'is_active' => 1]);
        $this->actingAsAdmin();

        $response = $this->withSession()->get("/admin/departments/{$dept}");

        $response->assertStatus(200);
        $response->assertSee('Shown Program');
    }

    public function testUpdateDepartmentChangesItsFields(): void
    {
        $college = $this->newCollege('College For Dept Update');
        $dept    = $this->newDept($college, 'Old Dept Name', 'OLDD');
        $this->actingAsAdmin();

        $this->postAs("/admin/departments/{$dept}/update", ['code' => 'NEWDD', 'name' => 'New Dept Name']);

        $this->seeInDatabase('departments', ['dept_id' => $dept, 'code' => 'NEWDD', 'name' => 'New Dept Name']);
    }

    public function testToggleStatusFlipsDepartmentActiveFlag(): void
    {
        $college = $this->newCollege('College For Toggle');
        $dept    = $this->newDept($college, 'Toggle Dept');
        $this->actingAsAdmin();

        $this->postAs("/admin/departments/{$dept}/toggle-status");
        $this->seeInDatabase('departments', ['dept_id' => $dept, 'is_active' => 0]);

        $this->postAs("/admin/departments/{$dept}/toggle-status");
        $this->seeInDatabase('departments', ['dept_id' => $dept, 'is_active' => 1]);
    }

    public function testAddProgramUnderDepartmentCreatesANewRow(): void
    {
        $college = $this->newCollege('College For Add Program');
        $dept    = $this->newDept($college, 'Dept For Add Program');
        $this->actingAsAdmin();

        $this->postAs("/admin/departments/{$dept}/programs", ['program_code' => 'ADDP', 'program_name' => 'Added Program']);

        $this->seeInDatabase('programs', ['department_id' => $dept, 'program_code' => 'ADDP', 'program_name' => 'Added Program']);
    }

    public function testTransferDepartmentMovesItToTheTargetCollege(): void
    {
        $collegeA = $this->newCollege('Source College');
        $collegeB = $this->newCollege('Target College');
        $dept     = $this->newDept($collegeA, 'Mobile Dept');
        $this->actingAsAdmin();

        $this->postAs("/admin/departments/{$dept}/transfer", ['college_id' => $collegeB, 'reason' => 'Reorganization']);

        $this->seeInDatabase('departments', ['dept_id' => $dept, 'college_id' => $collegeB]);
    }

    public function testDeleteDepartmentIsBlockedWhileItHasAProgram(): void
    {
        $college = $this->newCollege('College For Blocked Delete');
        $dept    = $this->newDept($college, 'Dept With A Program To Block Delete');
        db_connect()->table('programs')->insert(['department_id' => $dept, 'program_code' => 'BLOCKP', 'program_name' => 'Blocking Program', 'is_active' => 1]);
        $this->actingAsAdmin();

        $this->postAs("/admin/departments/{$dept}/delete");

        $this->seeInDatabase('departments', ['dept_id' => $dept]);
    }

    public function testDeleteDepartmentSucceedsWhenEmpty(): void
    {
        $college = $this->newCollege('College For Clean Delete');
        $dept    = $this->newDept($college, 'Empty Dept');
        $this->actingAsAdmin();

        $this->postAs("/admin/departments/{$dept}/delete");

        $this->dontSeeInDatabase('departments', ['dept_id' => $dept]);
    }
}
