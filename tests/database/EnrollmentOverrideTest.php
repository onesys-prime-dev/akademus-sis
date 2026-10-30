<?php

use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use App\Models\SubjectPrerequisiteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Registrar/admin prerequisite override: it bypasses ONLY an unmet
 * prerequisite, needs a reason, and leaves an audit row naming what was
 * missing, why, and who approved it.
 *
 * @internal
 */
final class EnrollmentOverrideTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const AY      = 'AY 2026 - 2027 · 1st Semester';
    // Sections are year-scoped (no semester of their own) — classOptions()
    // and enrollOne()'s own section lock both match sec.academic_year
    // against this year-only form, while plotted_schedules/enrollments
    // keep the full term string above.
    private const SECTION_AY = 'AY 2026 - 2027';
    private const PROGRAM = 'BS Test Program';

    /** @var array<string, int> */
    private array $subjectIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUsers(800, 801);

        // enrollOne() now derives "which term" from
        // AcademicYearModel::activeFormatted() instead of the section's own
        // academic_year — needs a real active row or it fails before
        // reaching any of the behavior under test here.
        db_connect()->table('academic_years')->insert(['label' => '2026-2027', 'semester' => '1st', 'is_active' => 1]);

        foreach (['OV-A', 'OV-B', 'OV-C'] as $code) {
            db_connect()->table('subjects')->insert([
                'subject_code'  => $code,
                'subject_name'  => $code . ' Title',
                'units'         => 3,
                'contact_hours' => 1.5,
                'is_active'     => 1,
            ]);
            $this->subjectIds[$code] = (int) db_connect()->insertID();
        }

        // OV-B requires OV-A, which student 800 has NOT passed.
        (new SubjectPrerequisiteModel())->addPrerequisite($this->subjectIds['OV-B'], $this->subjectIds['OV-A']);
    }

    protected function tearDown(): void
    {
        $db = db_connect();
        $db->disableForeignKeyChecks();

        foreach (['enrollment_logs', 'enrollments', 'plotted_schedules', 'sections'] as $table) {
            $db->table($table)->truncate();
        }

        $db->enableForeignKeyChecks();

        parent::tearDown();
    }

    private function makeSection(): array
    {
        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => self::SECTION_AY,
            'program'       => self::PROGRAM,
            'year_level'    => '1st Year',
            'section'       => 'A',
            'capacity'      => null,
        ]);

        return $sections->find($sections->getInsertID());
    }

    private function plot(array $section, string $code, string $start, string $end): void
    {
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::AY,
            'program'       => $section['program'],
            'year_level'    => $section['year_level'],
            'section'       => $section['section'],
            'day'           => 0,
            'start_time'    => $start,
            'end_time'      => $end,
            'time_label'    => "{$start}-{$end}",
            'subject_code'  => $code,
            'subject_title' => $code . ' Title',
            'room'          => 'Room 1',
            'theme'         => 'blue',
            'status'        => 'published',
        ]);
    }

    public function testUnmetPrerequisiteBlocksWithoutAnOverride(): void
    {
        $section = $this->makeSection();
        $this->plot($section, 'OV-B', '08:00:00', '09:30:00');

        $result = (new EnrollmentModel())->enrollOne(800, (int) $section['section_id'], 'OV-B', 'manual');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Prerequisite not met for OV-B: OV-A', $result['error']);
    }

    public function testAnOverrideWithAReasonEnrollsAndIsAudited(): void
    {
        $section = $this->makeSection();
        $this->plot($section, 'OV-B', '08:00:00', '09:30:00');

        $result = (new EnrollmentModel())->enrollOne(800, (int) $section['section_id'], 'OV-B', 'manual', [
            'reason' => 'Credited from previous school',
            'by'     => 801,
        ]);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('enrollments', ['enrollment_id' => $result['enrollment_id'], 'status' => 'enrolled']);
        $this->seeInDatabase('enrollment_logs', [
            'enrollment_id' => $result['enrollment_id'],
            'to_status'     => 'enrolled',
            'changed_by'    => 801,
            'reason'        => 'Prerequisite override (unmet: OV-A): Credited from previous school',
        ]);
    }

    public function testAnOverrideWithABlankReasonIsStillBlocked(): void
    {
        $section = $this->makeSection();
        $this->plot($section, 'OV-B', '08:00:00', '09:30:00');

        $result = (new EnrollmentModel())->enrollOne(800, (int) $section['section_id'], 'OV-B', 'manual', ['reason' => '   ', 'by' => 801]);

        $this->assertFalse($result['ok']);
        $this->assertSame(0, db_connect()->table('enrollments')->countAllResults());
    }

    public function testTheOverrideDoesNotBypassAScheduleConflict(): void
    {
        $section = $this->makeSection();
        $this->plot($section, 'OV-C', '08:00:00', '09:30:00'); // no prerequisites
        $this->plot($section, 'OV-B', '09:00:00', '10:30:00'); // overlaps OV-C, prerequisite unmet

        $model = new EnrollmentModel();
        $this->assertTrue($model->enrollOne(800, (int) $section['section_id'], 'OV-C', 'manual')['ok']);

        $result = $model->enrollOne(800, (int) $section['section_id'], 'OV-B', 'manual', ['reason' => 'Approved by dean', 'by' => 801]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Schedule conflict', $result['error']);
    }

    public function testAnOverrideIsNotLoggedWhenNoPrerequisiteWasMissing(): void
    {
        $section = $this->makeSection();
        $this->plot($section, 'OV-C', '08:00:00', '09:30:00'); // no prerequisites

        $result = (new EnrollmentModel())->enrollOne(800, (int) $section['section_id'], 'OV-C', 'manual', ['reason' => 'Not needed', 'by' => 801]);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, db_connect()->table('enrollment_logs')->countAllResults(), 'no override happened, so nothing to log');
    }

    public function testClassOptionsListsWhatIsPlottedForTheProgramAndTerm(): void
    {
        $section = $this->makeSection();
        $this->plot($section, 'OV-C', '08:00:00', '09:30:00');
        $this->plot($section, 'OV-B', '10:00:00', '11:30:00');

        $options = (new EnrollmentModel())->classOptions(self::PROGRAM, self::AY);

        $this->assertSame(['OV-B', 'OV-C'], array_column($options, 'subject_code'));
        $this->assertSame((int) $section['section_id'], $options[0]['section_id']);
        $this->assertSame([], (new EnrollmentModel())->classOptions(self::PROGRAM, 'AY 2030 - 2031 · 1st Semester'));
    }
}
