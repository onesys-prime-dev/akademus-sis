<?php

use App\Libraries\SchedulingAuthorization;
use App\Models\AuditLogModel;
use App\Models\DepartmentModel;
use App\Models\FacultyDepartmentModel;
use App\Models\ProgramModel;
use App\Models\SubDepartmentModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Admin academic-hierarchy transfer tools: Department -> College and
 * Program -> Department. Both are deliberately thin writes (one FK column
 * each) because everything downstream (programs/faculty/subjects for a
 * department move; curriculum/students/sections/enrollments for a program
 * move) keys off sub_dept_id / program_id directly rather than storing a
 * redundant college_id — so these tests exist to PROVE that "nothing else
 * needs to change" claim, not just that the UPDATE ran.
 *
 * @internal
 */
final class AcademicHierarchyTransferTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private static int $seq = 70000;

    private function newCollege(string $name): int
    {
        $id = ++self::$seq;
        db_connect()->table('departments')->insert(['dept_id' => $id, 'code' => 'COL' . $id, 'name' => $name]);

        return $id;
    }

    private function newDept(int $collegeId, string $name): int
    {
        $id = ++self::$seq;
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $id, 'college_id' => $collegeId, 'code' => 'SD' . $id, 'name' => $name]);

        return $id;
    }

    private function newProgram(int $subDeptId, string $code): int
    {
        db_connect()->table('programs')->insert(['sub_department_id' => $subDeptId, 'program_code' => $code, 'program_name' => $code . ' Title', 'is_active' => 1]);

        return (int) db_connect()->insertID();
    }

    private function newAdminUser(): int
    {
        $id = ++self::$seq;
        $this->seedUsers($id);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'admin', 'created_at' => date('Y-m-d H:i:s')]);

        return $id;
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs(auth()->getProvider()->findById($this->newAdminUser()));
    }

    private function postAs(string $url, array $data)
    {
        return $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->withSession()->post($url, $data);
    }

    // --- Department transfer -------------------------------------------------

    public function testDepartmentTransferMovesCollegeAndSyncsPrimaryUsers(): void
    {
        $collegeA = $this->newCollege('College A');
        $collegeB = $this->newCollege('College B');
        $dept     = $this->newDept($collegeA, 'Department of Testing');
        $faculty  = ++self::$seq;
        $this->seedUsers($faculty);
        db_connect()->table('users')->where('id', $faculty)->update(['sub_department_id' => $dept, 'department_id' => $collegeA]);

        $this->actingAsAdmin();

        $response = $this->postAs("/admin/sub-departments/{$dept}/transfer", [
            'college_id' => $collegeB,
            'reason'     => 'Reorganization test',
        ]);

        $response->assertRedirect();
        $this->seeInDatabase('sub_departments', ['sub_dept_id' => $dept, 'college_id' => $collegeB]);
        // The faculty member's college pointer must follow their department,
        // not silently point at the OLD college after the move.
        $this->seeInDatabase('users', ['id' => $faculty, 'department_id' => $collegeB]);
    }

    public function testDepartmentTransferWithoutReasonIsRejected(): void
    {
        $collegeA = $this->newCollege('College A');
        $collegeB = $this->newCollege('College B');
        $dept     = $this->newDept($collegeA, 'Department of Testing');

        $this->actingAsAdmin();

        $this->postAs("/admin/sub-departments/{$dept}/transfer", ['college_id' => $collegeB, 'reason' => '']);

        // Rejected — still under the original college.
        $this->seeInDatabase('sub_departments', ['sub_dept_id' => $dept, 'college_id' => $collegeA]);
    }

    public function testDepartmentTransferIsAudited(): void
    {
        $collegeA = $this->newCollege('College A');
        $collegeB = $this->newCollege('College B');
        $dept     = $this->newDept($collegeA, 'Department of Testing');

        $this->actingAsAdmin();
        $this->postAs("/admin/sub-departments/{$dept}/transfer", ['college_id' => $collegeB, 'reason' => 'Audit check']);

        $log = (new AuditLogModel())->where('action', 'department.transferred')->where('entity_id', (string) $dept)->first();

        $this->assertNotNull($log);
        $this->assertSame('Audit check', $log['reason']);
        $before = json_decode($log['before_json'], true);
        $after  = json_decode($log['after_json'], true);
        $this->assertSame($collegeA, $before['college_id']);
        $this->assertSame($collegeB, $after['college_id']);
    }

    public function testDepartmentTransferPreviewReportsAffectedCountsWithoutWriting(): void
    {
        $collegeA = $this->newCollege('College A');
        $collegeB = $this->newCollege('College B');
        $dept     = $this->newDept($collegeA, 'Department of Testing');
        $this->newProgram($dept, 'TP101');
        db_connect()->table('subjects')->insert(['subject_code' => 'TSUB1', 'subject_name' => 'Test Subject', 'units' => 3, 'sub_department_id' => $dept]);
        $facultyUserId = ++self::$seq;
        $this->seedUsers($facultyUserId);
        $affResult = (new FacultyDepartmentModel())->addAffiliation($facultyUserId, $dept, true, 1, 'admin');
        $this->assertTrue($affResult['ok'], $affResult['error'] ?? '');

        $this->actingAsAdmin();
        $response = $this->postAs("/admin/sub-departments/{$dept}/transfer/preview", ['college_id' => $collegeB]);

        $response->assertOK();
        $body = json_decode($response->getJSON(), true);

        $this->assertTrue($body['ok']);
        $this->assertSame(1, $body['affected']['programs']);
        $this->assertSame(1, $body['affected']['subjects']);
        $this->assertSame(1, $body['affected']['faculty']);
        // A preview must never write anything.
        $this->seeInDatabase('sub_departments', ['sub_dept_id' => $dept, 'college_id' => $collegeA]);
    }

    // --- Program transfer ----------------------------------------------------

    public function testProgramTransferMovesDepartmentAndIsAudited(): void
    {
        $college = $this->newCollege('College A');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $program = $this->newProgram($deptA, 'PT101');

        $this->actingAsAdmin();
        $response = $this->postAs("/admin/programs/{$program}/transfer", ['sub_department_id' => $deptB, 'reason' => 'Program realignment']);

        $response->assertRedirect();
        $this->seeInDatabase('programs', ['program_id' => $program, 'sub_department_id' => $deptB]);

        $log = (new AuditLogModel())->where('action', 'program.transferred')->where('entity_id', (string) $program)->first();
        $this->assertNotNull($log);
        $this->assertSame('Program realignment', $log['reason']);
    }

    public function testProgramTransferWithoutReasonIsRejected(): void
    {
        $college = $this->newCollege('College A');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $program = $this->newProgram($deptA, 'PT101');

        $this->actingAsAdmin();
        $this->postAs("/admin/programs/{$program}/transfer", ['sub_department_id' => $deptB, 'reason' => '']);

        $this->seeInDatabase('programs', ['program_id' => $program, 'sub_department_id' => $deptA]);
    }

    /**
     * The core claim this whole feature rests on: curriculum, students,
     * sections, and enrollments are untouched by a program transfer because
     * none of them store a department — they key off program_id (or, for
     * sections, the program's own display name).
     */
    public function testProgramTransferPreservesCurriculumStudentsSectionsAndEnrollments(): void
    {
        $college = $this->newCollege('College A');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $program = $this->newProgram($deptA, 'PT202');

        db_connect()->table('curriculum')->insert([
            'program_id' => $program, 'curriculum_code' => 'PT202-C1', 'curriculum_name' => 'PT202 Curriculum',
            'academic_year' => '2026-2027', 'is_active' => 1, 'status' => 'published', 'is_current' => 1,
        ]);
        $curriculumId = (int) db_connect()->insertID();

        $student = ++self::$seq;
        $this->seedUsers($student);
        db_connect()->table('students')->insert([
            'user_id' => $student, 'applicant_id' => 'APP' . $student, 'student_number' => 'SN' . $student,
            'program_id' => $program, 'curriculum_id' => $curriculumId, 'student_status' => 'active',
        ]);

        db_connect()->table('sections')->insert(['academic_year' => 'AY 1 · 1 Semester', 'program' => 'PT202 Title', 'year_level' => '1st Year', 'section' => 'A']);
        $sectionId = (int) db_connect()->insertID();

        db_connect()->table('enrollments')->insert(['user_id' => $student, 'section_id' => $sectionId, 'subject_code' => 'GEC101', 'status' => 'enrolled']);

        $this->actingAsAdmin();
        $this->postAs("/admin/programs/{$program}/transfer", ['sub_department_id' => $deptB, 'reason' => 'Preservation check']);

        $this->assertSame($deptB, (int) (new ProgramModel())->find($program)['sub_department_id']);
        // Everything else: completely untouched.
        $this->seeInDatabase('curriculum', ['curriculum_id' => $curriculumId, 'program_id' => $program]);
        $this->seeInDatabase('students', ['user_id' => $student, 'program_id' => $program, 'curriculum_id' => $curriculumId]);
        $this->seeInDatabase('sections', ['section_id' => $sectionId, 'program' => 'PT202 Title']);
        $this->seeInDatabase('enrollments', ['user_id' => $student, 'section_id' => $sectionId, 'subject_code' => 'GEC101']);
    }

    // --- Subject ownership unaffected ----------------------------------------

    public function testProgramTransferDoesNotTouchSubjectOwnership(): void
    {
        $college = $this->newCollege('College A');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $program = $this->newProgram($deptA, 'PT303');
        // A subject still owned by deptA, independent of the program move.
        db_connect()->table('subjects')->insert(['subject_code' => 'PT303SUB', 'subject_name' => 'Subject', 'units' => 3, 'sub_department_id' => $deptA]);

        $this->actingAsAdmin();
        $this->postAs("/admin/programs/{$program}/transfer", ['sub_department_id' => $deptB, 'reason' => 'Ownership check']);

        $this->seeInDatabase('subjects', ['subject_code' => 'PT303SUB', 'sub_department_id' => $deptA]);
    }

    // --- Scheduling authorization re-evaluates live --------------------------

    public function testSchedulingAuthorizationReflectsDepartmentTransferImmediately(): void
    {
        $college = $this->newCollege('College A');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $subjectId = $this->seedSubject('SA101', $deptA);

        $chairA = ++self::$seq;
        $this->seedUsers($chairA);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $chairA, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        db_connect()->table('users')->where('id', $chairA)->update(['sub_department_id' => $deptA]);

        $auth = new SchedulingAuthorization();
        $this->assertTrue($auth->canManageSubject($chairA, $subjectId), 'Chair A must manage the subject before any transfer.');

        // Transfer the SUBJECT'S department itself to a new college — chair
        // A's authorization is unaffected (still the same sub_dept_id, just
        // a different parent college), proving department transfer doesn't
        // accidentally touch subject-level authorization.
        $this->actingAsAdmin();
        $newCollege = $this->newCollege('College B');
        $this->postAs("/admin/sub-departments/{$deptA}/transfer", ['college_id' => $newCollege, 'reason' => 'Authorization check']);

        $this->assertTrue((new SchedulingAuthorization())->canManageSubject($chairA, $subjectId), 'Authorization must survive a college-level transfer unchanged.');
    }

    public function testDeanScopeFollowsDepartmentAfterCollegeTransfer(): void
    {
        $collegeA = $this->newCollege('College A');
        $collegeB = $this->newCollege('College B');
        $dept     = $this->newDept($collegeA, 'Department of Testing');

        $this->actingAsAdmin();
        $this->postAs("/admin/sub-departments/{$dept}/transfer", ['college_id' => $collegeB, 'reason' => 'Dean scope check']);

        // A "Dean sees all departments under their college" check, exactly
        // as Admin\DepartmentController::show() computes it.
        $departmentsUnderB = db_connect()->table('sub_departments')->where('college_id', $collegeB)->countAllResults();
        $departmentsUnderA = db_connect()->table('sub_departments')->where('college_id', $collegeA)->countAllResults();

        $this->assertSame(1, $departmentsUnderB, 'Dean of College B must now see the transferred department.');
        $this->assertSame(0, $departmentsUnderA, 'Dean of College A must no longer see it.');
    }

    public function testChairpersonScopeFollowsProgramAfterDepartmentTransfer(): void
    {
        $college = $this->newCollege('College A');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $program = $this->newProgram($deptA, 'PT404');

        $chairA = ++self::$seq;
        $chairB = ++self::$seq;
        $this->seedUsers($chairA);
        $this->seedUsers($chairB);
        db_connect()->table('users')->where('id', $chairA)->update(['sub_department_id' => $deptA]);
        db_connect()->table('users')->where('id', $chairB)->update(['sub_department_id' => $deptB]);

        $dept = new DepartmentModel();
        $this->assertContains('PT404 Title', $dept->programsOfSubDept($deptA));
        $this->assertNotContains('PT404 Title', $dept->programsOfSubDept($deptB));

        $this->actingAsAdmin();
        $this->postAs("/admin/programs/{$program}/transfer", ['sub_department_id' => $deptB, 'reason' => 'Chair scope check']);

        $dept = new DepartmentModel();
        $this->assertNotContains('PT404 Title', $dept->programsOfSubDept($deptA), 'Department A chair must lose scope over the moved program.');
        $this->assertContains('PT404 Title', $dept->programsOfSubDept($deptB), 'Department B chair must gain scope over the moved program.');
    }

    private function seedSubject(string $code, int $subDeptId): int
    {
        db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $code . ' Title', 'units' => 3, 'sub_department_id' => $subDeptId]);

        return (int) db_connect()->insertID();
    }
}
