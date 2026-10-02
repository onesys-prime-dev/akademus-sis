<?php

use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Regression coverage for the catalog-integrity hardening: a subject_code
 * reaching enrollments (EnrollmentModel::enrollOne()) or
 * faculty_assignment_overrides (ChairpersonController::recordFacultyOverride())
 * must now resolve to a real, active row in the central subjects catalog —
 * see the catalog-integrity audit. Both tables have no subject_id FK at all,
 * so this is model/controller-level validation, not a schema constraint.
 *
 * Deliberately narrow: no curriculum/section logic is touched, and existing
 * (pre-fix) rows are never re-validated — only NEW writes are gated.
 *
 * @internal
 */
final class CatalogIntegrityHardeningTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    // Short form (not the full "AY 2026 - 2027 · 1st Semester" display
    // string) — WidenAcademicYearColumns' down() narrows this column back
    // to VARCHAR(20) on every DatabaseTestTrait regress cycle; this is the
    // same short-term convention used throughout the Step 4+ test suite.
    private const TERM = '1 · 1st Semester';

    private function seedSubject(string $code, ?int $subDeptId = null): int
    {
        $db = db_connect();
        $db->table('subjects')->insert([
            'subject_code'      => $code,
            'subject_name'      => $code . ' Title',
            'units'             => 3,
            'is_active'         => 1,
            'sub_department_id' => $subDeptId,
        ]);

        return (int) $db->insertID();
    }

    private function makeSection(string $program = 'BS Catalog Test'): array
    {
        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => self::TERM,
            'program'       => $program,
            'year_level'    => '1st Year',
            'section'       => 'A',
        ]);

        return $sections->find($sections->getInsertID());
    }

    private function plotLegacy(array $section, string $subjectCode, int $day, string $start, string $end): void
    {
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => $section['academic_year'],
            'program'       => $section['program'],
            'year_level'    => $section['year_level'],
            'section'       => $section['section'],
            'day'           => $day,
            'start_time'    => $start,
            'end_time'      => $end,
            'time_label'    => "{$start}-{$end}",
            'subject_code'  => $subjectCode,
            'subject_title' => $subjectCode . ' Title',
            'room'          => 'Room 1',
            'theme'         => 'blue',
            'status'        => 'published',
        ]);
    }

    // --- Enrollment: catalog validation ---------------------------------

    public function testValidCatalogSubjectStillEnrollsNormally(): void
    {
        $this->seedUsers(900);
        $this->seedSubject('CAT101');
        $section = $this->makeSection();
        $this->plotLegacy($section, 'CAT101', 0, '08:00:00', '09:30:00');

        $result = (new EnrollmentModel())->enrollOne(900, (int) $section['section_id'], 'CAT101', 'manual');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('enrollments', ['user_id' => 900, 'subject_code' => 'CAT101', 'status' => 'enrolled']);
    }

    public function testScheduledButNonexistentSubjectCannotBeEnrolled(): void
    {
        $this->seedUsers(901);
        $section = $this->makeSection();
        // Plotted, but deliberately NOT in the subjects catalog.
        $this->plotLegacy($section, 'GHOST101', 0, '08:00:00', '09:30:00');

        $result = (new EnrollmentModel())->enrollOne(901, (int) $section['section_id'], 'GHOST101', 'manual');

        $this->assertFalse($result['ok']);
        $this->assertSame('GHOST101 is not a recognized subject. Please contact the registrar.', $result['error']);
        $this->dontSeeInDatabase('enrollments', ['user_id' => 901, 'subject_code' => 'GHOST101']);
    }

    public function testOutsideCurriculumValidationStillBehavesCorrectlyForValidSubjects(): void
    {
        $this->seedUsers(902);
        $subjectId = $this->seedSubject('CAT201');
        $otherSubjectId = $this->seedSubject('CAT202');
        $section = $this->makeSection();
        $this->plotLegacy($section, 'CAT201', 0, '08:00:00', '09:30:00');
        $this->plotLegacy($section, 'CAT202', 1, '10:00:00', '11:30:00');

        $db = db_connect();
        $db->table('programs')->insert(['program_code' => 'CATTEST', 'program_name' => 'Catalog Test Program', 'is_active' => 1]);
        $programId = (int) $db->insertID();

        $db->table('curriculum')->insert([
            'program_id' => $programId, 'curriculum_code' => 'CATTEST-1', 'curriculum_name' => 'Catalog Test Curriculum',
            'academic_year' => '2026-2027', 'is_active' => 1, 'version_number' => 1, 'status' => 'published', 'is_current' => 1,
        ]);
        $curriculumId = (int) $db->insertID();
        $db->table('curriculum')->where('curriculum_id', $curriculumId)->update(['base_curriculum_id' => $curriculumId]);

        $db->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculumId, 'subject_id' => $subjectId, 'year_level' => 1, 'semester' => 1, 'is_required' => 1, 'is_active' => 1,
        ]);
        // CAT202 deliberately NOT part of this curriculum.

        $db->table('students')->insert([
            'user_id' => 902, 'applicant_id' => 'CATTEST-902', 'student_number' => 'CATTEST-902',
            'curriculum_id' => $curriculumId, 'student_status' => 'active',
        ]);

        $enrollments = new EnrollmentModel();

        $inCurriculum = $enrollments->enrollOne(902, (int) $section['section_id'], 'CAT201', 'manual');
        $this->assertTrue($inCurriculum['ok'], $inCurriculum['error'] ?? '');

        $outsideCurriculum = $enrollments->enrollOne(902, (int) $section['section_id'], 'CAT202', 'manual');
        $this->assertFalse($outsideCurriculum['ok']);
        $this->assertSame('CAT202 is not part of your assigned curriculum.', $outsideCurriculum['error']);
    }

    // --- Historical rows unaffected --------------------------------------

    public function testExistingHistoricalEnrollmentRowsAreUnaffected(): void
    {
        $this->seedUsers(903);
        $section = $this->makeSection();

        // A pre-existing row inserted directly (bypassing enrollOne()'s new
        // validation entirely), simulating data that existed before this
        // hardening — e.g. a subject later deactivated/renamed.
        db_connect()->table('enrollments')->insert([
            'user_id' => 903, 'section_id' => $section['section_id'], 'subject_code' => 'ORPHANCODE', 'status' => 'enrolled', 'method' => 'manual',
        ]);
        $enrollmentId = (int) db_connect()->insertID();

        // Reading/acting on the EXISTING row (drop()) must still work —
        // only NEW enrollOne() calls are gated, never historical rows.
        $result = (new EnrollmentModel())->drop($enrollmentId, 'Historical cleanup test', 903, 903);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('enrollments', ['enrollment_id' => $enrollmentId, 'subject_code' => 'ORPHANCODE', 'status' => 'dropped']);
    }

    // --- Faculty override: catalog validation ----------------------------

    private function seedChairperson(int $id): void
    {
        $this->seedUsers($id);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
    }

    /** CSRF is on globally, so every JSON POST in these tests must carry the header the app itself uses (see layouts/admin_console.php). */
    private function postOverride(array $payload)
    {
        return $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])
            ->withBodyFormat('json')
            ->withSession()
            ->post('/chairperson/schedule/faculty-override', $payload);
    }

    public function testValidFacultyOverrideStillWorks(): void
    {
        $chairId = 910;
        $this->seedChairperson($chairId);
        $this->seedUsers(911);
        $this->seedSubject('CAT301');
        $this->actingAs(auth()->getProvider()->findById($chairId));

        $response = $this->postOverride([
            'faculty_id' => 911, 'academic_year' => self::TERM, 'program' => 'BS Catalog Test',
            'year_level' => '1st Year', 'section' => 'A', 'subject_code' => 'CAT301',
            'day' => 0, 'start' => '08:00', 'end' => '09:30', 'conflict_type' => 'unavailable',
        ]);

        $response->assertStatus(200);
        $response->assertJSONFragment(['ok' => true]);
        $this->seeInDatabase('faculty_assignment_overrides', ['faculty_id' => 911, 'subject_code' => 'CAT301']);
    }

    public function testInvalidSubjectCodeIsRejectedForFacultyOverrides(): void
    {
        $chairId = 912;
        $this->seedChairperson($chairId);
        $this->seedUsers(913);
        $this->actingAs(auth()->getProvider()->findById($chairId));

        $response = $this->postOverride([
            'faculty_id' => 913, 'academic_year' => self::TERM, 'program' => 'BS Catalog Test',
            'year_level' => '1st Year', 'section' => 'A', 'subject_code' => 'GHOST301',
            'day' => 0, 'start' => '08:00', 'end' => '09:30', 'conflict_type' => 'unavailable',
        ]);

        $response->assertJSONFragment(['ok' => false]);
        $this->dontSeeInDatabase('faculty_assignment_overrides', ['subject_code' => 'GHOST301']);
    }
}
