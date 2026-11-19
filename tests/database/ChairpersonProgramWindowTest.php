<?php

use App\Models\ProgramEnlistmentWindowModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * HTTP-level coverage for Chairperson\EnlistmentWindowController::
 * updateProgramWindow() — the program-scoped replacement for the old
 * university-wide update() action. See
 * docs/superpowers/specs/2026-11-19-enlistment-window-scoping-design.md.
 *
 * @internal
 */
final class ChairpersonProgramWindowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private int $ayId;
    private int $programId;
    private int $otherProgramId;

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();

        $db->table('colleges')->insert(['college_id' => 91101, 'code' => 'PWCOL', 'name' => 'PW College']);
        $db->table('departments')->insert(['dept_id' => 91101, 'college_id' => 91101, 'code' => 'PWDEPT', 'name' => 'PW Department']);
        $db->table('departments')->insert(['dept_id' => 91102, 'college_id' => 91101, 'code' => 'PWDEPT2', 'name' => 'PW Other Department']);
        $db->table('programs')->insert(['program_id' => 91101, 'department_id' => 91101, 'program_code' => 'PWPROG', 'program_name' => 'PW Program', 'is_active' => 1]);
        $db->table('programs')->insert(['program_id' => 91102, 'department_id' => 91102, 'program_code' => 'PWPROG2', 'program_name' => 'PW Other Program', 'is_active' => 1]);
        $this->programId      = 91101;
        $this->otherProgramId = 91102;

        $db->table('academic_years')->insert([
            'label' => '2026-2027', 'semester' => '2nd', 'is_active' => 0,
            'start_date' => date('Y-m-d', strtotime('+30 days')),
            'enlistment_opens_at' => '2026-10-01 00:00:00', 'enlistment_closes_at' => '2026-12-01 00:00:00',
        ]);
        $this->ayId = (int) $db->insertID();

        $this->seedUsers(91190);
        $db->table('users')->where('id', 91190)->update(['sub_department_id' => 91101]);
        $db->table('auth_groups_users')->insert(['user_id' => 91190, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById(91190));

        setting('Enlistment.windowMode', null);
    }

    private function postWindow(array $overrides = [])
    {
        return $this->withSession()->post('/chairperson/enlistment-window/update-program-window', array_merge([
            'ay_id' => $this->ayId, 'program_id' => $this->programId,
            'enlistment_opens_at' => '2026-10-05T08:00', 'enlistment_closes_at' => '2026-10-10T17:00',
            csrf_token() => csrf_hash(),
        ], $overrides));
    }

    public function testUpdateProgramWindowIsRefusedInRegistrarOnlyMode(): void
    {
        setting('Enlistment.windowMode', 'registrar_only');

        $this->postWindow();

        $this->assertNull((new ProgramEnlistmentWindowModel())->forProgram($this->ayId, $this->programId));
    }

    public function testUpdateProgramWindowSavesInProgramScopedMode(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');

        $result = $this->postWindow();

        $result->assertRedirect();
        $this->seeInDatabase('program_enlistment_windows', ['ay_id' => $this->ayId, 'program_id' => $this->programId]);
    }

    public function testUpdateProgramWindowRefusesAProgramOutsideTheChairsDepartments(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');

        $this->postWindow(['program_id' => $this->otherProgramId]);

        $this->assertNull((new ProgramEnlistmentWindowModel())->forProgram($this->ayId, $this->otherProgramId));
    }

    public function testUpdateProgramWindowSurfacesTheModelsValidationError(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');

        $this->postWindow(['enlistment_opens_at' => '2026-01-01T00:00', 'enlistment_closes_at' => '2026-01-02T00:00']);

        $this->assertNull((new ProgramEnlistmentWindowModel())->forProgram($this->ayId, $this->programId));
    }
}
