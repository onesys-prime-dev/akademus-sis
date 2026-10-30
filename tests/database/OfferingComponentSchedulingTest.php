<?php

use App\Libraries\SchedulingConflictService;
use App\Models\CourseOfferingModel;
use App\Models\EnrollmentModel;
use App\Models\FacultyAssignmentModel;
use App\Models\OfferingComponentModel;
use App\Models\PlottedScheduleModel;
use App\Models\SectionModel;
use App\Models\SubjectComponentTemplateModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Step 9 of the shared-subject architecture work: a subject may be split
 * into independently schedulable components (Lecture, Laboratory Group A,
 * Laboratory Group B, ...), each with its own roster, schedule, and
 * required hours — without disturbing a simple subject's plain Step 5
 * behavior at all.
 *
 * @internal
 */
final class OfferingComponentSchedulingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const TERM = '1 · 1st Semester';

    private int $nextUserId = 70000;
    private int $deptSeq    = 1;
    private int $typeSeq    = 1;

    // --- fixtures -----------------------------------------------------------

    private function newSubDept(): int
    {
        $collegeId = $this->deptSeq++;
        $subDeptId = $this->deptSeq++;
        db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => 'Unit ' . $subDeptId]);

        return $subDeptId;
    }

    private function seedChairperson(int $subDeptId): int
    {
        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['sub_department_id' => $subDeptId]);

        return $userId;
    }

    private function seedSubject(string $code, ?int $subDeptId): int
    {
        db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $code . ' Title', 'sub_department_id' => $subDeptId, 'units' => 3]);

        return (int) db_connect()->insertID();
    }

    private function componentType(string $code): int
    {
        $db = db_connect();
        $db->table('component_types')->insert(['code' => $code . $this->typeSeq++, 'name' => $code, 'is_active' => 1]);

        return (int) $db->insertID();
    }

    private function addTemplate(int $subjectId, int $typeId, array $overrides = []): int
    {
        $templates = new SubjectComponentTemplateModel();
        $templates->insert(array_merge([
            'subject_id'        => $subjectId,
            'component_type_id' => $typeId,
            'is_required'       => 1,
            'assignment_rule'   => 'shared',
        ], $overrides));

        return (int) $templates->getInsertID();
    }

    private function seedSection(string $program, string $label, string $academicYear = self::TERM): int
    {
        $sections = new SectionModel();
        $sections->insert(['academic_year' => $academicYear, 'program' => $program, 'year_level' => '2nd Year', 'section' => $label]);

        return (int) $sections->getInsertID();
    }

    private function makeOffering(string $subjectCode, array $sectionIds, ?int $capacity = null): int
    {
        $result = (new CourseOfferingModel())->createMerge(self::TERM, $subjectCode, $sectionIds, $capacity);
        $this->assertTrue($result['ok'], $result['error'] ?? '');

        return (int) $result['offering_id'];
    }

    /** Enrolls $n fresh students into $sectionId for $subjectCode, returns their user_ids. */
    private function enrollStudents(int $sectionId, string $subjectCode, int $n): array
    {
        $enrollments = new EnrollmentModel();
        $ids = [];

        for ($i = 0; $i < $n; $i++) {
            $uid = $this->nextUserId++;
            $this->seedUsers($uid);
            $enrollments->insert([
                'user_id'      => $uid,
                'section_id'   => $sectionId,
                'subject_code' => $subjectCode,
                'status'       => 'enrolled',
            ]);
            $ids[] = $uid;
        }

        return $ids;
    }

    /** Backfills offering_id onto enrollment rows the same way createMerge()/attachSection() do. */
    private function backfillOfferingId(int $offeringId, string $subjectCode, array $sectionIds): void
    {
        db_connect()->table('enrollments')
            ->where('subject_code', $subjectCode)
            ->whereIn('section_id', $sectionIds)
            ->update(['offering_id' => $offeringId]);
    }

    private function block(int $day, string $start, string $end, string $room, ?string $label = null): array
    {
        return ['day' => $day, 'start' => $start, 'end' => $end, 'time_label' => $label ?? "$start-$end", 'room' => $room, 'faculty_id' => null];
    }

    private function componentIdFor(int $offeringId, int $templateId): int
    {
        $row = db_connect()->table('offering_components')->where('offering_id', $offeringId)->where('subject_component_template_id', $templateId)->get()->getRowArray();

        return (int) $row['id'];
    }

    // --- backward compatibility ---------------------------------------------

    public function testSubjectWithNoComponentTemplatesProducesZeroOfferingComponents(): void
    {
        $subDept = $this->newSubDept();
        $this->seedSubject('SIMPLE1', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');

        $offeringId = $this->makeOffering('SIMPLE1', [$a, $b]);

        $this->assertSame(0, db_connect()->table('offering_components')->where('offering_id', $offeringId)->countAllResults());
    }

    public function testSimpleOfferingSchedulingStillWorksUnchanged(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('SIMPLE1', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('SIMPLE1', [$a, $b]);

        $result = (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '08:00', '09:30', 'Room 1')]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('plotted_schedules', ['offering_id' => $offeringId, 'offering_component_id' => null]);
    }

    // --- instantiation / cardinality ----------------------------------------

    public function testOfferingInstantiatesOneComponentPerActiveTemplate(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $lecType   = $this->componentType('Lecture');
        $labType   = $this->componentType('Laboratory');
        $this->addTemplate($subjectId, $lecType, ['required_hours' => 3]);
        $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'one_of']);

        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$a, $b]);

        $this->assertSame(2, db_connect()->table('offering_components')->where('offering_id', $offeringId)->countAllResults());
    }

    public function testTemplateCanProduceMultipleInstancesViaManualAddition(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $labType   = $this->componentType('Laboratory');
        $labTemplateId = $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'one_of']);

        $a = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$a, $this->seedSection('MUS', 'A')]);

        $components = new OfferingComponentModel();
        // Simulate "add another instance of this template" (Lab Group B).
        $components->insert([
            'offering_id'                   => $offeringId,
            'subject_component_template_id' => $labTemplateId,
            'component_type_id'             => $labType,
            'name'                          => 'Laboratory Group B',
            'is_required'                   => 1,
            'required_hours'                => 3,
            'roster_mode'                   => 'explicit',
            'is_active'                     => 1,
        ]);

        $this->assertSame(2, db_connect()->table('offering_components')->where('offering_id', $offeringId)->where('subject_component_template_id', $labTemplateId)->countAllResults());
    }

    // --- Required addition 1: irregular student legacy conflict -------------

    public function testIrregularStudentConflictIsDetectedViaTheirActualEnrollmentSection(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $labType   = $this->componentType('Laboratory');
        $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'shared']);

        $home     = $this->seedSection('BSIT', '3A');
        $other    = $this->seedSection('BSIT', '2B');
        $mus      = $this->seedSection('MUS', 'A');

        // A legacy class already plotted for BSIT-2B (NOT the student's
        // users.section_id, which stays BSIT-3A).
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => '2B',
            'day' => 0, 'start_time' => '10:00:00', 'end_time' => '11:30:00', 'time_label' => '10:00-11:30',
            'subject_code' => 'MATH201', 'subject_title' => 'Math', 'room' => 'Room 9',
        ]);

        $offeringId = $this->makeOffering('CHEM101', [$home, $mus]);

        $uid = $this->nextUserId++;
        $this->seedUsers($uid);
        db_connect()->table('users')->where('id', $uid)->update(['section_id' => $home]);

        // Irregular: enrolled in CHEM101 under section 'home', but their
        // MATH201 enrollment is under the OTHER section.
        (new EnrollmentModel())->insert(['user_id' => $uid, 'section_id' => $home, 'subject_code' => 'CHEM101', 'status' => 'enrolled']);
        (new EnrollmentModel())->insert(['user_id' => $uid, 'section_id' => $other, 'subject_code' => 'MATH201', 'status' => 'enrolled']);
        $this->backfillOfferingId($offeringId, 'CHEM101', [$home, $mus]);

        $labComponentId = $this->componentIdFor($offeringId, db_connect()->table('subject_component_templates')->where('subject_id', $subjectId)->get()->getRowArray()['id']);
        db_connect()->table('offering_components')->where('id', $labComponentId)->update(['roster_mode' => 'explicit']);
        (new OfferingComponentModel())->assignStudent($labComponentId, $uid);

        $result = (new SchedulingConflictService())->studentConflicts([$uid], self::TERM, [$this->block(0, '10:00', '11:30', 'Room 301')]);

        $this->assertFalse($result['ok'], 'The conflict must be detected via the enrollment\'s actual section_id, not users.section_id.');
    }

    // --- component scheduling: inherited vs explicit ------------------------

    public function testInheritedComponentCanBeScheduledLikeASimpleOffering(): void
    {
        $subDept   = $this->newSubDept();
        $chair     = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $lecType   = $this->componentType('Lecture');
        $this->addTemplate($subjectId, $lecType, ['required_hours' => 3]);

        $a = $this->seedSection('BSIT', 'A');
        $b = $this->seedSection('MUS', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$a, $b]);

        $tplId = db_connect()->table('subject_component_templates')->where('subject_id', $subjectId)->get()->getRowArray()['id'];
        $componentId = $this->componentIdFor($offeringId, (int) $tplId);

        $result = (new PlottedScheduleModel())->syncComponentSchedule($componentId, $chair, [$this->block(0, '08:00', '09:30', 'Room 1')]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('plotted_schedules', ['offering_component_id' => $componentId, 'offering_id' => $offeringId]);
    }

    public function testExplicitComponentRosterAssignmentRequiresParentOfferingMembership(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $labType   = $this->componentType('Laboratory');
        $tplId     = $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'one_of']);

        $a = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$a, $this->seedSection('MUS', 'A')]);
        $componentId = $this->componentIdFor($offeringId, $tplId);
        db_connect()->table('offering_components')->where('id', $componentId)->update(['roster_mode' => 'explicit']);

        $outsider = $this->nextUserId++;
        $this->seedUsers($outsider);

        $result = (new OfferingComponentModel())->assignStudent($componentId, $outsider);

        $this->assertFalse($result['ok']);
    }

    // --- disjoint lab groups: the core Step 9 scenario ----------------------

    private function makeChemOfferingWithTwoLabGroups(int $studentsPerGroup = 15): array
    {
        $subDept   = $this->newSubDept();
        $chair     = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $labType   = $this->componentType('Laboratory');
        $tplId     = $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'one_of']);

        $bsit = $this->seedSection('BSIT', '2A');
        $offeringId = $this->makeOffering('CHEM101', [$bsit, $this->seedSection('MUS', 'A')]);

        $groupAIds = $this->enrollStudents($bsit, 'CHEM101', $studentsPerGroup);
        $groupBIds = $this->enrollStudents($bsit, 'CHEM101', $studentsPerGroup);
        $this->backfillOfferingId($offeringId, 'CHEM101', [$bsit]);

        $components = new OfferingComponentModel();
        $groupAComponentId = $this->componentIdFor($offeringId, $tplId);
        db_connect()->table('offering_components')->where('id', $groupAComponentId)->update(['roster_mode' => 'explicit', 'name' => 'Laboratory Group A']);

        $groupBComponentId = $components->insert([
            'offering_id' => $offeringId, 'subject_component_template_id' => $tplId, 'component_type_id' => $labType,
            'name' => 'Laboratory Group B', 'is_required' => 1, 'required_hours' => 3, 'roster_mode' => 'explicit', 'is_active' => 1,
        ]) ? $components->getInsertID() : 0;

        foreach ($groupAIds as $uid) {
            $this->assertTrue($components->assignStudent($groupAComponentId, $uid)['ok']);
        }
        foreach ($groupBIds as $uid) {
            $this->assertTrue($components->assignStudent((int) $groupBComponentId, $uid)['ok']);
        }

        return compact('chair', 'offeringId', 'groupAComponentId', 'groupBComponentId', 'groupAIds', 'groupBIds');
    }

    public function testDisjointLabGroupsFromTheSameHomeSectionMayShareTheSameTimeSlot(): void
    {
        $f = $this->makeChemOfferingWithTwoLabGroups();

        $resultA = (new PlottedScheduleModel())->syncComponentSchedule($f['groupAComponentId'], $f['chair'], [$this->block(1, '13:00', '16:00', 'Lab Room 1')]);
        $this->assertTrue($resultA['ok'], $resultA['error'] ?? '');

        $resultB = (new PlottedScheduleModel())->syncComponentSchedule($f['groupBComponentId'], $f['chair'], [$this->block(1, '13:00', '16:00', 'Lab Room 2')]);
        $this->assertTrue($resultB['ok'], $resultB['error'] ?? '', 'Two disjoint groups with zero student overlap must be allowed to share a time slot.');
    }

    public function testStudentInBothGroupsOfAOneOfTemplateIsRejectedAtAssignmentTime(): void
    {
        $f = $this->makeChemOfferingWithTwoLabGroups(1);

        $result = (new OfferingComponentModel())->assignStudent($f['groupBComponentId'], $f['groupAIds'][0]);

        $this->assertFalse($result['ok'], 'A student already in Group A (one_of template) must be rejected from Group B.');
    }

    public function testExternalConflictForOneStudentOnlyBlocksTheirOwnGroup(): void
    {
        $f = $this->makeChemOfferingWithTwoLabGroups(2);

        (new PlottedScheduleModel())->syncComponentSchedule($f['groupAComponentId'], $f['chair'], [$this->block(1, '13:00', '16:00', 'Lab Room 1')]);

        // One Group-A student has a real external conflict at the same time.
        $conflictedStudent = $f['groupAIds'][0];
        db_connect()->table('enrollments')->insert(['user_id' => $conflictedStudent, 'section_id' => $this->seedSection('NUR', 'A'), 'subject_code' => 'NUR101', 'status' => 'enrolled']);
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'NUR', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 2, 'start_time' => '09:00:00', 'end_time' => '10:30:00', 'time_label' => '09:00-10:30',
            'subject_code' => 'NUR101', 'subject_title' => 'Nursing', 'room' => 'Room 2',
        ]);

        // Group A re-scheduled onto Wednesday, overlapping that external class.
        $resultA = (new PlottedScheduleModel())->syncComponentSchedule($f['groupAComponentId'], $f['chair'], [$this->block(2, '09:00', '10:30', 'Lab Room 1')]);
        $this->assertFalse($resultA['ok']);

        // Group B, same slot, has NO affected students — must still succeed.
        $resultB = (new PlottedScheduleModel())->syncComponentSchedule($f['groupBComponentId'], $f['chair'], [$this->block(2, '09:00', '10:30', 'Lab Room 2')]);
        $this->assertTrue($resultB['ok'], $resultB['error'] ?? '', 'Student 10\'s external conflict must block only their own group.');
    }

    // --- coverage / completeness semantics ----------------------------------

    public function testRequiredSharedTemplateUncoveredStudentIsReported(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $labType   = $this->componentType('Laboratory');
        $tplId     = $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'shared']);

        $bsit = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$bsit, $this->seedSection('MUS', 'A')]);
        $students = $this->enrollStudents($bsit, 'CHEM101', 2);
        $this->backfillOfferingId($offeringId, 'CHEM101', [$bsit]);

        $componentId = $this->componentIdFor($offeringId, $tplId);
        db_connect()->table('offering_components')->where('id', $componentId)->update(['roster_mode' => 'explicit']);
        (new OfferingComponentModel())->assignStudent($componentId, $students[0]);
        // $students[1] intentionally left unassigned.

        $completeness = (new OfferingComponentModel())->completeness($offeringId);

        $this->assertSame(1, $completeness['templates'][0]['uncovered_count']);
        $this->assertSame(0, $completeness['templates'][0]['duplicate_assignment_count']);
    }

    public function testRequiredSharedTemplateAllowsMultipleMembershipWithoutInvalidity(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $labType   = $this->componentType('Laboratory');
        $tplId     = $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'shared']);

        $bsit = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$bsit, $this->seedSection('MUS', 'A')]);
        $students = $this->enrollStudents($bsit, 'CHEM101', 1);
        $this->backfillOfferingId($offeringId, 'CHEM101', [$bsit]);

        $componentAId = $this->componentIdFor($offeringId, $tplId);
        db_connect()->table('offering_components')->where('id', $componentAId)->update(['roster_mode' => 'explicit']);

        $components = new OfferingComponentModel();
        $componentBId = $components->insert([
            'offering_id' => $offeringId, 'subject_component_template_id' => $tplId, 'component_type_id' => $labType,
            'name' => 'Extra session', 'is_required' => 1, 'required_hours' => 3, 'roster_mode' => 'explicit', 'is_active' => 1,
        ]) ? $components->getInsertID() : 0;

        $this->assertTrue($components->assignStudent($componentAId, $students[0])['ok']);
        $this->assertTrue($components->assignStudent((int) $componentBId, $students[0])['ok'], 'Shared assignment_rule must allow multiple membership.');

        $completeness = $components->completeness($offeringId);
        $this->assertSame(0, $completeness['templates'][0]['uncovered_count']);
        $this->assertTrue($completeness['templates'][0]['roster_valid']);
    }

    public function testRequiredOneOfTemplateWithForcedDuplicateIsInvalid(): void
    {
        $f = $this->makeChemOfferingWithTwoLabGroups(1);

        // Bypass the assignment-time guard directly at the DB level to
        // simulate stale/legacy data with a real duplicate.
        db_connect()->table('offering_component_students')->insert([
            'offering_component_id' => $f['groupBComponentId'],
            'user_id'                => $f['groupAIds'][0],
            'created_at'             => date('Y-m-d H:i:s'),
        ]);

        $completeness = (new OfferingComponentModel())->completeness($f['offeringId']);

        $this->assertSame(1, $completeness['templates'][0]['duplicate_assignment_count']);
        $this->assertFalse($completeness['templates'][0]['roster_valid']);
    }

    public function testRequiredTemplateWithActiveInheritedInstanceAutoCoversEveryone(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $lecType   = $this->componentType('Lecture');
        $tplId     = $this->addTemplate($subjectId, $lecType, ['required_hours' => 3]);

        $bsit = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$bsit, $this->seedSection('MUS', 'A')]);
        $this->enrollStudents($bsit, 'CHEM101', 5);
        $this->backfillOfferingId($offeringId, 'CHEM101', [$bsit]);
        // roster_mode stays 'inherited' (the default) — no explicit rows needed.

        $completeness = (new OfferingComponentModel())->completeness($offeringId);

        $this->assertSame(0, $completeness['templates'][0]['uncovered_count']);
        $this->assertTrue($completeness['templates'][0]['roster_valid']);
    }

    public function testOptionalTemplateWithNoAssignmentsDoesNotBlockCompleteness(): void
    {
        $subDept   = $this->newSubDept();
        $chair     = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $lecType   = $this->componentType('Lecture');
        $tutType   = $this->componentType('Tutorial');
        $this->addTemplate($subjectId, $lecType, ['required_hours' => 3]);
        $this->addTemplate($subjectId, $tutType, ['required_hours' => 1, 'is_required' => 0]);

        $a = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$a, $this->seedSection('MUS', 'A')]);

        $lecTplId = db_connect()->table('subject_component_templates')->where('component_type_id', $lecType)->get()->getRowArray()['id'];
        $lecComponentId = $this->componentIdFor($offeringId, (int) $lecTplId);
        (new PlottedScheduleModel())->syncComponentSchedule($lecComponentId, $chair, [$this->block(0, '08:00', '11:00', 'Room 1')]);

        $completeness = (new OfferingComponentModel())->completeness($offeringId);

        $this->assertTrue($completeness['complete'], 'An unscheduled OPTIONAL template must not block parent offering completeness.');
    }

    // --- lifecycle: deactivation ---------------------------------------------

    public function testDeactivatedComponentIsExcludedFromCurrentCoverage(): void
    {
        $f = $this->makeChemOfferingWithTwoLabGroups(2);

        (new PlottedScheduleModel())->syncComponentSchedule($f['groupAComponentId'], $f['chair'], [$this->block(1, '13:00', '16:00', 'Lab Room 1')]);
        (new PlottedScheduleModel())->syncComponentSchedule($f['groupBComponentId'], $f['chair'], [$this->block(1, '13:00', '16:00', 'Lab Room 2')]);

        $completeBefore = (new OfferingComponentModel())->completeness($f['offeringId']);
        $this->assertTrue($completeBefore['templates'][0]['roster_valid']);

        (new OfferingComponentModel())->deactivate($f['groupBComponentId'], $f['chair']);

        $completeAfter = (new OfferingComponentModel())->completeness($f['offeringId']);
        $this->assertSame(2, $completeAfter['templates'][0]['uncovered_count'], 'Group B\'s former students must become uncovered once it is deactivated.');
    }

    public function testDeactivatedComponentRejectsNewRosterAssignment(): void
    {
        $f = $this->makeChemOfferingWithTwoLabGroups(1);
        (new OfferingComponentModel())->deactivate($f['groupBComponentId'], $f['chair']);

        $newStudent = $this->enrollStudents(db_connect()->table('offering_sections')->where('offering_id', $f['offeringId'])->get()->getRowArray()['section_id'], 'CHEM101', 1)[0];
        $this->backfillOfferingId($f['offeringId'], 'CHEM101', [db_connect()->table('offering_sections')->where('offering_id', $f['offeringId'])->get()->getRowArray()['section_id']]);

        $result = (new OfferingComponentModel())->assignStudent($f['groupBComponentId'], $newStudent);

        $this->assertFalse($result['ok']);
    }

    public function testHistoricalMembershipRowsSurviveDeactivation(): void
    {
        $f = $this->makeChemOfferingWithTwoLabGroups(1);

        (new OfferingComponentModel())->deactivate($f['groupBComponentId'], $f['chair']);

        $this->seeInDatabase('offering_component_students', ['offering_component_id' => $f['groupBComponentId'], 'user_id' => $f['groupBIds'][0]]);
    }

    // --- dashboard integration -----------------------------------------------

    public function testDashboardReportsIncompleteWhenLectureCompleteButLabUnscheduled(): void
    {
        $subDept   = $this->newSubDept();
        $chair     = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $lecType   = $this->componentType('Lecture');
        $labType   = $this->componentType('Laboratory');
        $this->addTemplate($subjectId, $lecType, ['required_hours' => 3]);
        $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'one_of']);

        $a = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$a, $this->seedSection('MUS', 'A')]);

        $lecTplId = db_connect()->table('subject_component_templates')->where('component_type_id', $lecType)->get()->getRowArray()['id'];
        $lecComponentId = $this->componentIdFor($offeringId, (int) $lecTplId);
        (new PlottedScheduleModel())->syncComponentSchedule($lecComponentId, $chair, [$this->block(0, '08:00', '11:00', 'Room 1')]);
        // Laboratory is left entirely unscheduled.

        $dashboard = (new \App\Libraries\SharedSubjectDashboardService())->build($chair, self::TERM);

        $offeringEntry = $dashboard['subjects'][0]['offerings'][0];
        $this->assertFalse($offeringEntry['is_scheduled'], 'Lecture complete + Lab unscheduled must read as incomplete, not "scheduled".');
    }

    public function testDashboardReportsIncompleteWhenAllMeetingsScheduledButARequiredStudentIsUncovered(): void
    {
        $subDept   = $this->newSubDept();
        $chair     = $this->seedChairperson($subDept);
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $labType   = $this->componentType('Laboratory');
        $tplId     = $this->addTemplate($subjectId, $labType, ['required_hours' => 3, 'assignment_rule' => 'shared']);

        $bsit = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$bsit, $this->seedSection('MUS', 'A')]);
        $students = $this->enrollStudents($bsit, 'CHEM101', 2);
        $this->backfillOfferingId($offeringId, 'CHEM101', [$bsit]);

        $componentId = $this->componentIdFor($offeringId, $tplId);
        db_connect()->table('offering_components')->where('id', $componentId)->update(['roster_mode' => 'explicit']);
        (new OfferingComponentModel())->assignStudent($componentId, $students[0]);
        // $students[1] left uncovered.

        (new PlottedScheduleModel())->syncComponentSchedule($componentId, $chair, [$this->block(0, '08:00', '11:00', 'Room 1')]);

        $dashboard = (new \App\Libraries\SharedSubjectDashboardService())->build($chair, self::TERM);
        $offeringEntry = $dashboard['subjects'][0]['offerings'][0];

        $this->assertFalse($offeringEntry['is_scheduled'], 'A fully-scheduled but roster-incomplete required component must still read as incomplete.');
    }

    public function testDashboardBehaviorForSimpleOfferingsIsUnchanged(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedSubject('SIMPLE1', $subDept);
        $a = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('SIMPLE1', [$a, $this->seedSection('MUS', 'A')]);

        (new PlottedScheduleModel())->syncOfferingSchedule($offeringId, $chair, [$this->block(0, '08:00', '09:30', 'Room 1')]);

        $dashboard = (new \App\Libraries\SharedSubjectDashboardService())->build($chair, self::TERM);
        $offeringEntry = $dashboard['subjects'][0]['offerings'][0];

        $this->assertTrue($offeringEntry['is_scheduled']);
    }

    // --- contact hours: per-instance, never summed ---------------------------

    public function testPerInstanceContactHoursAreNeverSummedAcrossSiblingInstances(): void
    {
        $f = $this->makeChemOfferingWithTwoLabGroups(1);

        (new PlottedScheduleModel())->syncComponentSchedule($f['groupAComponentId'], $f['chair'], [$this->block(1, '13:00', '16:00', 'Lab Room 1')]);
        // Group B left unscheduled (0h).

        $completeness = (new OfferingComponentModel())->completeness($f['offeringId']);

        $this->assertFalse($completeness['templates'][0]['schedule_complete'], 'Group A\'s 3h must not retroactively cover Group B\'s own 3h requirement.');
    }

    // --- faculty load ----------------------------------------------------------

    public function testFacultySameOfferingLectureAndLabCountsParentSubjectOnce(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $lecType   = $this->componentType('Lecture');
        $labType   = $this->componentType('Laboratory');
        $this->addTemplate($subjectId, $lecType);
        $this->addTemplate($subjectId, $labType);

        $a = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$a, $this->seedSection('MUS', 'A')]);

        $faculty = $this->nextUserId++;
        $this->seedUsers($faculty);

        $components = db_connect()->table('offering_components')->where('offering_id', $offeringId)->get()->getResultArray();

        $assignments = new FacultyAssignmentModel();
        foreach ($components as $c) {
            $assignments->upsertForOffering($offeringId, (int) $c['id'], self::TERM, 'CHEM101', $faculty);
        }

        $this->assertEquals(3.0, $assignments->currentOfferingLoadUnits($faculty), '', 0.001);
    }

    public function testFacultyTwoSeparateOfferingsEachCountInFull(): void
    {
        $subDept = $this->newSubDept();
        $this->seedSubject('CHEM101', $subDept);
        $this->seedSubject('CHEM102', $subDept);

        $offeringA = $this->makeOffering('CHEM101', [$this->seedSection('BSIT', 'A'), $this->seedSection('MUS', 'A')]);
        $offeringB = $this->makeOffering('CHEM102', [$this->seedSection('BSIT', 'B'), $this->seedSection('MUS', 'B')]);

        $faculty = $this->nextUserId++;
        $this->seedUsers($faculty);

        $assignments = new FacultyAssignmentModel();
        $assignments->upsertForOffering($offeringA, null, self::TERM, 'CHEM101', $faculty);
        $assignments->upsertForOffering($offeringB, null, self::TERM, 'CHEM102', $faculty);

        $this->assertEquals(6.0, $assignments->currentOfferingLoadUnits($faculty), '', 0.001);
    }

    // --- demand / substitutions unchanged -------------------------------------

    public function testParentDemandIsNotMultipliedByComponentCount(): void
    {
        $subDept   = $this->newSubDept();
        $subjectId = $this->seedSubject('CHEM101', $subDept);
        $lecType   = $this->componentType('Lecture');
        $labType   = $this->componentType('Laboratory');
        $this->addTemplate($subjectId, $lecType);
        $this->addTemplate($subjectId, $labType);

        $bsit = $this->seedSection('BSIT', 'A');
        $offeringId = $this->makeOffering('CHEM101', [$bsit, $this->seedSection('MUS', 'A')]);
        $this->enrollStudents($bsit, 'CHEM101', 3);
        $this->backfillOfferingId($offeringId, 'CHEM101', [$bsit]);

        $demand = (new \App\Libraries\SubjectDemandService())->summary($subjectId, self::TERM);

        $this->assertSame(3, $demand['assigned_count'], 'Demand must stay subject-level — one student needing CHEM101 is one unit of demand regardless of how many components it has.');
    }
}
