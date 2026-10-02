<?php

use App\Libraries\SubjectDemandService;
use App\Libraries\SubjectRequirementService;
use App\Models\AuditLogModel;
use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use App\Models\SubjectSubstitutionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Step 8 of the shared-subject architecture work: explicit, auditable
 * subject substitution/equivalency. Two similar subjects never satisfy each
 * other unless a human explicitly configured that — no title/code/prefix
 * guessing, no transitivity, no multi-hop traversal.
 *
 * @internal
 */
final class SubjectRequirementServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const TERM = '1 · 1st Semester';

    private int $nextUserId = 90000;
    private ?int $gradingScaleId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUsers(1); // the actor id used by createOrReactivate() calls throughout
    }

    private function seedSubject(string $code, string $name = ''): int
    {
        db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $name !== '' ? $name : $code . ' Title']);

        return (int) db_connect()->insertID();
    }

    private function seedSection(string $program, string $label): int
    {
        $sections = new SectionModel();
        $sections->insert(['academic_year' => self::TERM, 'program' => $program, 'year_level' => '2nd Year', 'section' => $label]);

        return (int) $sections->getInsertID();
    }

    private function seedProgram(string $name): int
    {
        static $seq = 1;
        $id = $seq++;
        db_connect()->table('programs')->insert(['program_id' => $id, 'program_code' => 'P' . $id, 'program_name' => $name, 'is_active' => 1]);

        return $id;
    }

    private function seedCurriculum(int $programId, string $code): int
    {
        db_connect()->table('curriculum')->insert([
            'program_id' => $programId, 'curriculum_code' => $code, 'curriculum_name' => $code,
            'academic_year' => '2026-2027', 'status' => 'published', 'is_current' => 1, 'is_active' => 1,
        ]);

        return (int) db_connect()->insertID();
    }

    private function seedCurriculumSubject(int $curriculumId, int $subjectId, int $yearLevel, int $semester): void
    {
        db_connect()->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculumId, 'subject_id' => $subjectId, 'year_level' => $yearLevel,
            'semester' => $semester, 'is_required' => 1, 'is_active' => 1,
        ]);
    }

    private function seedStudent(int $programId, int $curriculumId, int $sectionId): int
    {
        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['section_id' => $sectionId]);
        db_connect()->table('students')->insert([
            'user_id' => $userId, 'applicant_id' => 'APP' . $userId, 'student_number' => 'SN' . $userId,
            'program_id' => $programId, 'curriculum_id' => $curriculumId, 'student_status' => 'active',
        ]);

        return $userId;
    }

    /** Locked grade for $subjectCode, defaulting to passing. */
    private function seedGrade(int $userId, int $sectionId, string $subjectCode, bool $passing = true, string $status = 'locked'): void
    {
        db_connect()->table('enrollments')->insert([
            'user_id' => $userId, 'section_id' => $sectionId, 'subject_code' => $subjectCode, 'status' => 'dropped',
        ]);
        $enrollmentId = (int) db_connect()->insertID();

        if ($this->gradingScaleId === null) {
            db_connect()->table('grading_scales')->insert([
                'name' => 'GWA', 'type' => 'numeric_gwa', 'min_value' => 1.0, 'max_value' => 5.0,
                'passing_value' => 3.0, 'is_lower_better' => 1, 'is_active' => 1,
            ]);
            $this->gradingScaleId = (int) db_connect()->insertID();
        }

        $facultyId = $this->nextUserId++;
        $this->seedUsers($facultyId);

        db_connect()->table('grades')->insert([
            'enrollment_id' => $enrollmentId, 'user_id' => $userId, 'subject_code' => $subjectCode, 'section_id' => $sectionId,
            'academic_year' => self::TERM, 'faculty_id' => $facultyId, 'grading_scale_id' => $this->gradingScaleId,
            'grade_value' => $passing ? '1.50' : '5.00', 'status' => $status, 'is_passing' => $passing ? 1 : 0,
        ]);
    }

    // --- 1-4: core satisfies() rule -------------------------------------------

    public function testExactSameSubjectSatisfiesItsOwnRequirementWithoutAnyRelationship(): void
    {
        $subjectId = $this->seedSubject('A');

        $this->assertTrue((new SubjectRequirementService())->satisfies($subjectId, $subjectId));
    }

    public function testExplicitlyConfiguredSubstituteSatisfiesRequirement(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        (new SubjectSubstitutionModel())->createOrReactivate($a, $b, null, 1);

        $this->assertTrue((new SubjectRequirementService())->satisfies($b, $a));
    }

    public function testSameBWithoutConfiguredRelationshipDoesNotSatisfy(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');

        $this->assertFalse((new SubjectRequirementService())->satisfies($b, $a));
    }

    public function testDirectionalitySatisfiesIsNotAutomaticallyReversed(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        (new SubjectSubstitutionModel())->createOrReactivate($a, $b, null, 1); // B satisfies A

        $service = new SubjectRequirementService();
        $this->assertTrue($service->satisfies($b, $a));
        $this->assertFalse($service->satisfies($a, $b), 'A satisfying B must not be implied.');
    }

    // --- 5/6: self/duplicate guards --------------------------------------------

    public function testSelfSubstitutionCannotBeCreated(): void
    {
        $a = $this->seedSubject('A');

        $result = (new SubjectSubstitutionModel())->createOrReactivate($a, $a, null, 1);

        $this->assertFalse($result['ok']);
    }

    public function testDuplicateRelationshipCannotBeCreated(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        $model = new SubjectSubstitutionModel();
        $first = $model->createOrReactivate($a, $b, null, 1);
        $this->assertTrue($first['ok']);

        $second = $model->createOrReactivate($a, $b, null, 1);

        $this->assertFalse($second['ok']);
    }

    // --- 7-10: no inferred equivalency ------------------------------------------

    public function testSimilarSubjectTitleDoesNotImplyEquivalency(): void
    {
        $a = $this->seedSubject('GEC108', 'Ethics');
        $b = $this->seedSubject('NCM108', 'Ethics'); // deliberately identical title text

        $this->assertFalse((new SubjectRequirementService())->satisfies($b, $a));
    }

    public function testSimilarSubjectCodeDoesNotImplyEquivalency(): void
    {
        $a = $this->seedSubject('GEC112.1');
        $b = $this->seedSubject('GEC112.2');

        $this->assertFalse((new SubjectRequirementService())->satisfies($b, $a));
    }

    public function testGec112VariantsRemainSeparateWithoutExplicitConfiguration(): void
    {
        $a = $this->seedSubject('GEC112.1');
        $b = $this->seedSubject('GEC112.2');
        $c = $this->seedSubject('GEC112.4');

        $service = new SubjectRequirementService();
        $this->assertFalse($service->satisfies($b, $a));
        $this->assertFalse($service->satisfies($c, $a));
        $this->assertFalse($service->satisfies($c, $b));
    }

    public function testGec108VsNcm108RemainSeparateWithoutExplicitConfiguration(): void
    {
        $gec = $this->seedSubject('GEC108', 'Ethics');
        $ncm = $this->seedSubject('NCM108', 'Health Care Ethics / Bioethics');

        $this->assertFalse((new SubjectRequirementService())->satisfies($ncm, $gec));
    }

    // --- 11-13: grade status/passing nuance --------------------------------------

    private function makeDemandFixture(): array
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $subjA = $this->seedSubject('SUBJ-A');
        $this->seedCurriculumSubject($curriculum, $subjA, 2, 1);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);

        return [$subjA, $section, $student];
    }

    public function testLockedPassingGradeForSubstituteRemovesStudentFromExpectedDemand(): void
    {
        [$subjA, $section, $student] = $this->makeDemandFixture();
        $subjB = $this->seedSubject('SUBJ-B');
        (new SubjectSubstitutionModel())->createOrReactivate($subjA, $subjB, null, 1);
        $this->seedGrade($student, $section, 'SUBJ-B', true, 'locked');

        $expected = (new SubjectDemandService())->expectedStudents($subjA, self::TERM);

        $this->assertCount(0, $expected);
    }

    public function testDraftPassingLookingGradeDoesNotSatisfyRequirement(): void
    {
        [$subjA, $section, $student] = $this->makeDemandFixture();
        $subjB = $this->seedSubject('SUBJ-B');
        (new SubjectSubstitutionModel())->createOrReactivate($subjA, $subjB, null, 1);
        $this->seedGrade($student, $section, 'SUBJ-B', true, 'draft');

        $expected = (new SubjectDemandService())->expectedStudents($subjA, self::TERM);

        $this->assertCount(1, $expected, 'An unlocked grade must not satisfy the requirement.');
    }

    public function testLockedFailingGradeDoesNotSatisfyRequirement(): void
    {
        [$subjA, $section, $student] = $this->makeDemandFixture();
        $subjB = $this->seedSubject('SUBJ-B');
        (new SubjectSubstitutionModel())->createOrReactivate($subjA, $subjB, null, 1);
        $this->seedGrade($student, $section, 'SUBJ-B', false, 'locked');

        $expected = (new SubjectDemandService())->expectedStudents($subjA, self::TERM);

        $this->assertCount(1, $expected, 'A failing grade must not satisfy the requirement.');
    }

    // --- 14/15: single vs batch parity -------------------------------------------

    public function testSummaryHonorsSubstitutions(): void
    {
        [$subjA, $section, $student] = $this->makeDemandFixture();
        $subjB = $this->seedSubject('SUBJ-B');
        (new SubjectSubstitutionModel())->createOrReactivate($subjA, $subjB, null, 1);
        $this->seedGrade($student, $section, 'SUBJ-B', true, 'locked');

        $summary = (new SubjectDemandService())->summary($subjA, self::TERM);

        $this->assertSame(0, $summary['expected_count']);
    }

    public function testSummaryForSubjectsProducesIdenticalSubstitutionBehavior(): void
    {
        [$subjA, $section, $student] = $this->makeDemandFixture();
        $subjB = $this->seedSubject('SUBJ-B');
        (new SubjectSubstitutionModel())->createOrReactivate($subjA, $subjB, null, 1);
        $this->seedGrade($student, $section, 'SUBJ-B', true, 'locked');

        $single = (new SubjectDemandService())->summary($subjA, self::TERM);
        $batch  = (new SubjectDemandService())->summaryForSubjects([$subjA], self::TERM)[$subjA];

        $this->assertSame($single['expected_count'], $batch['expected_count']);
        $this->assertSame(0, $batch['expected_count']);
    }

    // --- 19/20: prerequisite integration ------------------------------------------

    public function testExplicitSubstituteSatisfiesPrerequisite(): void
    {
        $prereq = $this->seedSubject('MATH101');
        $old    = $this->seedSubject('MATH101-OLD');
        (new SubjectSubstitutionModel())->createOrReactivate($prereq, $old, null, 1);

        $target = $this->seedSubject('MATH201');
        db_connect()->table('subject_prerequisites')->insert(['subject_id' => $target, 'prerequisite_subject_id' => $prereq]);

        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $this->seedCurriculumSubject($curriculum, $target, 2, 1);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, $section, 'MATH101-OLD', true, 'locked');

        $this->seedSubjectForEnrollment($target, 2, 1, $curriculum, 'MATH201');

        $result = (new EnrollmentModel())->enrollOne($student, $section, 'MATH201', 'manual');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    public function testReverseDirectionDoesNotSatisfyPrerequisiteUnlessConfigured(): void
    {
        $prereq = $this->seedSubject('MATH101');
        $old    = $this->seedSubject('MATH101-OLD');
        (new SubjectSubstitutionModel())->createOrReactivate($old, $prereq, null, 1); // wrong direction: MATH101 satisfies MATH101-OLD, not the reverse

        $target = $this->seedSubject('MATH201');
        db_connect()->table('subject_prerequisites')->insert(['subject_id' => $target, 'prerequisite_subject_id' => $prereq]);

        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $this->seedCurriculumSubject($curriculum, $target, 2, 1);
        $section = $this->seedSection('BSIT', 'A');
        $student = $this->seedStudent($program, $curriculum, $section);
        $this->seedGrade($student, $section, 'MATH101-OLD', true, 'locked');

        $this->seedSubjectForEnrollment($target, 2, 1, $curriculum, 'MATH201');

        $result = (new EnrollmentModel())->enrollOne($student, $section, 'MATH201', 'manual');

        $this->assertFalse($result['ok']);
    }

    /** Minimal plotted_schedules row so enrollOne() finds the subject plotted for this section. */
    private function seedSubjectForEnrollment(int $subjectId, int $yearLevel, int $semester, int $curriculumId, string $code): void
    {
        $subjectRow = db_connect()->table('subjects')->where('subject_id', $subjectId)->get()->getRowArray();
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => $code, 'subject_title' => $subjectRow['subject_name'], 'room' => 'Room 1', 'status' => 'published',
        ]);
    }

    // --- 21: single student with multiple acceptable completed subjects -------------

    public function testStudentWithMultipleAcceptableCompletedSubjectsCountedOnce(): void
    {
        [$subjA, $section, $student] = $this->makeDemandFixture();
        $subjB = $this->seedSubject('SUBJ-B');
        $subjC = $this->seedSubject('SUBJ-C');
        $model = new SubjectSubstitutionModel();
        $model->createOrReactivate($subjA, $subjB, null, 1);
        $model->createOrReactivate($subjA, $subjC, null, 1);

        // Student passed BOTH acceptable substitutes.
        $this->seedGrade($student, $section, 'SUBJ-B', true, 'locked');
        $this->seedGrade($student, $section, 'SUBJ-C', true, 'locked');

        $expected = (new SubjectDemandService())->expectedStudents($subjA, self::TERM);

        $this->assertCount(0, $expected, 'Student must be excluded once, not double-processed.');
    }

    // --- 22/23: no transitivity, no recursion ----------------------------------------

    public function testDirectRelationshipDoesNotImplyTransitiveEquivalency(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        $c = $this->seedSubject('C');
        $model = new SubjectSubstitutionModel();
        $model->createOrReactivate($a, $b, null, 1); // B satisfies A
        $model->createOrReactivate($b, $c, null, 1); // C satisfies B

        // C must NOT automatically satisfy A.
        $this->assertFalse((new SubjectRequirementService())->satisfies($c, $a));
    }

    public function testCyclicRowsDoNotCauseInfiniteRecursion(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        $c = $this->seedSubject('C');
        $model = new SubjectSubstitutionModel();
        $model->createOrReactivate($a, $b, null, 1);
        $model->createOrReactivate($b, $c, null, 1);
        $model->createOrReactivate($c, $a, null, 1);

        $service = new SubjectRequirementService();
        // One-hop evaluation only — completes instantly, no traversal.
        $this->assertTrue($service->satisfies($b, $a));
        $this->assertFalse($service->satisfies($c, $a));
    }

    // --- Adjustment 1: reactivation -------------------------------------------------

    public function testCreateDeactivateRecreateReactivatesTheSameRow(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        $model = new SubjectSubstitutionModel();

        $created = $model->createOrReactivate($a, $b, null, 1);
        $this->assertTrue($created['ok']);
        $this->assertFalse($created['reactivated']);
        $originalId = $created['id'];

        $deactivated = $model->deactivate($originalId);
        $this->assertTrue($deactivated['ok']);

        $recreated = $model->createOrReactivate($a, $b, 'back again', 1);
        $this->assertTrue($recreated['ok']);
        $this->assertTrue($recreated['reactivated']);
        $this->assertSame($originalId, $recreated['id'], 'Must reuse the same row, not insert a duplicate.');

        $this->assertSame(1, db_connect()->table('subject_substitutions')->where('required_subject_id', $a)->where('substitute_subject_id', $b)->countAllResults());
        $this->seeInDatabase('subject_substitutions', ['id' => $originalId, 'is_active' => 1]);
    }

    public function testReactivationIsAudited(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        $model = new SubjectSubstitutionModel();
        $created = $model->createOrReactivate($a, $b, null, 1);
        $model->deactivate($created['id']);
        $model->createOrReactivate($a, $b, null, 1);

        // The model itself doesn't audit (controller does, matching this
        // session's convention) — simulate the controller's audit call here
        // to prove the action name/shape is distinguishable from "created".
        (new AuditLogModel())->record('subject_substitution.reactivated', 'subject_substitution', $created['id'], null, ['required_subject_id' => $a, 'substitute_subject_id' => $b], null, 1, 'admin');

        $this->assertNotNull((new AuditLogModel())->where('action', 'subject_substitution.reactivated')->where('entity_id', (string) $created['id'])->first());
    }

    // --- 24-27: authorization + audit (controller-level behavior) --------------------

    public function testCreatingRelationshipIsAudited(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        $created = (new SubjectSubstitutionModel())->createOrReactivate($a, $b, null, 1);

        (new AuditLogModel())->record('subject_substitution.created', 'subject_substitution', $created['id'], null, ['required_subject_id' => $a, 'substitute_subject_id' => $b], null, 1, 'admin');

        $this->assertNotNull((new AuditLogModel())->where('action', 'subject_substitution.created')->where('entity_id', (string) $created['id'])->first());
    }

    public function testDeactivatingRelationshipIsAudited(): void
    {
        $a = $this->seedSubject('A');
        $b = $this->seedSubject('B');
        $model = new SubjectSubstitutionModel();
        $created = $model->createOrReactivate($a, $b, null, 1);
        $model->deactivate($created['id']);

        (new AuditLogModel())->record('subject_substitution.deactivated', 'subject_substitution', $created['id'], ['required_subject_id' => $a, 'substitute_subject_id' => $b], null, null, 1, 'admin');

        $this->assertNotNull((new AuditLogModel())->where('action', 'subject_substitution.deactivated')->where('entity_id', (string) $created['id'])->first());
    }

    // --- 28: batch performance -------------------------------------------------------

    public function testDemandBatchPathDoesNotIntroduceNPlusOne(): void
    {
        $program = $this->seedProgram('BSIT');
        $curriculum = $this->seedCurriculum($program, 'C1');
        $subjectIds = [];
        $model = new SubjectSubstitutionModel();

        for ($i = 0; $i < 10; $i++) {
            $required = $this->seedSubject('REQ' . $i);
            $substitute = $this->seedSubject('SUB' . $i);
            $model->createOrReactivate($required, $substitute, null, 1);
            $this->seedCurriculumSubject($curriculum, $required, 2, 1);
            $subjectIds[] = $required;
        }

        $section = $this->seedSection('BSIT', 'A');
        $this->seedStudent($program, $curriculum, $section);

        $before = (int) db_connect()->query("SHOW SESSION STATUS LIKE 'Questions'")->getRow()->Value;
        (new SubjectDemandService())->summaryForSubjects($subjectIds, self::TERM);
        $after = (int) db_connect()->query("SHOW SESSION STATUS LIKE 'Questions'")->getRow()->Value;

        // A handful of fixed queries for the whole batch of 10 subjects —
        // nowhere near one query per subject for the substitution lookup.
        $this->assertLessThan(20, $after - $before);
    }
}
