<?php

use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use App\Models\SubjectPrerequisiteModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * @internal
 */
final class SubjectPrerequisiteModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    /** @var array<string, int> code => subject_id */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['A', 'B', 'C', 'D'] as $code) {
            db_connect()->table('subjects')->insert([
                'subject_code'  => 'PR-' . $code,
                'subject_name'  => 'Subject ' . $code,
                'units'         => 3,
                'contact_hours' => 1.5,
                'is_active'     => 1,
            ]);
            $this->ids[$code] = (int) db_connect()->insertID();
        }
    }

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

    public function testAddingAPrerequisiteIsListed(): void
    {
        $model  = new SubjectPrerequisiteModel();
        $result = $model->addPrerequisite($this->ids['B'], $this->ids['A']);

        $this->assertTrue($result['ok']);
        $this->assertSame(['PR-A'], array_column($model->forSubject($this->ids['B']), 'subject_code'));
    }

    public function testASubjectCannotBeItsOwnPrerequisite(): void
    {
        $result = (new SubjectPrerequisiteModel())->addPrerequisite($this->ids['A'], $this->ids['A']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('own prerequisite', $result['error']);
    }

    public function testDuplicatePrerequisiteIsRejected(): void
    {
        $model = new SubjectPrerequisiteModel();
        $model->addPrerequisite($this->ids['B'], $this->ids['A']);

        $again = $model->addPrerequisite($this->ids['B'], $this->ids['A']);

        $this->assertFalse($again['ok']);
        $this->assertStringContainsString('already set', $again['error']);
    }

    public function testDirectCycleIsRejected(): void
    {
        $model = new SubjectPrerequisiteModel();
        $model->addPrerequisite($this->ids['B'], $this->ids['A']); // B requires A

        $result = $model->addPrerequisite($this->ids['A'], $this->ids['B']); // A requires B => loop

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('circular', $result['error']);
    }

    public function testTransitiveCycleIsRejected(): void
    {
        $model = new SubjectPrerequisiteModel();
        $model->addPrerequisite($this->ids['B'], $this->ids['A']); // B requires A
        $model->addPrerequisite($this->ids['C'], $this->ids['B']); // C requires B

        $result = $model->addPrerequisite($this->ids['A'], $this->ids['C']); // A requires C => A>C>B>A

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('circular', $result['error']);
    }

    public function testUnknownSubjectIsRejected(): void
    {
        $result = (new SubjectPrerequisiteModel())->addPrerequisite($this->ids['A'], 999999);

        $this->assertFalse($result['ok']);
    }

    public function testCandidatesExcludeSelfExistingAndCycleCausingSubjects(): void
    {
        $model = new SubjectPrerequisiteModel();
        $model->addPrerequisite($this->ids['B'], $this->ids['A']); // B requires A
        $model->addPrerequisite($this->ids['C'], $this->ids['B']); // C requires B

        // For A: not itself, and not B or C (each would loop back to A).
        $this->assertSame(['PR-D'], array_column($model->candidatesFor($this->ids['A']), 'subject_code'));

        // For C: not itself, not B (already set); A is fine (redundant but acyclic); D fine.
        $this->assertSame(['PR-A', 'PR-D'], array_column($model->candidatesFor($this->ids['C']), 'subject_code'));
    }

    public function testRemovingAPrerequisite(): void
    {
        $model = new SubjectPrerequisiteModel();
        $model->addPrerequisite($this->ids['B'], $this->ids['A']);

        $this->assertTrue($model->removePrerequisite($this->ids['B'], $this->ids['A']));
        $this->assertSame([], $model->forSubject($this->ids['B']));
        $this->assertFalse($model->removePrerequisite($this->ids['B'], $this->ids['A']));
    }

    public function testEnrollmentIsBlockedUntilThePrerequisiteIsPassed(): void
    {
        (new SubjectPrerequisiteModel())->addPrerequisite($this->ids['B'], $this->ids['A']);
        $this->seedUsers(600);

        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => 'AY 2026 - 2027 · 1st Semester',
            'program'       => 'BS Test Program',
            'year_level'    => '1st Year',
            'section'       => 'A',
            'capacity'      => null,
        ]);
        $section = $sections->find($sections->getInsertID());

        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => $section['academic_year'],
            'program'       => $section['program'],
            'year_level'    => $section['year_level'],
            'section'       => $section['section'],
            'day'           => 0,
            'start_time'    => '08:00:00',
            'end_time'      => '09:30:00',
            'time_label'    => '08:00-09:30',
            'subject_code'  => 'PR-B',
            'subject_title' => 'Subject B',
            'room'          => 'Room 1',
            'theme'         => 'blue',
            'status'        => 'published',
        ]);

        $result = (new EnrollmentModel())->enrollOne(600, (int) $section['section_id'], 'PR-B', 'manual');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Prerequisite not met for PR-B: PR-A', $result['error']);
    }
}
