<?php

use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Add/drop: dropping keeps the row as history, frees the seat, is logged,
 * is owner-scoped, is refused once a grade exists, and re-enrolling
 * reactivates the same row instead of tripping the unique key.
 *
 * @internal
 */
final class EnrollmentDropTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUsers(700, 701, 702);
    }

    /** Long realistic AY strings would break the next refresh's rollback — see TermCurriculumTest. */
    protected function tearDown(): void
    {
        $db = db_connect();
        $db->disableForeignKeyChecks();

        foreach (['grades', 'enrollment_logs', 'enrollments', 'plotted_schedules', 'sections'] as $table) {
            $db->table($table)->truncate();
        }

        $db->enableForeignKeyChecks();

        parent::tearDown();
    }

    /**
     * EnrollmentModel::enrollOne() now requires subject_code to resolve to
     * a real, active subjects row (see the catalog-integrity hardening) —
     * this fixture previously plotted DR101 with no matching catalog entry.
     */
    private function seedSubject(string $code): void
    {
        if (db_connect()->table('subjects')->where('subject_code', $code)->countAllResults() > 0) {
            return;
        }

        db_connect()->table('subjects')->insert([
            'subject_code' => $code,
            'subject_name' => $code . ' Title',
            'units'        => 3,
            'is_active'    => 1,
        ]);
    }

    private function makeSection(?int $capacity = null): array
    {
        $this->seedSubject('DR101');

        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => 'AY 2026 - 2027 · 1st Semester',
            'program'       => 'BS Test Program',
            'year_level'    => '1st Year',
            'section'       => 'A',
            'capacity'      => $capacity,
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
            'subject_code'  => 'DR101',
            'subject_title' => 'Drop 101',
            'room'          => 'Room 1',
            'theme'         => 'blue',
            'status'        => 'published',
        ]);

        return $section;
    }

    private function enroll(int $user, array $section): array
    {
        return (new EnrollmentModel())->enrollOne($user, (int) $section['section_id'], 'DR101', 'manual');
    }

    public function testDropKeepsTheRowAsHistoryAndLogsIt(): void
    {
        $section = $this->makeSection();
        $id      = $this->enroll(700, $section)['enrollment_id'];

        $result = (new EnrollmentModel())->drop($id, 'Changed my mind', 700, 700);

        $this->assertTrue($result['ok']);
        $this->seeInDatabase('enrollments', ['enrollment_id' => $id, 'status' => 'dropped']);
        $this->seeInDatabase('enrollment_logs', [
            'enrollment_id' => $id,
            'from_status'   => 'enrolled',
            'to_status'     => 'dropped',
            'reason'        => 'Changed my mind',
            'changed_by'    => 700,
        ]);
    }

    public function testDroppingFreesTheSeat(): void
    {
        $section = $this->makeSection(1);

        $first = $this->enroll(700, $section);
        $this->assertTrue($first['ok']);
        $this->assertFalse($this->enroll(701, $section)['ok'], 'section should be full');

        (new EnrollmentModel())->drop($first['enrollment_id'], 'Leaving', 700, 700);

        $this->assertTrue($this->enroll(701, $section)['ok'], 'the freed seat should be available');
    }

    public function testAStudentCannotDropSomeoneElsesEnrollment(): void
    {
        $section = $this->makeSection();
        $id      = $this->enroll(700, $section)['enrollment_id'];

        $result = (new EnrollmentModel())->drop($id, 'Not mine', 701, 701);

        $this->assertFalse($result['ok']);
        $this->assertSame('Enrollment not found.', $result['error']);
        $this->seeInDatabase('enrollments', ['enrollment_id' => $id, 'status' => 'enrolled']);
    }

    public function testAnAlreadyDroppedEnrollmentCannotBeDroppedAgain(): void
    {
        $section = $this->makeSection();
        $id      = $this->enroll(700, $section)['enrollment_id'];
        $model   = new EnrollmentModel();

        $model->drop($id, 'First drop', 700, 700);
        $again = $model->drop($id, 'Second drop', 700, 700);

        $this->assertFalse($again['ok']);
        $this->assertStringContainsString('already dropped', $again['error']);
    }

    public function testAnEnrollmentWithAGradeCannotBeDropped(): void
    {
        $section = $this->makeSection();
        $id      = $this->enroll(700, $section)['enrollment_id'];

        // The grading-scale migration seeds a default scale (id 1); reuse it.
        $scaleId = (int) db_connect()->table('grading_scales')->select('scale_id')->get()->getRowArray()['scale_id'];

        db_connect()->table('grades')->insert([
            'enrollment_id'    => $id,
            'user_id'          => 700,
            'subject_code'     => 'DR101',
            'section_id'       => $section['section_id'],
            'academic_year'    => $section['academic_year'],
            'faculty_id'       => 701,
            'grading_scale_id' => $scaleId,
            'grade_value'      => '1.50',
            'status'           => 'draft',
        ]);

        $result = (new EnrollmentModel())->drop($id, 'Too late', 700, 700);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('grade has already been recorded', $result['error']);
        $this->seeInDatabase('enrollments', ['enrollment_id' => $id, 'status' => 'enrolled']);
    }

    public function testReEnrollingAfterADropReactivatesTheSameRow(): void
    {
        $section = $this->makeSection();
        $first   = $this->enroll(700, $section);
        (new EnrollmentModel())->drop($first['enrollment_id'], 'Oops', 700, 700);

        $second = $this->enroll(700, $section);

        $this->assertTrue($second['ok'], $second['error'] ?? '');
        $this->assertSame($first['enrollment_id'], $second['enrollment_id'], 'same row, no duplicate');
        $this->seeInDatabase('enrollments', ['enrollment_id' => $first['enrollment_id'], 'status' => 'enrolled']);
        $this->assertSame(1, db_connect()->table('enrollments')->where('user_id', 700)->countAllResults());
        $this->seeInDatabase('enrollment_logs', ['enrollment_id' => $first['enrollment_id'], 'from_status' => 'dropped', 'to_status' => 'enrolled']);
    }

    public function testDoubleEnrollingWhileStillEnrolledIsStillRejected(): void
    {
        $section = $this->makeSection();
        $this->enroll(700, $section);

        $again = $this->enroll(700, $section);

        $this->assertFalse($again['ok']);
        $this->assertStringContainsString('Already enrolled', $again['error']);
    }
}
