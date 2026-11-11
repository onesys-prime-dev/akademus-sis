<?php

use App\Controllers\ChairpersonController;
use App\Models\AcademicYearModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Exercises ChairpersonController::processSectionsImport() directly
 * (via reflection, since it is private) rather than through HTTP: this
 * project's FeatureTestTrait has no multipart file-upload helper, so the
 * full accept path can't be simulated through $this->post(). See
 * ChairpersonImportSectionsRouteTest for the HTTP-level reject-path
 * coverage that doesn't need a real upload.
 *
 * @internal
 */
final class ChairpersonImportSectionsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private int $chairId = 70601;

    private string $term;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        $db->table('colleges')->insert(['college_id' => 1, 'code' => 'COL1', 'name' => 'College 1']);
        $db->table('departments')->insert(['dept_id' => 2, 'college_id' => 1, 'code' => 'SD1', 'name' => 'Owning Unit']);
        $db->table('programs')->insert(['program_id' => 6101, 'department_id' => 2, 'program_code' => 'P6101', 'program_name' => 'BSIT', 'is_active' => 1]);

        $this->seedUsers($this->chairId);
        $db->table('users')->where('id', $this->chairId)->update(['sub_department_id' => 2]);
        $db->table('auth_groups_users')->insert(['user_id' => $this->chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById($this->chairId));

        $this->term = AcademicYearModel::format('1');
        $db->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);
    }

    private function callProcessSectionsImport(array $sectionRows, array $rosterRows, string $targetTerm, bool $createMissingStudents = false): array
    {
        $controller = new ChairpersonController();
        $method     = new ReflectionMethod($controller, 'processSectionsImport');
        $method->setAccessible(true);

        return $method->invoke($controller, $sectionRows, $rosterRows, $targetTerm, $createMissingStudents);
    }

    public function testProcessCreatesNewSectionsAndSkipsExisting(): void
    {
        (new SectionModel())->insert(['academic_year' => $this->term, 'program' => 'BSIT', 'program_id' => 6101, 'year_level' => '1st Year', 'section' => '1A']);

        $report = $this->callProcessSectionsImport(
            sectionRows: [
                ['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => '40'],
                ['row' => 3, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1B', 'capacity' => null],
            ],
            rosterRows: [],
            targetTerm: $this->term
        );

        $this->assertSame(1, $report['sections']['created']);
        $this->assertSame(1, $report['sections']['skipped']);
        $this->assertSame([], $report['sections']['errors']);
    }

    public function testProcessReportsInvalidCapacityAsARowError(): void
    {
        $report = $this->callProcessSectionsImport(
            sectionRows: [['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => '-5']],
            rosterRows: [],
            targetTerm: $this->term
        );

        $this->assertSame(0, $report['sections']['created']);
        $this->assertCount(1, $report['sections']['errors']);
        $this->assertSame(2, $report['sections']['errors'][0]['row']);
    }

    public function testProcessReportsZeroAndNonNumericCapacityAsRowErrorsToo(): void
    {
        $report = $this->callProcessSectionsImport(
            sectionRows: [
                ['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => '0'],
                ['row' => 3, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1B', 'capacity' => 'abc'],
            ],
            rosterRows: [],
            targetTerm: $this->term
        );

        $this->assertSame(0, $report['sections']['created']);
        $this->assertCount(2, $report['sections']['errors']);
    }

    public function testProcessReportsDuplicateRowsWithinTheSameFileAsSkipped(): void
    {
        $row = ['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => null];
        $dup = ['row' => 3, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => null];

        $report = $this->callProcessSectionsImport([$row, $dup], [], $this->term);

        $this->assertSame(1, $report['sections']['created']);
        $this->assertSame(1, $report['sections']['skipped']);
    }

    public function testProcessReportsRosterRowForMissingSectionAsAnError(): void
    {
        $report = $this->callProcessSectionsImport(
            sectionRows: [],
            rosterRows: [['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1Z', 'student_number' => 'STU-0001']],
            targetTerm: $this->term
        );

        $this->assertSame(0, $report['roster']['assigned']);
        $this->assertCount(1, $report['roster']['errors']);
        $this->assertStringContainsString('not found', $report['roster']['errors'][0]['reason']);
    }

    public function testProcessReportsRosterRowForUnknownStudentNumberAsAnError(): void
    {
        (new SectionModel())->insert(['academic_year' => $this->term, 'program' => 'BSIT', 'program_id' => 6101, 'year_level' => '1st Year', 'section' => '1A']);

        $report = $this->callProcessSectionsImport(
            sectionRows: [],
            rosterRows: [['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'student_number' => 'NOPE']],
            targetTerm: $this->term
        );

        $this->assertSame(0, $report['roster']['assigned']);
        $this->assertCount(1, $report['roster']['errors']);
        $this->assertSame('Student not found.', $report['roster']['errors'][0]['reason']);
    }

    public function testProcessRejectsARosterRowForASectionOutsideTheChairpersonsAuthorizedPrograms(): void
    {
        $db = db_connect();
        $db->table('departments')->insert(['dept_id' => 3, 'college_id' => 1, 'code' => 'SD2', 'name' => 'Other Unit']);
        $db->table('programs')->insert(['program_id' => 6102, 'department_id' => 3, 'program_code' => 'P6102', 'program_name' => 'Other Program', 'is_active' => 1]);
        (new SectionModel())->insert(['academic_year' => $this->term, 'program' => 'Other Program', 'program_id' => 6102, 'year_level' => '1st Year', 'section' => '1A']);

        $studentId = 70603;
        $this->seedUsers($studentId);
        $db->table('auth_groups_users')->insert(['user_id' => $studentId, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);
        $db->table('students')->insert(['user_id' => $studentId, 'applicant_id' => 'APP-70603', 'student_number' => 'STU-0003']);

        $report = $this->callProcessSectionsImport(
            sectionRows: [],
            rosterRows: [['row' => 2, 'program' => 'Other Program', 'year_level' => '1st Year', 'section' => '1A', 'student_number' => 'STU-0003']],
            targetTerm: $this->term
        );

        $this->assertSame(0, $report['roster']['assigned']);
        $this->assertCount(1, $report['roster']['errors']);
        $this->assertNull(db_connect()->table('users')->select('section_id')->where('id', $studentId)->get()->getRowArray()['section_id']);
    }

    public function testProcessRollsBackEverythingAndReportsFailureWhenAWriteFails(): void
    {
        $report = $this->callProcessSectionsImport(
            sectionRows: [
                ['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'capacity' => null],
                ['row' => 3, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1B', 'capacity' => '99999999999999'],
            ],
            rosterRows: [],
            targetTerm: $this->term
        );

        $this->assertFalse($report['ok']);
        $this->assertNull((new SectionModel())->forContext($this->term, 'BSIT', '1st Year', '1A'));
    }

    public function testProcessAssignsAStudentByStudentNumber(): void
    {
        (new SectionModel())->insert(['academic_year' => $this->term, 'program' => 'BSIT', 'program_id' => 6101, 'year_level' => '1st Year', 'section' => '1A']);

        $studentId = 70602;
        $this->seedUsers($studentId);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $studentId, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);
        db_connect()->table('students')->insert(['user_id' => $studentId, 'applicant_id' => 'APP-70602', 'student_number' => 'STU-0002']);

        $report = $this->callProcessSectionsImport(
            sectionRows: [],
            rosterRows: [['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'student_number' => 'STU-0002']],
            targetTerm: $this->term
        );

        $this->assertSame(1, $report['roster']['assigned']);
        $this->assertSame([], $report['roster']['errors']);
    }

    public function testProcessLeavesAnUnknownStudentNumberAsAnErrorWhenCreateMissingIsNotOptedIn(): void
    {
        (new SectionModel())->insert(['academic_year' => $this->term, 'program' => 'BSIT', 'program_id' => 6101, 'year_level' => '1st Year', 'section' => '1A']);

        $report = $this->callProcessSectionsImport(
            sectionRows: [],
            rosterRows: [['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'student_number' => 'STU-9999', 'student_name' => 'Mathew Umali', 'email' => 'mathew@example.com']],
            targetTerm: $this->term,
            createMissingStudents: false
        );

        $this->assertSame(0, $report['roster']['assigned']);
        $this->assertSame(0, $report['roster']['accounts_created']);
        $this->assertSame('Student not found.', $report['roster']['errors'][0]['reason']);
    }

    public function testProcessCreatesAMissingStudentAndAssignsThemWhenOptedIn(): void
    {
        (new SectionModel())->insert(['academic_year' => $this->term, 'program' => 'BSIT', 'program_id' => 6101, 'year_level' => '1st Year', 'section' => '1A']);

        $report = $this->callProcessSectionsImport(
            sectionRows: [],
            rosterRows: [['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'student_number' => 'STU-9999', 'student_name' => 'Mathew Umali', 'email' => 'mathew@example.com']],
            targetTerm: $this->term,
            createMissingStudents: true
        );

        $this->assertSame(1, $report['roster']['assigned']);
        $this->assertSame(1, $report['roster']['accounts_created']);
        $this->assertSame([], $report['roster']['errors']);

        $user = db_connect()->table('users')->where('first_name', 'Mathew')->where('last_name', 'Umali')->get()->getRowArray();
        $this->assertNotNull($user);
        $this->assertNotNull($user['section_id'], 'the newly created student must end up assigned to the section');
    }

    public function testProcessReportsAMissingNameOrEmailAsARowErrorWhenCreatingIsOptedIn(): void
    {
        (new SectionModel())->insert(['academic_year' => $this->term, 'program' => 'BSIT', 'program_id' => 6101, 'year_level' => '1st Year', 'section' => '1A']);

        $report = $this->callProcessSectionsImport(
            sectionRows: [],
            rosterRows: [['row' => 2, 'program' => 'BSIT', 'year_level' => '1st Year', 'section' => '1A', 'student_number' => 'STU-9999', 'student_name' => 'Mathew Umali', 'email' => '']],
            targetTerm: $this->term,
            createMissingStudents: true
        );

        $this->assertSame(0, $report['roster']['assigned']);
        $this->assertSame(0, $report['roster']['accounts_created']);
        $this->assertStringContainsString('Missing name/email', $report['roster']['errors'][0]['reason']);
    }
}
