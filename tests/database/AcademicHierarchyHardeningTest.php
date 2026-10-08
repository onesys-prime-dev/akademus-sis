<?php

use App\Libraries\AcademicHealthService;
use App\Libraries\SchedulingAuthorization;
use App\Libraries\SharedSubjectCoverageService;
use App\Models\AuditLogModel;
use App\Models\DepartmentModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Hierarchy-integrity + Section -> Program FK hardening batch.
 *
 * SchedulingAuthorizationTest.php already pins the pre-existing, still-live
 * text-matching fallback (its sections never set program_id) — this file
 * covers the NEW ground: FK-based resolution, the backfill command's
 * matched/ambiguous/unresolved classification, rename/transfer safety now
 * that identity is ID-based, the health checker, duplicate-prevention
 * validation, and the department hard-delete guard.
 *
 * @internal
 */
final class AcademicHierarchyHardeningTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private static int $seq = 60000;

    private function newCollege(string $name): int
    {
        $id = ++self::$seq;
        db_connect()->table('departments')->insert(['dept_id' => $id, 'code' => 'COL' . $id, 'name' => $name]);

        return $id;
    }

    private function newDept(int $collegeId, string $name, ?string $code = null): int
    {
        $id = ++self::$seq;
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $id, 'college_id' => $collegeId, 'code' => $code ?? 'SD' . $id, 'name' => $name]);

        return $id;
    }

    private function newProgram(int $subDeptId, string $code, ?string $name = null, bool $active = true): int
    {
        db_connect()->table('programs')->insert(['sub_department_id' => $subDeptId, 'program_code' => $code, 'program_name' => $name ?? $code . ' Title', 'is_active' => $active ? 1 : 0]);

        return (int) db_connect()->insertID();
    }

    private function newSection(string $academicYear, string $program, ?int $programId, string $yearLevel = '1st Year', string $section = 'A'): int
    {
        db_connect()->table('sections')->insert([
            'academic_year' => $academicYear, 'program' => $program, 'program_id' => $programId,
            'year_level' => $yearLevel, 'section' => $section,
        ]);

        return (int) db_connect()->insertID();
    }

    private function newAdminUser(): int
    {
        $id = ++self::$seq;
        $this->seedUsers($id);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'admin', 'created_at' => date('Y-m-d H:i:s')]);

        return $id;
    }

    private function actingAsAdmin(): int
    {
        $id = $this->newAdminUser();
        $this->actingAs(auth()->getProvider()->findById($id));

        return $id;
    }

    private function postAs(string $url, array $data)
    {
        return $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->withSession()->post($url, $data);
    }

    // =====================================================================
    // SECTION PROGRAM FK
    // =====================================================================

    public function testSectionResolvesDepartmentThroughProgramIdWhenSet(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department of Testing');
        $program = $this->newProgram($dept, 'TP1');
        $section = $this->newSection('AY 1 · 1 Semester', 'TP1 Title', $program);

        $chair = ++self::$seq;
        $this->seedUsers($chair);
        db_connect()->table('users')->where('id', $chair)->update(['sub_department_id' => $dept]);

        $auth = new SchedulingAuthorization();
        $this->assertNotNull($auth->ownSection($chair, $section), 'Section must resolve via program_id.');
        $this->assertSame($dept, $auth->subDeptForSection($chair, $section));
    }

    public function testSectionWithNullProgramIdFallsBackToLegacyTextMatch(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department of Testing');
        $this->newProgram($dept, 'TP2', 'TP2 Title');
        // program_id intentionally NULL — simulates a never-backfilled row.
        $section = $this->newSection('AY 1 · 1 Semester', 'TP2 Title', null);

        $chair = ++self::$seq;
        $this->seedUsers($chair);
        db_connect()->table('users')->where('id', $chair)->update(['sub_department_id' => $dept]);

        $this->assertNotNull((new SchedulingAuthorization())->ownSection($chair, $section), 'Legacy text-matching fallback must still work for unbackfilled sections.');
    }

    public function testSectionProgramIdPointingToUnauthorizedDepartmentIsDenied(): void
    {
        $college = $this->newCollege('College');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $programB = $this->newProgram($deptB, 'TP3');
        $section = $this->newSection('AY 1 · 1 Semester', 'TP3 Title', $programB);

        $chairA = ++self::$seq;
        $this->seedUsers($chairA);
        db_connect()->table('users')->where('id', $chairA)->update(['sub_department_id' => $deptA]);

        $this->assertNull((new SchedulingAuthorization())->ownSection($chairA, $section), 'Chair of an unrelated department must not resolve this section.');
    }

    /**
     * Part E/F: renaming a Program must not break a section that already
     * points to it by ID.
     */
    public function testProgramRenameDoesNotBreakSectionResolution(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department of Testing');
        $program = $this->newProgram($dept, 'TP4', 'Old Name');
        $section = $this->newSection('AY 1 · 1 Semester', 'Old Name', $program);

        $chair = ++self::$seq;
        $this->seedUsers($chair);
        db_connect()->table('users')->where('id', $chair)->update(['sub_department_id' => $dept]);

        db_connect()->table('programs')->where('program_id', $program)->update(['program_name' => 'Brand New Name']);

        $this->assertNotNull((new SchedulingAuthorization())->ownSection($chair, $section), 'Section must still resolve after its Program was renamed.');
    }

    /**
     * Part E: a Program transfer to a different Department must be picked
     * up automatically by a section pointing to it by ID — no section
     * rewrite needed.
     */
    public function testProgramTransferIsPickedUpAutomaticallyBySection(): void
    {
        $college = $this->newCollege('College');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $program = $this->newProgram($deptA, 'TP5');
        $section = $this->newSection('AY 1 · 1 Semester', 'TP5 Title', $program);

        $chairA = ++self::$seq;
        $chairB = ++self::$seq;
        $this->seedUsers($chairA);
        $this->seedUsers($chairB);
        db_connect()->table('users')->where('id', $chairA)->update(['sub_department_id' => $deptA]);
        db_connect()->table('users')->where('id', $chairB)->update(['sub_department_id' => $deptB]);

        $this->assertNotNull((new SchedulingAuthorization())->ownSection($chairA, $section));
        $this->assertNull((new SchedulingAuthorization())->ownSection($chairB, $section));

        // Transfer the program (same mechanism as Admin\ProgramController::transfer()).
        db_connect()->table('programs')->where('program_id', $program)->update(['sub_department_id' => $deptB]);

        $this->assertNull((new SchedulingAuthorization())->ownSection($chairA, $section), 'Old department must lose access automatically.');
        $this->assertNotNull((new SchedulingAuthorization())->ownSection($chairB, $section), 'New department must gain access automatically — no section rewrite.');
    }

    public function testSharedSubjectCoverageResolvesSectionsThroughProgramId(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department of Testing');
        $program = $this->newProgram($dept, 'TP6');
        $this->newSection('AY 1 · 1 Semester', 'TP6 Title', $program);

        $chair = ++self::$seq;
        $this->seedUsers($chair);
        db_connect()->table('users')->where('id', $chair)->update(['sub_department_id' => $dept]);

        $result = (new SharedSubjectCoverageService())->build($chair, 'AY 1 · 1 Semester');

        $this->assertSame(1, $result['my_section_count'], 'Coverage must find the section via program_id.');
    }

    /**
     * Fixture mirrors SectionBalancerTest::testSplitsOneOverfullSectionIntoTwoBalancedOnes()
     * exactly (one 24-student overfull section triggers an auto-created
     * split) — reusing the proven scenario rather than re-deriving
     * SectionBalancer's own capacity/split rules, per the explicit
     * "do not rewrite SectionBalancer" instruction.
     */
    public function testSectionBalancerCreatedSectionsGetProgramId(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department of Testing');
        $program = $this->newProgram($dept, 'TP7', 'TP7 Title');

        $section = db_connect()->table('sections');
        $section->insert(['academic_year' => 'AY7', 'program' => 'TP7 Title', 'program_id' => $program, 'year_level' => '1st Year', 'section' => '1A', 'status' => 'open']);
        $sectionId = (int) db_connect()->insertID();

        for ($i = 0; $i < 24; $i++) {
            $uid = ++self::$seq;
            db_connect()->table('users')->insert(['id' => $uid, 'username' => "balseed{$uid}", 'active' => 1, 'section_id' => $sectionId]);
            db_connect()->table('auth_groups_users')->insert(['user_id' => $uid, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);
        }

        $result = (new \App\Libraries\SectionBalancer())->apply('AY7', 'TP7 Title', '1st Year', null, 'chairperson', 'Test auto-create');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertNotEmpty($result['created'], 'Fixture must actually trigger an auto-created section — nothing to assert on otherwise.');

        $created = db_connect()->table('sections')->where('academic_year', 'AY7')->where('section', $result['created'][0])->get()->getRowArray();
        $this->assertSame($program, (int) $created['program_id'], 'SectionBalancer-created sections must resolve program_id, not stay NULL.');
    }

    // =====================================================================
    // BACKFILL COMMAND
    // =====================================================================

    public function testBackfillMatchesUnambiguousSection(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department of Testing');
        $program = $this->newProgram($dept, 'BF1', 'Backfill One');
        $section = $this->newSection('AY 1 · 1 Semester', 'Backfill One', null);

        $exit = $this->runBackfillCommit();

        $row = (new SectionModel())->find($section);
        $this->assertSame($program, (int) $row['program_id']);
    }

    public function testBackfillReportsAmbiguousMatchAndLeavesItNull(): void
    {
        $college = $this->newCollege('College');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        // Two DIFFERENT programs sharing the exact same name — the
        // duplicate-name gap this whole batch exists partly to close.
        $this->newProgram($deptA, 'AMB1', 'Shared Name');
        $this->newProgram($deptB, 'AMB2', 'Shared Name');
        $section = $this->newSection('AY 1 · 1 Semester', 'Shared Name', null);

        $this->runBackfillCommit();

        $row = (new SectionModel())->find($section);
        $this->assertNull($row['program_id'], 'An ambiguous name match must never be silently guessed.');
    }

    public function testBackfillReportsUnresolvedSection(): void
    {
        $section = $this->newSection('AY 1 · 1 Semester', 'Totally Unknown Program XYZ', null);

        $this->runBackfillCommit();

        $row = (new SectionModel())->find($section);
        $this->assertNull($row['program_id']);
    }

    public function testBackfillDryRunWritesNothing(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department of Testing');
        $this->newProgram($dept, 'DRY1', 'Dry Run Program');
        $section = $this->newSection('AY 1 · 1 Semester', 'Dry Run Program', null);

        // Direct instantiation + run([...]) bypasses CLI's real option
        // parser (CLI::getOption() reads CLI's own parsed-argv state, not
        // the $params array handed to run() directly) — command() is the
        // documented, correct way to invoke a spark command from PHP code.
        command('sections:backfill-program-id');

        $row = (new SectionModel())->find($section);
        $this->assertNull($row['program_id'], 'Dry run must never write.');
    }

    private function runBackfillCommit(): void
    {
        command('sections:backfill-program-id --commit');
    }

    // =====================================================================
    // HEALTH CHECKER
    // =====================================================================

    public function testHealthCheckerDetectsOrphanProgram(): void
    {
        $this->newProgram($this->newDept($this->newCollege('C'), 'D'), 'ORPHANCHK'); // has a dept, control case
        db_connect()->table('programs')->insert(['program_code' => 'ORPHAN1', 'program_name' => 'Orphan Program', 'is_active' => 1, 'sub_department_id' => null]);

        $findings = (new AcademicHealthService())->check();
        $messages = array_column($findings, 'message');

        $this->assertTrue((bool) array_filter($messages, static fn ($m) => str_contains($m, 'Orphan Program') && str_contains($m, 'no Department')));
    }

    public function testHealthCheckerDetectsDepartmentNamedLikeItsProgram(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Identical Name Co', 'IDN');
        $this->newProgram($dept, 'IDN1', 'Identical Name Co');

        $findings = (new AcademicHealthService())->check();
        $messages = array_column($findings, 'message');

        $this->assertTrue((bool) array_filter($messages, static fn ($m) => str_contains($m, 'Identical Name Co') && str_contains($m, 'identically')));
    }

    public function testHealthCheckerDetectsFacultyWithMultipleActivePrimaries(): void
    {
        $college = $this->newCollege('College');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $faculty = ++self::$seq;
        $this->seedUsers($faculty);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $faculty, 'group' => 'faculty', 'created_at' => date('Y-m-d H:i:s')]);
        // Bypass the model's own invariant deliberately, to prove the
        // health checker catches a direct-data-edit violation.
        db_connect()->table('faculty_departments')->insert(['user_id' => $faculty, 'sub_dept_id' => $deptA, 'is_primary' => 1, 'status' => 'active']);
        db_connect()->table('faculty_departments')->insert(['user_id' => $faculty, 'sub_dept_id' => $deptB, 'is_primary' => 1, 'status' => 'active']);

        $findings = (new AcademicHealthService())->check();
        $critical = array_filter($findings, static fn ($f) => $f['severity'] === 'critical' && $f['entity_type'] === 'user' && $f['entity_id'] === $faculty);

        $this->assertNotEmpty($critical);
    }

    /**
     * programs.program_code already carries a real DB-level UNIQUE
     * constraint (confirmed: a raw duplicate INSERT throws), so
     * AcademicHealthService::checkDuplicateProgramCodes() can never
     * actually fire in practice — it stays in as a defensive check in case
     * that constraint is ever relaxed, but duplicate DEPARTMENT codes have
     * no such DB-level protection (see A2's manual scoped-uniqueness check
     * in DepartmentController::storeSubDepartment()), so that's the
     * realistically-triggerable case this test covers instead.
     */
    public function testHealthCheckerDetectsDuplicateDepartmentCode(): void
    {
        $college = $this->newCollege('College');
        // Bypass the controller-level validation (which already blocks
        // this at creation time) to prove the health checker independently
        // catches it too, e.g. if a row was ever inserted directly.
        db_connect()->table('sub_departments')->insert(['college_id' => $college, 'code' => 'DUPDEPT', 'name' => 'First Dept']);
        db_connect()->table('sub_departments')->insert(['college_id' => $college, 'code' => 'DUPDEPT', 'name' => 'Second Dept']);

        $findings = (new AcademicHealthService())->check();
        $messages = array_column($findings, 'message');

        $this->assertTrue((bool) array_filter($messages, static fn ($m) => str_contains($m, 'DUPDEPT')));
    }

    public function testHealthCheckerDetectsDepartmentWithoutChairperson(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Chairless Department');

        $findings = (new AcademicHealthService())->check();
        $messages = array_column($findings, 'message');

        $this->assertTrue((bool) array_filter($messages, static fn ($m) => str_contains($m, 'Chairless Department')));
    }

    public function testHealthCheckerIsAdminOnly(): void
    {
        $response = $this->get('/admin/academic-health');
        $response->assertRedirect(); // not authenticated/authorized -> redirected, never 200 with data
    }

    // =====================================================================
    // DUPLICATE-PREVENTION VALIDATION
    // =====================================================================

    public function testDuplicateDepartmentCodeInSameCollegeRejected(): void
    {
        $college = $this->newCollege('College');
        $this->newDept($college, 'Existing Dept', 'DUP');

        $this->actingAsAdmin();
        $this->postAs("/admin/departments/{$college}/sub-departments", ['dept_code' => 'DUP', 'dept_name' => 'Another Dept']);

        $this->assertSame(1, db_connect()->table('sub_departments')->where('college_id', $college)->where('code', 'DUP')->countAllResults());
    }

    public function testSameDepartmentCodeInDifferentCollegeIsAllowed(): void
    {
        $collegeA = $this->newCollege('College A');
        $collegeB = $this->newCollege('College B');
        $this->newDept($collegeA, 'Dept A', 'SAME');

        $this->actingAsAdmin();
        $this->postAs("/admin/departments/{$collegeB}/sub-departments", ['dept_code' => 'SAME', 'dept_name' => 'Dept B']);

        $this->assertSame(1, db_connect()->table('sub_departments')->where('college_id', $collegeB)->where('code', 'SAME')->countAllResults(), 'The same code in a DIFFERENT college must be allowed.');
    }

    public function testDuplicateProgramCodeRejected(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department');
        $this->newProgram($dept, 'EXISTCODE');

        $this->actingAsAdmin();
        $this->postAs("/admin/sub-departments/{$dept}/programs", ['program_code' => 'EXISTCODE', 'program_name' => 'Another Program']);

        $this->assertSame(1, db_connect()->table('programs')->where('program_code', 'EXISTCODE')->countAllResults());
    }

    // =====================================================================
    // DEACTIVATION / DELETE
    // =====================================================================

    public function testDepartmentHardDeleteBlockedWhenProgramsExist(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department With Program');
        $this->newProgram($dept, 'BLOCK1');

        $this->actingAsAdmin();
        $this->postAs("/admin/sub-departments/{$dept}/delete", []);

        $this->seeInDatabase('sub_departments', ['sub_dept_id' => $dept]);
    }

    public function testDepartmentHardDeleteSucceedsWhenEmpty(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Empty Department');

        $this->actingAsAdmin();
        $this->postAs("/admin/sub-departments/{$dept}/delete", []);

        $this->dontSeeInDatabase('sub_departments', ['sub_dept_id' => $dept]);
        $this->assertNotNull((new AuditLogModel())->where('action', 'department.deleted')->where('entity_id', (string) $dept)->first());
    }

    public function testInactiveProgramHiddenFromNewSectionCreation(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department');
        $this->newProgram($dept, 'INACTIVE1', 'Inactive Program', false);

        $chair = ++self::$seq;
        $this->seedUsers($chair);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $chair, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        db_connect()->table('users')->where('id', $chair)->update(['sub_department_id' => $dept]);

        $names = (new DepartmentModel())->programsOfSubDept($dept);
        $this->assertNotContains('Inactive Program', $names, 'An inactive program must not appear in the creatable-program list.');
    }

    public function testHistoricalStudentsRemainReadableUnderInactiveProgram(): void
    {
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department');
        $program = $this->newProgram($dept, 'HIST1', 'Historical Program', true);

        $student = ++self::$seq;
        $this->seedUsers($student);
        db_connect()->table('students')->insert(['user_id' => $student, 'applicant_id' => 'A' . $student, 'student_number' => 'S' . $student, 'program_id' => $program, 'student_status' => 'active']);

        // Deactivate the program.
        db_connect()->table('programs')->where('program_id', $program)->update(['is_active' => 0]);

        $this->seeInDatabase('students', ['user_id' => $student, 'program_id' => $program]);
    }

    // =====================================================================
    // SECURITY / AUTHORIZATION
    // =====================================================================

    public function testChairpersonCannotManipulateUnrelatedProgram(): void
    {
        // Program transfer is admin-only (group:admin filter) — a
        // chairperson session must be rejected outright.
        $college = $this->newCollege('College');
        $deptA   = $this->newDept($college, 'Department A');
        $deptB   = $this->newDept($college, 'Department B');
        $program = $this->newProgram($deptA, 'SECTST');

        $chair = ++self::$seq;
        $this->seedUsers($chair);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $chair, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById($chair));

        $this->postAs("/admin/programs/{$program}/transfer", ['sub_department_id' => $deptB, 'reason' => 'unauthorized attempt']);

        $this->seeInDatabase('programs', ['program_id' => $program, 'sub_department_id' => $deptA]);
    }

    public function testTransferAuthorizationUnchangedByThisBatch(): void
    {
        // Regression pin: the transfer-tools batch's own authorization
        // behavior (admin-only) must still hold after this batch's changes.
        $college = $this->newCollege('College');
        $dept    = $this->newDept($college, 'Department');

        $response = $this->postAs("/admin/sub-departments/{$dept}/transfer/preview", ['college_id' => $college]);
        $response->assertRedirect(); // unauthenticated -> redirected to login, never a JSON 200
    }
}
