<?php

use App\Models\EnlistmentOverrideModel;
use App\Models\SectionModel;
use App\Models\StudentEnlistmentModel;
use App\Models\SubjectPrerequisiteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Whether a staff-granted EnlistmentOverride actually lets an irregular
 * student add a prerequisite-blocked subject to their self-service
 * enlistment cart. The wizard's own display logic (StudentEnlistment::
 * wizard()) already consults EnlistmentOverrideModel::hasOverride() to
 * decide whether to even SHOW the subject as due — this verifies the write
 * path (StudentEnlistmentModel::addItem() -> EnrollmentModel::
 * checkEligibility()) honors the same override, not just the display.
 *
 * @internal
 */
final class IrregularStudentEnlistmentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private const AY = 'AY 2026 - 2027 · 1st Semester';

    private int $studentUserId = 900;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();

        $db->table('subjects')->insert(['subject_code' => 'IRR-A', 'subject_name' => 'IRR-A Title', 'units' => 3, 'contact_hours' => 1.5, 'is_active' => 1]);
        $prereqId = (int) $db->insertID();
        $db->table('subjects')->insert(['subject_code' => 'IRR-B', 'subject_name' => 'IRR-B Title', 'units' => 3, 'contact_hours' => 1.5, 'is_active' => 1]);
        $subjectId = (int) $db->insertID();

        (new SubjectPrerequisiteModel())->addPrerequisite($subjectId, $prereqId);

        $this->seedUsers($this->studentUserId);
        $db->table('students')->insert([
            'user_id' => $this->studentUserId, 'applicant_id' => 'A900', 'student_number' => 'S900', 'student_status' => 'active',
        ]);
        $this->studentId = (int) $db->insertID();

        $sections = new SectionModel();
        $sections->insert(['academic_year' => self::AY, 'program' => 'BS Test Program', 'year_level' => '2nd Year', 'section' => 'A']);
        $sectionId = (int) $sections->getInsertID();

        $db->table('plotted_schedules')->insert([
            'academic_year' => self::AY, 'program' => 'BS Test Program', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'IRR-B', 'subject_title' => 'IRR-B Title', 'room' => 'Room 1', 'theme' => 'blue', 'status' => 'published',
        ]);

        $this->sectionId = $sectionId;
    }

    private int $sectionId;

    public function testAddItemIsBlockedByAnUnmetPrerequisiteWithoutAnOverride(): void
    {
        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);

        $result = $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'IRR-B');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Prerequisite not met for IRR-B', $result['error']);
    }

    public function testAGrantedOverrideLetsTheStudentActuallyAddTheBlockedSubject(): void
    {
        $granted = (new EnlistmentOverrideModel())->grant($this->studentId, 'IRR-B', self::AY, 'Transferee credit pending evaluation', 801, 'chairperson');
        $this->assertTrue($granted['ok'], $granted['error'] ?? '');

        $model      = new StudentEnlistmentModel();
        $enlistment = $model->openOrCreate($this->studentId, self::AY);

        $result = $model->addItem((int) $enlistment['id'], $this->studentUserId, $this->sectionId, 'IRR-B');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('enlistment_items', ['enlistment_id' => $enlistment['id'], 'subject_code' => 'IRR-B']);
    }
}
