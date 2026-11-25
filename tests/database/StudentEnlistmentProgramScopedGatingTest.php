<?php

use App\Models\AcademicYearModel;
use App\Models\EnlistmentWindowOverrideModel;
use App\Models\ProgramEnlistmentWindowModel;
use App\Models\SectionModel;
use App\Models\StudentEnlistmentModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Program-Scoped mode's gate on the student wizard/submit path, via
 * EnlistmentWindowResolver. See
 * docs/superpowers/specs/2026-11-19-enlistment-window-scoping-design.md.
 *
 * @internal
 */
final class StudentEnlistmentProgramScopedGatingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private const AY = 'AY 2026 - 2027 · 1st Semester';

    private int $studentUserId = 940;
    private int $studentId;
    private int $programId;
    private int $ayId;
    private int $sectionId;

    protected function setUp(): void
    {
        parent::setUp();
        $db = db_connect();

        $db->table('programs')->insert(['program_code' => 'PSG-' . uniqid(), 'program_name' => 'PSG Program']);
        $this->programId = (int) $db->insertID();

        $db->table('subjects')->insert(['subject_code' => 'PSG-A', 'subject_name' => 'PSG-A Title', 'units' => 3, 'contact_hours' => 1.5, 'is_active' => 1]);

        $this->seedUsers($this->studentUserId);
        $db->table('auth_groups_users')->insert(['user_id' => $this->studentUserId, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);
        $db->table('students')->insert([
            'user_id' => $this->studentUserId, 'applicant_id' => 'A940', 'student_number' => 'S940',
            'student_status' => 'active', 'program_id' => $this->programId,
        ]);
        $this->studentId = (int) $db->insertID();

        $sections = new SectionModel();
        $sections->insert(['academic_year' => self::AY, 'program' => 'PSG Program', 'year_level' => '2nd Year', 'section' => 'A']);
        $this->sectionId = (int) $sections->getInsertID();

        $db->table('plotted_schedules')->insert([
            'academic_year' => self::AY, 'program' => 'PSG Program', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'PSG-A', 'subject_title' => 'PSG-A Title', 'room' => 'Room 1', 'theme' => 'blue', 'status' => 'published',
        ]);

        // Upcoming term, Registrar's outer window OPEN — isolates whether
        // the program-level window is what's actually gating things.
        $db->table('academic_years')->insert([
            'label' => '2026-2027', 'semester' => '1st', 'is_active' => 0,
            'start_date' => date('Y-m-d', strtotime('+30 days')),
            'enlistment_opens_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'enlistment_closes_at' => date('Y-m-d H:i:s', strtotime('+60 days')),
        ]);
        $this->ayId = (int) $db->insertID();

        setting('Enlistment.windowMode', null);
    }

    private function openProgramWindow(): void
    {
        (new ProgramEnlistmentWindowModel())->upsert(
            $this->ayId, $this->programId,
            date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+30 days')),
            801
        );
    }

    // ---- wizard() ----

    public function testWizardShowsTheClosedSplashWhenTheStudentsProgramWindowIsClosed(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        $this->actingAs(auth()->getProvider()->findById($this->studentUserId));

        $result = $this->withSession()->get('/student/enlistment');

        $result->assertSee('Enlistment is not yet open');
    }

    public function testWizardOpensWhenTheStudentsProgramWindowIsOpen(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        $this->openProgramWindow();
        $this->actingAs(auth()->getProvider()->findById($this->studentUserId));

        $result = $this->withSession()->get('/student/enlistment');

        $result->assertDontSee('Enlistment is not yet open');
    }

    public function testWizardFallsBackToTermOnlyWhenTheStudentHasNoProgramId(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        db_connect()->table('students')->where('student_id', $this->studentId)->update(['program_id' => null]);
        $this->actingAs(auth()->getProvider()->findById($this->studentUserId));

        $result = $this->withSession()->get('/student/enlistment');

        // Term window is open and no program window ever applies to this
        // student in program_scoped mode — same as registrar_only behavior.
        $result->assertDontSee('Enlistment is not yet open');
    }

    public function testWizardStaysUnchangedInRegistrarOnlyMode(): void
    {
        setting('Enlistment.windowMode', 'registrar_only');
        $this->actingAs(auth()->getProvider()->findById($this->studentUserId));

        $result = $this->withSession()->get('/student/enlistment');

        $result->assertDontSee('Enlistment is not yet open');
    }

    // ---- submit() ----

    public function testSubmitIsRefusedWhenTheStudentsProgramWindowIsClosed(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);
        $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'PSG-A');

        $result = $model->submit((int) $enlistment['id'], $this->studentId);

        $this->assertFalse($result['ok']);
    }

    public function testSubmitSucceedsWhenTheStudentsProgramWindowIsOpen(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        $this->openProgramWindow();

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);
        $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'PSG-A');

        $result = $model->submit((int) $enlistment['id'], $this->studentId);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    public function testSubmitStillSucceedsViaAWindowOverrideRegardlessOfProgramWindow(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        (new EnlistmentWindowOverrideModel())->grant($this->studentId, self::AY, 'Catch-up term', 801, 'chairperson');

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);
        $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'PSG-A');

        $result = $model->submit((int) $enlistment['id'], $this->studentId);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    public function testSubmitStaysUnchangedInRegistrarOnlyMode(): void
    {
        setting('Enlistment.windowMode', 'registrar_only');

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);
        $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'PSG-A');

        $result = $model->submit((int) $enlistment['id'], $this->studentId);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }
}
