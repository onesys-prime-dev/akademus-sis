<?php

use App\Models\CourseOfferingModel;
use App\Models\EnrollmentModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Section Merging — the scenario from the SIS development roadmap: two
 * sections sharing one subject get merged into a single class instance so
 * capacity is enforced against the combined pool, not each section alone.
 *
 * @internal
 */
final class CourseOfferingModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    // Sections are year-scoped (no semester of their own); plotted_schedules/
    // course_offerings/enrollments keep the full term string, matched
    // against EnrollmentModel::enrollOne()'s own AcademicYearModel::
    // activeFormatted() lookup — needs a real active row or every call
    // fails before reaching any of the merge/capacity behavior under test.
    private const AY         = 'AY 2026 - 2027 · 1st Semester';
    private const SECTION_AY = 'AY 2026 - 2027';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedUsers(100, 101, 102, 200, 201, 202);
        db_connect()->table('academic_years')->insert(['label' => '2026-2027', 'semester' => '1st', 'is_active' => 1]);
    }

    private function makeSection(string $name, ?int $capacity = null): array
    {
        $sections = new SectionModel();

        $sections->insert([
            'academic_year' => self::SECTION_AY,
            'program'       => 'BS Information Technology',
            'year_level'    => '1st Year',
            'section'       => $name,
            'capacity'      => $capacity,
        ]);

        return $sections->find($sections->getInsertID());
    }

    private function seedSubject(string $subjectCode): void
    {
        $subjects = db_connect()->table('subjects');

        if ($subjects->where('subject_code', $subjectCode)->countAllResults() > 0) {
            return;
        }

        $subjects->insert([
            'subject_code' => $subjectCode,
            'subject_name' => $subjectCode . ' Title',
        ]);
    }

    private function plotSubject(array $section, string $subjectCode): void
    {
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::AY,
            'program'       => $section['program'],
            'year_level'    => $section['year_level'],
            'section'       => $section['section'],
            'day'           => 0,
            'start_time'    => '08:00:00',
            'end_time'      => '10:00:00',
            'time_label'    => '08:00-10:00',
            'subject_code'  => $subjectCode,
            'subject_title' => $subjectCode . ' Title',
            'room'          => 'Room 101',
            'theme'         => 'blue',
            'status'        => 'published',
        ]);
    }

    public function testMergedClassEnforcesCombinedCapacityAcrossBothSections(): void
    {
        $sectionA = $this->makeSection('A', 5); // each section's own capacity is generous —
        $sectionB = $this->makeSection('B', 5); // it's the MERGED cap of 2 that must bind.
        $this->seedSubject('GE-ELEC1');
        $this->plotSubject($sectionA, 'GE-ELEC1');
        $this->plotSubject($sectionB, 'GE-ELEC1');

        $enrollments = new EnrollmentModel();
        $offerings   = new CourseOfferingModel();

        // Before merging: two independent rosters, each governed by its own
        // section capacity — proves the pre-merge baseline is normal.
        $preA = $enrollments->enrollOne(100, (int) $sectionA['section_id'], 'GE-ELEC1', 'manual');
        $preB = $enrollments->enrollOne(101, (int) $sectionB['section_id'], 'GE-ELEC1', 'manual');
        $this->assertTrue($preA['ok']);
        $this->assertTrue($preB['ok']);

        // Merge with a combined capacity of 2 — already exactly met by the
        // two students enrolled above (backfilled onto the new offering).
        $merge = $offerings->createMerge(self::AY, 'GE-ELEC1', [
            $sectionA['section_id'],
            $sectionB['section_id'],
        ], 2);

        $this->assertTrue($merge['ok'], $merge['error'] ?? '');
        $this->assertSame(2, $merge['roster_count']);
        $this->assertFalse($merge['over_capacity']);

        // The two pre-merge enrollments were backfilled onto the offering.
        $this->seeInDatabase('enrollments', ['user_id' => 100, 'offering_id' => $merge['offering_id']]);
        $this->seeInDatabase('enrollments', ['user_id' => 101, 'offering_id' => $merge['offering_id']]);

        // A THIRD student, enrolling through EITHER section, is rejected —
        // the merged pool (2) is already full, even though each section's
        // own capacity (5) would have allowed it.
        $thirdViaA = $enrollments->enrollOne(102, (int) $sectionA['section_id'], 'GE-ELEC1', 'manual');
        $this->assertFalse($thirdViaA['ok']);
        $this->assertSame('This merged class is full.', $thirdViaA['error']);

        $thirdViaB = $enrollments->enrollOne(102, (int) $sectionB['section_id'], 'GE-ELEC1', 'manual');
        $this->assertFalse($thirdViaB['ok']);
        $this->assertSame('This merged class is full.', $thirdViaB['error']);

        $this->assertSame(2, $offerings->rosterCount($merge['offering_id']));
    }

    public function testUnmergingASectionRevertsItToItsOwnCapacity(): void
    {
        $sectionA = $this->makeSection('A', 5);
        $sectionB = $this->makeSection('B', 1); // deliberately tiny once standalone again
        $this->seedSubject('GE-ELEC1');
        $this->plotSubject($sectionA, 'GE-ELEC1');
        $this->plotSubject($sectionB, 'GE-ELEC1');

        $enrollments = new EnrollmentModel();
        $offerings   = new CourseOfferingModel();

        $merge = $offerings->createMerge(self::AY, 'GE-ELEC1', [
            $sectionA['section_id'],
            $sectionB['section_id'],
        ], null); // unlimited merged capacity

        $this->assertTrue($merge['ok']);

        $enrollments->enrollOne(200, (int) $sectionB['section_id'], 'GE-ELEC1', 'manual');

        $unmerge = $offerings->unmergeSection($merge['offering_id'], (int) $sectionB['section_id']);
        $this->assertTrue($unmerge['ok']);

        // Section B's existing enrollment is untagged, not deleted.
        $this->seeInDatabase('enrollments', ['user_id' => 200, 'section_id' => $sectionB['section_id'], 'offering_id' => null]);

        // Section B is back to its own (tiny) capacity of 1, already met.
        $blocked = $enrollments->enrollOne(201, (int) $sectionB['section_id'], 'GE-ELEC1', 'manual');
        $this->assertFalse($blocked['ok']);
        $this->assertSame('This section is full.', $blocked['error']);

        // Section A, still merged (alone now) with unlimited capacity,
        // accepts more students unaffected by B's unmerge.
        $stillOk = $enrollments->enrollOne(202, (int) $sectionA['section_id'], 'GE-ELEC1', 'manual');
        $this->assertTrue($stillOk['ok'], $stillOk['error'] ?? '');
    }

    public function testCannotMergeASectionAlreadyMergedForTheSameSubject(): void
    {
        $sectionA = $this->makeSection('A');
        $sectionB = $this->makeSection('B');
        $sectionC = $this->makeSection('C');
        $this->seedSubject('GE-ELEC1');
        $this->plotSubject($sectionA, 'GE-ELEC1');
        $this->plotSubject($sectionB, 'GE-ELEC1');
        $this->plotSubject($sectionC, 'GE-ELEC1');

        $offerings = new CourseOfferingModel();

        $first = $offerings->createMerge(self::AY, 'GE-ELEC1', [$sectionA['section_id'], $sectionB['section_id']], null);
        $this->assertTrue($first['ok']);

        // Section A is already in an offering for GE-ELEC1 — merging it into
        // a second, different offering for the same subject must be refused.
        $second = $offerings->createMerge(self::AY, 'GE-ELEC1', [$sectionA['section_id'], $sectionC['section_id']], null);
        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('already merged', $second['error']);
    }

    /** @return array{offering_id: int} */
    private function makeUnattachedOfferingWithSchedule(string $sectionName, string $subjectCode): array
    {
        $section = $this->makeSection($sectionName);
        $this->seedSubject($subjectCode);
        $this->plotSubject($section, $subjectCode);

        $offerings = new CourseOfferingModel();
        $merge     = $offerings->createMerge(self::AY, $subjectCode, [$section['section_id']], null, 1);
        $this->assertTrue($merge['ok'], $merge['error'] ?? '');

        // An offering-linked schedule — the state a real "merge carrying a
        // schedule" or a synced offering component leaves behind, with no
        // section/year_level/program of its own (see PlottedScheduleModel's
        // docblock) — distinct from the section-plotted row plotSubject()
        // already inserted above.
        db_connect()->table('plotted_schedules')->insert([
            'offering_id'   => $merge['offering_id'],
            'academic_year' => self::AY,
            'day'           => 1,
            'start_time'    => '10:00:00',
            'end_time'      => '11:30:00',
            'time_label'    => '10:00-11:30',
            'subject_code'  => $subjectCode,
            'subject_title' => $subjectCode . ' Title',
            'room'          => 'Room 202',
            'theme'         => 'blue',
            'status'        => 'published',
        ]);

        $unmerge = $offerings->unmergeSection($merge['offering_id'], (int) $section['section_id']);
        $this->assertTrue($unmerge['ok']);

        // Confirms unmergeSection() left it behind rather than deleting it —
        // the precondition this whole test fixture exists to set up.
        $this->seeInDatabase('course_offerings', ['offering_id' => $merge['offering_id']]);

        return ['offering_id' => (int) $merge['offering_id']];
    }

    public function testDeleteIfUnattachedRemovesAnOrphanedOfferingAndItsSchedule(): void
    {
        $fixture    = $this->makeUnattachedOfferingWithSchedule('D', 'GE-ELEC2');
        $offerings  = new CourseOfferingModel();

        $result = $offerings->deleteIfUnattached($fixture['offering_id']);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->dontSeeInDatabase('course_offerings', ['offering_id' => $fixture['offering_id']]);
        $this->dontSeeInDatabase('plotted_schedules', ['offering_id' => $fixture['offering_id']]);
    }

    public function testDeleteIfUnattachedRefusesAnOfferingThatHasBeenReattachedSince(): void
    {
        $fixture   = $this->makeUnattachedOfferingWithSchedule('E', 'GE-ELEC3');
        $offerings = new CourseOfferingModel();

        // Something reattached a section to it between page load and click.
        $another = $this->makeSection('F');
        db_connect()->table('offering_sections')->insert([
            'offering_id'  => $fixture['offering_id'],
            'section_id'   => $another['section_id'],
            'subject_code' => 'GE-ELEC3',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $result = $offerings->deleteIfUnattached($fixture['offering_id']);

        $this->assertFalse($result['ok']);
        $this->seeInDatabase('course_offerings', ['offering_id' => $fixture['offering_id']]);
        $this->seeInDatabase('plotted_schedules', ['offering_id' => $fixture['offering_id']]);
    }

    public function testDeleteIfUnattachedRejectsAnUnknownOffering(): void
    {
        $result = (new CourseOfferingModel())->deleteIfUnattached(999999);

        $this->assertFalse($result['ok']);
        $this->assertSame('Offering not found.', $result['error']);
    }
}
