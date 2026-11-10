<?php

use App\Models\AcademicYearModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\Traits\SeedsUsers;

/**
 * @internal
 */
final class ChairpersonExportSectionsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testExportWithNoAuthorizedSectionsReturnsHeaderOnlyWorkbook(): void
    {
        $db      = db_connect();
        $chairId = 70501;
        $this->seedUsers($chairId);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->get('/chairperson/sections/export?academic_year=' . rawurlencode('2026-2027 (1st Sem)'));

        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function testExportIncludesOnlySectionsFromTheRequestedTerm(): void
    {
        $db = db_connect();

        $db->table('colleges')->insert(['college_id' => 1, 'code' => 'COL1', 'name' => 'College 1']);
        $db->table('departments')->insert(['dept_id' => 2, 'college_id' => 1, 'code' => 'SD1', 'name' => 'Owning Unit']);
        $db->table('programs')->insert(['program_id' => 5101, 'department_id' => 2, 'program_code' => 'P5101', 'program_name' => 'BSIT', 'is_active' => 1]);

        $chairId = 70502;
        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => 2]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        $wantedTerm = AcademicYearModel::format('1');
        $otherTerm  = AcademicYearModel::format('2');

        $sections = new SectionModel();
        $sections->insert(['academic_year' => $wantedTerm, 'program' => 'BSIT', 'program_id' => 5101, 'year_level' => '1st Year', 'section' => '1A', 'capacity' => 40]);
        $sections->insert(['academic_year' => $otherTerm, 'program' => 'BSIT', 'program_id' => 5101, 'year_level' => '1st Year', 'section' => '1B']);

        $studentId = 70503;
        $this->seedUsers($studentId);
        $db->table('auth_groups_users')->insert(['user_id' => $studentId, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);
        $db->table('students')->insert(['user_id' => $studentId, 'applicant_id' => 'APP-70503', 'student_number' => 'STU-2026-00001']);
        $wantedSectionId = (int) $db->table('sections')->where('section', '1A')->get()->getRowArray()['section_id'];
        $db->table('users')->where('id', $studentId)->update(['section_id' => $wantedSectionId]);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        $result = $this->withSession()->get('/chairperson/sections/export?academic_year=' . rawurlencode($wantedTerm));
        $result->assertStatus(200);

        $tmp = tempnam(sys_get_temp_dir(), 'export_assert_') . '.xlsx';
        file_put_contents($tmp, $result->response()->getBody());
        $spreadsheet = IOFactory::load($tmp);
        unlink($tmp);

        $sectionsSheet = $spreadsheet->getSheetByName('Sections');
        $this->assertSame(1, $sectionsSheet->getHighestDataRow() - 1);
        $this->assertSame('1A', $sectionsSheet->getCell('C2')->getValue());

        $rosterSheet = $spreadsheet->getSheetByName('Roster');
        $this->assertSame('STU-2026-00001', $rosterSheet->getCell('D2')->getValue());
    }
}
