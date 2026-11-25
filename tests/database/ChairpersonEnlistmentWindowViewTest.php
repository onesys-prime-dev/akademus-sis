<?php

use App\Models\ProgramEnlistmentWindowModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * The Enlistment Window page's Term Window card, mode-aware per
 * docs/superpowers/specs/2026-11-19-enlistment-window-scoping-design.md.
 *
 * @internal
 */
final class ChairpersonEnlistmentWindowViewTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private int $ayId;
    private int $programId;

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();

        $db->table('colleges')->insert(['college_id' => 91201, 'code' => 'VWCOL', 'name' => 'VW College']);
        $db->table('departments')->insert(['dept_id' => 91201, 'college_id' => 91201, 'code' => 'VWDEPT', 'name' => 'VW Department']);
        $db->table('programs')->insert(['program_id' => 91201, 'department_id' => 91201, 'program_code' => 'VWPROG', 'program_name' => 'VW Program', 'is_active' => 1]);
        $this->programId = 91201;

        $db->table('academic_years')->insert([
            'label' => '2026-2027', 'semester' => '2nd', 'is_active' => 0,
            'start_date' => date('Y-m-d', strtotime('+30 days')),
            'enlistment_opens_at' => '2026-10-01 00:00:00', 'enlistment_closes_at' => '2026-12-01 00:00:00',
        ]);
        $this->ayId = (int) $db->insertID();

        $this->seedUsers(91290);
        $db->table('users')->where('id', 91290)->update(['sub_department_id' => 91201]);
        $db->table('auth_groups_users')->insert(['user_id' => 91290, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById(91290));

        setting('Enlistment.windowMode', null);
    }

    private function visit()
    {
        return $this->withSession()->get('chairperson/enlistment-window?ay_id=' . $this->ayId);
    }

    public function testRegistrarOnlyModeShowsNoEditControls(): void
    {
        setting('Enlistment.windowMode', 'registrar_only');

        $result = $this->visit();

        $result->assertDontSee('name="enlistment_opens_at"');
        $result->assertSee('controlled by the Registrar');
    }

    public function testProgramScopedModeShowsAnEditableFieldPerAuthorizedProgram(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');

        $result = $this->visit();

        $result->assertSee('name="enlistment_opens_at"');
        $result->assertSee('VW Program');
    }

    public function testProgramScopedModeDisablesTheFieldsWhenTheOuterWindowIsUnset(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        db_connect()->table('academic_years')->where('ay_id', $this->ayId)->update([
            'enlistment_opens_at' => null, 'enlistment_closes_at' => null,
        ]);

        $result = $this->visit();

        $result->assertSee('disabled');
        $result->assertSee("hasn't opened enlistment");
    }

    public function testProgramScopedModePrefillsAnExistingProgramWindow(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        (new ProgramEnlistmentWindowModel())->upsert($this->ayId, $this->programId, '2026-10-05 08:00:00', '2026-10-10 17:00:00', 91290);

        $result = $this->visit();

        $result->assertSee('2026-10-05T08:00');
    }
}
