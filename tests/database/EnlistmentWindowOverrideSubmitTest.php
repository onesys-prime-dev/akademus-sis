<?php

use App\Models\EnlistmentWindowOverrideModel;
use App\Models\SectionModel;
use App\Models\StudentEnlistmentModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Whether a staff-granted EnlistmentWindowOverride actually lets
 * StudentEnlistmentModel::submit() succeed while the global enlistment
 * window is closed, and stops working the instant it's revoked.
 *
 * @internal
 */
final class EnlistmentWindowOverrideSubmitTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private const AY = 'AY 2026 - 2027 · 1st Semester';

    private int $studentUserId = 930;
    private int $studentId;
    private int $sectionId;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();

        $db->table('subjects')->insert(['subject_code' => 'WIN-A', 'subject_name' => 'WIN-A Title', 'units' => 3, 'contact_hours' => 1.5, 'is_active' => 1]);

        $this->seedUsers($this->studentUserId);
        $db->table('students')->insert([
            'user_id' => $this->studentUserId, 'applicant_id' => 'A930', 'student_number' => 'S930', 'student_status' => 'active',
        ]);
        $this->studentId = (int) $db->insertID();

        $sections = new SectionModel();
        $sections->insert(['academic_year' => self::AY, 'program' => 'BS Test Program', 'year_level' => '2nd Year', 'section' => 'A']);
        $this->sectionId = (int) $sections->getInsertID();

        $db->table('plotted_schedules')->insert([
            'academic_year' => self::AY, 'program' => 'BS Test Program', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'WIN-A', 'subject_title' => 'WIN-A Title', 'room' => 'Room 1', 'theme' => 'blue', 'status' => 'published',
        ]);

        // Upcoming term, window left closed (null opens/closes_at) — the
        // default per AcademicYearModel::isEnlistmentOpen()'s doc.
        $db->table('academic_years')->insert(['label' => '2026-2027', 'semester' => '1st', 'is_active' => 0]);
    }

    public function testSubmitIsBlockedWhenTheWindowIsClosedAndNoOverrideExists(): void
    {
        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);
        $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'WIN-A');

        $result = $model->submit((int) $enlistment['id'], $this->studentId);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('closed', $result['error']);
    }

    public function testAGrantedWindowOverrideLetsSubmitSucceedDespiteTheClosedWindow(): void
    {
        $granted = (new EnlistmentWindowOverrideModel())->grant($this->studentId, self::AY, 'Catch-up term', 801, 'chairperson');
        $this->assertTrue($granted['ok'], $granted['error'] ?? '');

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);
        $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'WIN-A');

        $result = $model->submit((int) $enlistment['id'], $this->studentId);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('student_enlistments', ['id' => $enlistment['id'], 'status' => 'submitted']);
    }

    public function testARevokedWindowOverrideNoLongerUnblocksSubmit(): void
    {
        $granted = (new EnlistmentWindowOverrideModel())->grant($this->studentId, self::AY, 'Catch-up term', 801, 'chairperson');
        (new EnlistmentWindowOverrideModel())->revoke((int) $granted['id'], 802);

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);
        $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'WIN-A');

        $result = $model->submit((int) $enlistment['id'], $this->studentId);

        $this->assertFalse($result['ok']);
    }
}
