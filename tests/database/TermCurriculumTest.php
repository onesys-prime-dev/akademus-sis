<?php

use App\Libraries\TermCurriculum;
use App\Models\CurriculumModel;
use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * The curriculum cutover: a program's published curriculum version is the
 * source of truth for what a term offers, and (for students pinned to a
 * version) for which subjects they may enroll in.
 *
 * @internal
 */
final class TermCurriculumTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const PROGRAM_NAME = 'BS Test Program';
    private const AY           = 'AY 2026 - 2027 · 1st Semester';

    private int $programId;

    protected function setUp(): void
    {
        parent::setUp();

        db_connect()->table('programs')->insert([
            'program_code' => 'BSTP',
            'program_name' => self::PROGRAM_NAME,
            'is_active'    => 1,
        ]);
        $this->programId = (int) db_connect()->insertID();
    }

    /**
     * The realistic term string used here is longer than 20 characters, and
     * the next test's refresh rolls WidenAcademicYearColumns back (which
     * narrows academic_year to 20) — so leftover rows would abort that
     * rollback. Clear them out.
     */
    protected function tearDown(): void
    {
        $db = db_connect();
        $db->disableForeignKeyChecks();

        foreach (['enrollments', 'plotted_schedules', 'sections'] as $table) {
            $db->table($table)->truncate();
        }

        $db->enableForeignKeyChecks();

        parent::tearDown();
    }

    private function makeSubject(string $code, bool $lab = false): int
    {
        db_connect()->table('subjects')->insert([
            'program_id'    => $this->programId,
            'subject_code'  => $code,
            'subject_name'  => $code . ' Title',
            'units'         => 3,
            'contact_hours' => 1.5,
            'is_laboratory' => $lab ? 1 : 0,
            'is_active'     => 1,
        ]);

        return (int) db_connect()->insertID();
    }

    /** @param list<array{subject: int, year: int, sem: int, lab?: int|null}> $rows */
    private function makeCurriculum(string $status, array $rows, bool $current = true): int
    {
        $model = new CurriculumModel();
        $model->insert([
            'program_id'      => $this->programId,
            'curriculum_code' => 'TP-' . $status,
            'curriculum_name' => 'Test curriculum',
            'academic_year'   => '2026-2027',
            'is_active'       => 1,
            'version_number'  => 1,
            'status'          => $status,
            'is_current'      => $current ? 1 : 0,
        ]);
        $id = (int) $model->getInsertID();

        foreach ($rows as $r) {
            db_connect()->table('curriculum_subjects')->insert([
                'curriculum_id' => $id,
                'subject_id'    => $r['subject'],
                'year_level'    => $r['year'],
                'semester'      => $r['sem'],
                'is_required'   => 1,
                'is_laboratory' => $r['lab'] ?? null,
                'is_active'     => 1,
            ]);
        }

        return $id;
    }

    public function testPublishedCurriculumSuppliesTheTermsSubjects(): void
    {
        $a = $this->makeSubject('TP101');
        $b = $this->makeSubject('TP102');
        $c = $this->makeSubject('TP201'); // different year level
        $d = $this->makeSubject('TP103'); // different semester

        $this->makeCurriculum('published', [
            ['subject' => $a, 'year' => 1, 'sem' => 1],
            ['subject' => $b, 'year' => 1, 'sem' => 1],
            ['subject' => $c, 'year' => 2, 'sem' => 1],
            ['subject' => $d, 'year' => 1, 'sem' => 2],
        ]);

        $term = (new TermCurriculum())->forContext(self::AY, self::PROGRAM_NAME, '1st Year');

        $this->assertNotNull($term);
        $this->assertSame(['TP101', 'TP102'], array_column($term['subjects'], 'code'));
        // Same catalog shape the plotting UI already consumes.
        $this->assertArrayHasKey('color', $term['subjects'][0]);
        $this->assertSame('3 Units', $term['subjects'][0]['units']);
    }

    /**
     * The draft/published/is_current workflow was removed (see
     * CurriculumModel::liveFor()) — every program now has exactly one
     * always-live curriculum, and its legacy `status` column (left on the
     * table for old data, never meaningfully written to anymore) is no
     * longer a gate. A row that still says 'draft' from before that change
     * governs scheduling exactly the same as any other.
     */
    public function testLegacyDraftStatusNoLongerBlocksGovernance(): void
    {
        $a = $this->makeSubject('TP101');
        $this->makeCurriculum('draft', [['subject' => $a, 'year' => 1, 'sem' => 1]], false);

        $term = (new TermCurriculum())->forContext(self::AY, self::PROGRAM_NAME, '1st Year');

        $this->assertNotNull($term);
        $this->assertSame(['TP101'], array_column($term['subjects'], 'code'));
    }

    public function testEmptyTermIsAuthoritativeNotAFallback(): void
    {
        $a = $this->makeSubject('TP101');
        $this->makeCurriculum('published', [['subject' => $a, 'year' => 1, 'sem' => 1]]);

        $term = (new TermCurriculum())->forContext('AY 2026 - 2027 · 2nd Semester', self::PROGRAM_NAME, '1st Year');

        $this->assertNotNull($term);
        $this->assertSame([], $term['subjects']);
    }

    public function testYearOnlyTermStringFallsBack(): void
    {
        $a = $this->makeSubject('TP101');
        $this->makeCurriculum('published', [['subject' => $a, 'year' => 1, 'sem' => 1]]);

        $this->assertNull((new TermCurriculum())->forContext('AY 2026 - 2027', self::PROGRAM_NAME, '1st Year'));
    }

    public function testLabClassificationComesFromTheCurriculumWithSubjectDefaultAsFallback(): void
    {
        $lecDefault = $this->makeSubject('TP101', false);
        $labDefault = $this->makeSubject('TP102', true);
        $forcedLab  = $this->makeSubject('TP103', false);

        $this->makeCurriculum('published', [
            ['subject' => $lecDefault, 'year' => 1, 'sem' => 1],           // null => subject default (lecture)
            ['subject' => $labDefault, 'year' => 1, 'sem' => 1],           // null => subject default (lab)
            ['subject' => $forcedLab, 'year' => 1, 'sem' => 1, 'lab' => 1], // curriculum says lab
        ]);

        $byCode = array_column((new TermCurriculum())->forContext(self::AY, self::PROGRAM_NAME, '1st Year')['subjects'], 'is_lab', 'code');

        $this->assertFalse($byCode['TP101']);
        $this->assertTrue($byCode['TP102']);
        $this->assertTrue($byCode['TP103']);
    }

    // ---- enrollment: must be in the student's assigned curriculum ----

    private function enrolmentFixture(?int $curriculumId): array
    {
        $this->seedUsers(500);
        db_connect()->table('students')->insert([
            'user_id'        => 500,
            'applicant_id'   => 'APP-500',
            'student_number' => 'STU-2026-00500',
            'program_id'     => $this->programId,
            'curriculum_id'  => $curriculumId,
            'student_status' => 'active',
        ]);

        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => self::AY,
            'program'       => self::PROGRAM_NAME,
            'year_level'    => '1st Year',
            'section'       => 'A',
            'capacity'      => null,
        ]);
        $section = $sections->find($sections->getInsertID());

        foreach (['TP101' => '08:00:00', 'TP999' => '10:00:00'] as $code => $start) {
            db_connect()->table('plotted_schedules')->insert([
                'academic_year' => $section['academic_year'],
                'program'       => $section['program'],
                'year_level'    => $section['year_level'],
                'section'       => $section['section'],
                'day'           => 0,
                'start_time'    => $start,
                'end_time'      => date('H:i:s', strtotime($start) + 5400),
                'time_label'    => $start,
                'subject_code'  => $code,
                'subject_title' => $code,
                'room'          => 'Room 1',
                'theme'         => 'blue',
                'status'        => 'published',
            ]);
        }

        return $section;
    }

    public function testStudentCanEnrollInASubjectOfTheirCurriculumButNotOutsideIt(): void
    {
        $in = $this->makeSubject('TP101');
        $this->makeSubject('TP999'); // exists in the catalog, NOT in the curriculum
        $curriculumId = $this->makeCurriculum('published', [['subject' => $in, 'year' => 1, 'sem' => 1]]);

        $section = $this->enrolmentFixture($curriculumId);
        $enroll  = new EnrollmentModel();

        $ok = $enroll->enrollOne(500, (int) $section['section_id'], 'TP101', 'manual');
        $this->assertTrue($ok['ok'], $ok['error'] ?? '');

        $blocked = $enroll->enrollOne(500, (int) $section['section_id'], 'TP999', 'manual');
        $this->assertFalse($blocked['ok']);
        $this->assertStringContainsString('not part of your assigned curriculum', $blocked['error']);
    }

    public function testStudentWithoutAnAssignedCurriculumIsNotRestricted(): void
    {
        $this->makeSubject('TP101');
        $this->makeSubject('TP999');

        $section = $this->enrolmentFixture(null);
        $result  = (new EnrollmentModel())->enrollOne(500, (int) $section['section_id'], 'TP999', 'manual');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }
}
