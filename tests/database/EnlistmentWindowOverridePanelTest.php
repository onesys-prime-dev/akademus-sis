<?php

use App\Models\AcademicYearModel;
use App\Models\EnlistmentWindowOverrideModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * HTTP-level coverage for the chairperson's grant/revoke window-override
 * endpoints and (once Task 5 lands) the Enlistment Window page's Student
 * Overrides panel — scoped the same way the rest of EnlistmentWindowController
 * already is, by authorizedPrograms().
 *
 * @internal
 */
final class EnlistmentWindowOverridePanelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private int $ayId;
    private int $studentId;
    private int $otherProgramStudentId;

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();

        $db->table('colleges')->insert(['college_id' => 91001, 'code' => 'EWCOL', 'name' => 'EW College']);
        $db->table('departments')->insert(['dept_id' => 91001, 'college_id' => 91001, 'code' => 'EWDEPT', 'name' => 'EW Department']);
        $db->table('departments')->insert(['dept_id' => 91002, 'college_id' => 91001, 'code' => 'EWDEPT2', 'name' => 'EW Other Department']);
        $db->table('programs')->insert(['program_id' => 91001, 'department_id' => 91001, 'program_code' => 'EWPROG', 'program_name' => 'EW Program', 'is_active' => 1]);
        $db->table('programs')->insert(['program_id' => 91002, 'department_id' => 91002, 'program_code' => 'EWPROG2', 'program_name' => 'EW Other Program', 'is_active' => 1]);

        $db->table('academic_years')->insert([
            'label' => '2026-2027', 'semester' => '2nd', 'is_active' => 0,
            'start_date' => date('Y-m-d', strtotime('+30 days')),
        ]);
        $this->ayId = (int) $db->insertID();

        $this->seedUsers(70801, 70802);
        $db->table('users')->where('id', 70801)->update(['first_name' => 'Juan', 'last_name' => 'DelaCruz']);
        $db->table('users')->where('id', 70802)->update(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $db->table('students')->insert(['user_id' => 70801, 'applicant_id' => 'A70801', 'student_number' => 'S70801', 'student_status' => 'active', 'program_id' => 91001]);
        $this->studentId = (int) $db->insertID();
        $db->table('students')->insert(['user_id' => 70802, 'applicant_id' => 'A70802', 'student_number' => 'S70802', 'student_status' => 'active', 'program_id' => 91002]);
        $this->otherProgramStudentId = (int) $db->insertID();

        $this->seedUsers(70803);
        $db->table('users')->where('id', 70803)->update(['sub_department_id' => 91001]);
        $db->table('auth_groups_users')->insert(['user_id' => 70803, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById(70803));
    }

    public function testGrantUnlocksAnAuthorizedStudent(): void
    {
        $result = $this->withSession()->post('/chairperson/enlistment-window/grant-override', [
            'student_id' => $this->studentId, 'ay_id' => $this->ayId, 'reason' => 'Shifted program',
            csrf_token() => csrf_hash(),
        ]);

        $result->assertStatus(200);
        $body = json_decode($result->response()->getBody(), true);
        $this->assertTrue($body['ok'], $body['error'] ?? '');
        $this->seeInDatabase('enlistment_window_overrides', ['student_id' => $this->studentId, 'status' => 'active']);
    }

    public function testGrantRefusesAStudentOutsideTheChairsPrograms(): void
    {
        $result = $this->withSession()->post('/chairperson/enlistment-window/grant-override', [
            'student_id' => $this->otherProgramStudentId, 'ay_id' => $this->ayId, 'reason' => 'Shifted program',
            csrf_token() => csrf_hash(),
        ]);

        $body = json_decode($result->response()->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertSame(0, db_connect()->table('enlistment_window_overrides')->countAllResults());
    }

    public function testRevokeEndsAnActiveOverride(): void
    {
        $granted = (new EnlistmentWindowOverrideModel())->grant($this->studentId, AcademicYearModel::format('2026-2027', '2nd'), 'Shifted program', 70803, 'chairperson');

        $result = $this->withSession()->post('/chairperson/enlistment-window/revoke-override', [
            'override_id' => $granted['id'], csrf_token() => csrf_hash(),
        ]);

        $body = json_decode($result->response()->getBody(), true);
        $this->assertTrue($body['ok'], $body['error'] ?? '');
        $this->seeInDatabase('enlistment_window_overrides', ['id' => $granted['id'], 'status' => 'revoked']);
    }

    public function testRevokeRefusesAnOverrideOutsideTheChairsPrograms(): void
    {
        $granted = (new EnlistmentWindowOverrideModel())->grant($this->otherProgramStudentId, AcademicYearModel::format('2026-2027', '2nd'), 'Shifted program', 1, 'registrar');

        $result = $this->withSession()->post('/chairperson/enlistment-window/revoke-override', [
            'override_id' => $granted['id'], csrf_token() => csrf_hash(),
        ]);

        $body = json_decode($result->response()->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->seeInDatabase('enlistment_window_overrides', ['id' => $granted['id'], 'status' => 'active']);
    }
}
