<?php

use App\Models\FacultyAssignmentModel;
use App\Models\FacultyDepartmentModel;
use App\Models\AuditLogModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Faculty <-> department many-to-many affiliation (see
 * CreateFacultyDepartments migration). A faculty member must support
 * multiple department affiliations with exactly one primary, and teaching
 * eligibility must be affiliation-based (primary OR secondary) rather than
 * "must belong to the chairperson's own department" — the restriction this
 * batch removed from ChairpersonController::sync().
 *
 * @internal
 */
final class FacultyDepartmentModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private static int $seq = 80000;

    private function newSubDept(string $name): int
    {
        $collegeId = ++self::$seq;
        $subDeptId = ++self::$seq;
        db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => $name]);

        return $subDeptId;
    }

    private function newFaculty(): int
    {
        $id = ++self::$seq;
        $this->seedUsers($id);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'faculty', 'created_at' => date('Y-m-d H:i:s')]);

        return $id;
    }

    // --- 1/2: multiple affiliations, first one becomes primary -----------

    public function testFirstAffiliationBecomesPrimaryAutomatically(): void
    {
        $it  = $this->newSubDept('Information Technology');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $result = $model->addAffiliation($theo, $it, false, 1, 'admin');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $primary = $model->primaryDepartmentForFaculty($theo);
        $this->assertNotNull($primary);
        $this->assertSame($it, $primary['sub_dept_id']);
        $this->seeInDatabase('users', ['id' => $theo, 'sub_department_id' => $it]);
    }

    public function testFacultyCanHaveMultipleDepartments(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $second = $model->addAffiliation($theo, $cs, false, 1, 'admin');

        $this->assertTrue($second['ok'], $second['error'] ?? '');
        $all = $model->departmentsForFaculty($theo);
        $this->assertCount(2, $all);
        $this->assertTrue($model->isAffiliatedWith($theo, $it));
        $this->assertTrue($model->isAffiliatedWith($theo, $cs));
    }

    // --- 3: duplicate rejected --------------------------------------------

    public function testDuplicateAffiliationRejected(): void
    {
        $it = $this->newSubDept('Information Technology');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $dup = $model->addAffiliation($theo, $it, false, 1, 'admin');

        $this->assertFalse($dup['ok']);
    }

    // --- 4: cannot have two primaries --------------------------------------

    public function testOneFacultyCannotHaveTwoActivePrimaries(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $model->setPrimaryDepartment($theo, $cs, 1, 'admin');

        $primaries = db_connect()->table('faculty_departments')
            ->where('user_id', $theo)->where('is_primary', 1)->countAllResults();

        $this->assertSame(1, $primaries);
        $this->assertSame($cs, $model->primaryDepartmentForFaculty($theo)['sub_dept_id']);
    }

    // --- 5: changing primary updates users.sub_department_id --------------

    public function testChangingPrimaryUpdatesLegacyUsersColumn(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $this->seeInDatabase('users', ['id' => $theo, 'sub_department_id' => $it]);

        $result = $model->setPrimaryDepartment($theo, $cs, 1, 'admin', 'Promotion');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('users', ['id' => $theo, 'sub_department_id' => $cs]);
        $this->assertNotNull(
            (new AuditLogModel())->where('action', 'faculty_department.primary_changed')->first(),
            'Primary changes must be audited.'
        );
    }

    // --- 6: removing a secondary works -------------------------------------

    public function testRemovingSecondaryAffiliationWorks(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $model->addAffiliation($theo, $cs, false, 1, 'admin');

        $result = $model->removeAffiliation($theo, $cs, 1, 'admin');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertFalse($model->isAffiliatedWith($theo, $cs));
        $this->assertTrue($model->isAffiliatedWith($theo, $it), 'Primary must be untouched.');
    }

    // --- 7: removing primary without replacement is blocked ----------------

    public function testRemovingPrimaryWithoutReplacementIsBlocked(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $model->addAffiliation($theo, $cs, false, 1, 'admin');

        $result = $model->removeAffiliation($theo, $it, 1, 'admin');

        $this->assertFalse($result['ok']);
        $this->assertTrue($model->isAffiliatedWith($theo, $it), 'Primary must remain after a blocked removal.');
    }

    // --- 8/9/10: department-scoped faculty visibility -----------------------

    public function testUserIdsForDepartmentIncludesBothPrimaryAndSecondary(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        $profA = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $model->addAffiliation($theo, $cs, false, 1, 'admin');
        $model->addAffiliation($profA, $cs, true, 1, 'admin');

        $csFaculty = $model->userIdsForDepartment($cs);

        $this->assertContains($theo, $csFaculty, 'Secondary affiliation must make Theo visible to the CS department.');
        $this->assertContains($profA, $csFaculty);
    }

    public function testUnrelatedDepartmentDoesNotSeeUnaffiliatedFaculty(): void
    {
        $it = $this->newSubDept('Information Technology');
        $unrelated = $this->newSubDept('Unrelated Department');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');

        $this->assertNotContains($theo, $model->userIdsForDepartment($unrelated));
    }

    // --- 11: the exact scenario this batch targets ---------------------------
    // CS Chairperson manages CS301 (owned by CS). Theo's primary is IT, but
    // he's affiliated with CS. The eligibility check ChairpersonController::
    // sync() now runs is exactly isAffiliatedWith($facultyId, $subjectOwnerDeptId)
    // — this proves that primitive allows it, and rejects an unaffiliated one.

    public function testCrossDepartmentFacultyIsEligibleForTheOwningDepartmentsSubject(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        $strangerFaculty = $this->newFaculty();
        $model = new FacultyDepartmentModel();

        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $model->addAffiliation($theo, $cs, false, 1, 'admin');

        // Theo is eligible for a CS301-like subject owned by CS...
        $this->assertTrue($model->isAffiliatedWith($theo, $cs));
        // ...while a faculty member with NO affiliation to CS is not.
        $this->assertFalse($model->isAffiliatedWith($strangerFaculty, $cs));
        // Theo's IT affiliation is untouched by being used for CS teaching.
        $this->assertTrue($model->isAffiliatedWith($theo, $it));
        $this->assertSame($it, $model->primaryDepartmentForFaculty($theo)['sub_dept_id'], 'Theo must remain primarily IT.');
    }

    // --- 12/13: teaching load stays global across departments ---------------

    public function testTeachingLoadIsGlobalAcrossAffiliatedDepartments(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        (new FacultyDepartmentModel())->addAffiliation($theo, $it, true, 1, 'admin');
        (new FacultyDepartmentModel())->addAffiliation($theo, $cs, false, 1, 'admin');

        db_connect()->table('subjects')->insert(['subject_code' => 'IT201', 'subject_name' => 'IT201', 'units' => 3, 'sub_department_id' => $it]);
        db_connect()->table('subjects')->insert(['subject_code' => 'CS301', 'subject_name' => 'CS301', 'units' => 3, 'sub_department_id' => $cs]);

        $term = 'AY 1 · 1 Semester';

        $assignments = new FacultyAssignmentModel();
        $assignments->insert([
            'user_id' => $theo, 'academic_year' => $term, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'subject_code' => 'IT201', 'method' => 'assigned', 'status' => 'assigned',
        ]);
        $assignments->insert([
            'user_id' => $theo, 'academic_year' => $term, 'program' => 'BSCS', 'year_level' => '2nd Year', 'section' => 'A',
            'subject_code' => 'CS301', 'method' => 'assigned', 'status' => 'assigned',
        ]);

        // Global total — both departments' assignments count toward the
        // SAME faculty member's load, with no per-department exclusion
        // other than the context explicitly passed in.
        $total = $assignments->totalLoadUnits($theo, $term);

        $this->assertSame(6.0, $total, 'Load across IT201 + CS301 must sum globally, not per department.');
    }

    // --- Audit logging ------------------------------------------------------

    public function testAddingAnAffiliationIsAudited(): void
    {
        $it = $this->newSubDept('Information Technology');
        $theo = $this->newFaculty();

        (new FacultyDepartmentModel())->addAffiliation($theo, $it, true, 1, 'admin');

        $this->assertNotNull((new AuditLogModel())->where('action', 'faculty_department.added')->first());
    }

    public function testRemovingAnAffiliationIsAudited(): void
    {
        $it = $this->newSubDept('Information Technology');
        $cs = $this->newSubDept('Computer Science');
        $theo = $this->newFaculty();
        $model = new FacultyDepartmentModel();
        $model->addAffiliation($theo, $it, true, 1, 'admin');
        $model->addAffiliation($theo, $cs, false, 1, 'admin');

        $model->removeAffiliation($theo, $cs, 1, 'admin');

        $this->assertNotNull((new AuditLogModel())->where('action', 'faculty_department.removed')->first());
    }
}
