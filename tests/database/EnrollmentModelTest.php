<?php

use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * @internal
 */
final class EnrollmentModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUsers(1, 2, 10, 11, 20, 30, 40);
    }

    private function makeSection(array $overrides = []): array
    {
        $sections = new SectionModel();

        $sections->insert(array_merge([
            'academic_year' => 'AY2026-2027',
            'program'       => 'BS Information Technology',
            'year_level'    => '1st Year',
            'section'       => 'A',
            'capacity'      => null,
        ], $overrides));

        return $sections->find($sections->getInsertID());
    }

    private function plotSubject(array $section, string $subjectCode, int $day, string $start, string $end): void
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
            'room'          => 'Room 101',
            'theme'         => 'blue',
            'status'        => 'published',
        ]);
    }

    public function testPerSubjectEnrollmentSucceeds(): void
    {
        $section = $this->makeSection();
        $this->plotSubject($section, 'IT101', 0, '08:00:00', '10:00:00');

        $result = (new EnrollmentModel())->enrollOne(1, (int) $section['section_id'], 'IT101', 'manual');

        $this->assertTrue($result['ok']);
        $this->seeInDatabase('enrollments', [
            'user_id'      => 1,
            'section_id'   => $section['section_id'],
            'subject_code' => 'IT101',
            'method'       => 'manual',
            'status'       => 'enrolled',
        ]);
    }

    public function testBlockEnrollmentProducesOneRowPerPlottedSubject(): void
    {
        $section = $this->makeSection();
        $this->plotSubject($section, 'IT101', 0, '08:00:00', '10:00:00');
        $this->plotSubject($section, 'IT102', 1, '10:00:00', '12:00:00');
        $this->plotSubject($section, 'IT103', 2, '13:00:00', '15:00:00');

        $enrollments = new EnrollmentModel();
        $subjects    = $enrollments->subjectsFor($section);

        $this->assertSame(['IT101', 'IT102', 'IT103'], $subjects);

        foreach ($subjects as $subjectCode) {
            $result = $enrollments->enrollOne(2, (int) $section['section_id'], $subjectCode, 'block');
            $this->assertTrue($result['ok'], $result['error'] ?? '');
        }

        $this->assertSame(
            3,
            db_connect()->table('enrollments')->where('user_id', 2)->where('section_id', $section['section_id'])->countAllResults()
        );
    }

    public function testEnrollmentIsRejectedOnceSectionIsAtCapacity(): void
    {
        $section = $this->makeSection(['capacity' => 1]);
        $this->plotSubject($section, 'IT101', 0, '08:00:00', '10:00:00');

        $enrollments = new EnrollmentModel();

        // First student takes the only seat.
        $first = $enrollments->enrollOne(10, (int) $section['section_id'], 'IT101', 'manual');
        $this->assertTrue($first['ok']);

        // A second, different student is rejected — the section is full.
        $second = $enrollments->enrollOne(11, (int) $section['section_id'], 'IT101', 'manual');

        $this->assertFalse($second['ok']);
        $this->assertSame('This section is full.', $second['error']);
        $this->assertSame(
            1,
            db_connect()->table('enrollments')->where('section_id', $section['section_id'])->countAllResults()
        );
    }

    public function testSameStudentEnrollingAnotherSubjectInAFullSectionIsNotBlocked(): void
    {
        // Capacity caps distinct STUDENTS, not subject rows — a student
        // already counted against the cap can still add another subject
        // under the same section.
        $section = $this->makeSection(['capacity' => 1]);
        $this->plotSubject($section, 'IT101', 0, '08:00:00', '10:00:00');
        $this->plotSubject($section, 'IT102', 1, '10:00:00', '12:00:00');

        $enrollments = new EnrollmentModel();

        $first  = $enrollments->enrollOne(20, (int) $section['section_id'], 'IT101', 'block');
        $second = $enrollments->enrollOne(20, (int) $section['section_id'], 'IT102', 'block');

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
    }

    public function testTimeConflictAcrossDifferentSectionsIsRejected(): void
    {
        $sectionA = $this->makeSection(['section' => 'A']);
        $sectionB = $this->makeSection(['section' => 'B']);

        $this->plotSubject($sectionA, 'IT101', 0, '08:00:00', '10:00:00');
        // Different section, same day, overlapping time.
        $this->plotSubject($sectionB, 'IT201', 0, '09:00:00', '11:00:00');

        $enrollments = new EnrollmentModel();

        $first = $enrollments->enrollOne(30, (int) $sectionA['section_id'], 'IT101', 'manual');
        $this->assertTrue($first['ok']);

        $second = $enrollments->enrollOne(30, (int) $sectionB['section_id'], 'IT201', 'manual');

        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('Schedule conflict', $second['error']);
        $this->assertSame(
            1,
            db_connect()->table('enrollments')->where('user_id', 30)->countAllResults()
        );
    }

    public function testDoubleEnrollmentInTheSameSubjectIsRejected(): void
    {
        $section = $this->makeSection();
        $this->plotSubject($section, 'IT101', 0, '08:00:00', '10:00:00');

        $enrollments = new EnrollmentModel();

        $first  = $enrollments->enrollOne(40, (int) $section['section_id'], 'IT101', 'manual');
        $second = $enrollments->enrollOne(40, (int) $section['section_id'], 'IT101', 'manual');

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok']);
        $this->assertSame(
            1,
            db_connect()->table('enrollments')->where('user_id', 40)->countAllResults()
        );
    }
}
