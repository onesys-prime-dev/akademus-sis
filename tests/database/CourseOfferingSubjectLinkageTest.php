<?php

use App\Database\Migrations\BackfillCourseOfferingsSubjectId;
use App\Libraries\SubjectCodeResolver;
use App\Models\CourseOfferingModel;
use App\Models\SectionModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Step 2 of the shared-subject architecture work: course_offerings.subject_id
 * as the canonical link to the subject catalog, so ownership can be derived
 * (offering -> subject -> sub_department) instead of depending on the
 * denormalized subject_code string alone.
 *
 * @internal
 */
final class CourseOfferingSubjectLinkageTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private function seedSubject(string $subjectCode, ?int $subDeptId = null): int
    {
        $subjects = db_connect()->table('subjects');

        $subjects->insert([
            'subject_code'      => $subjectCode,
            'subject_name'      => $subjectCode . ' Title',
            'sub_department_id' => $subDeptId,
        ]);

        return (int) db_connect()->insertID();
    }

    private function seedSection(string $program, string $name): array
    {
        $sections = new SectionModel();
        $sections->insert([
            'academic_year' => 'AY2026-2027',
            'program'       => $program,
            'year_level'    => '1st Year',
            'section'       => $name,
        ]);

        return $sections->find($sections->getInsertID());
    }

    // 1. Existing (legacy) offering with a valid subject code gets correctly
    // linked to the matching subject_id by the backfill migration.
    public function testBackfillLinksLegacyOfferingToMatchingSubject(): void
    {
        $subjectId = $this->seedSubject('LEGACY101');

        db_connect()->table('course_offerings')->insert([
            'academic_year' => 'AY2026-2027',
            'subject_code'  => 'LEGACY101',
            'subject_id'    => null,
        ]);
        $offeringId = (int) db_connect()->insertID();

        (new BackfillCourseOfferingsSubjectId())->up();

        $this->seeInDatabase('course_offerings', ['offering_id' => $offeringId, 'subject_id' => $subjectId]);
    }

    // 1b. The backfill never fails and never guesses for an unmatched
    // legacy subject_code — it leaves subject_id null instead.
    public function testBackfillLeavesUnmatchedLegacyOfferingNull(): void
    {
        db_connect()->table('course_offerings')->insert([
            'academic_year' => 'AY2026-2027',
            'subject_code'  => 'NO-SUCH-SUBJECT',
            'subject_id'    => null,
        ]);
        $offeringId = (int) db_connect()->insertID();

        (new BackfillCourseOfferingsSubjectId())->up();

        $this->seeInDatabase('course_offerings', ['offering_id' => $offeringId, 'subject_id' => null]);
    }

    // 2. A new offering created via createMerge() saves a valid subject_id.
    public function testCreateMergeSavesCanonicalSubjectId(): void
    {
        $subjectId = $this->seedSubject('SHARED201');
        $sectionA  = $this->seedSection('BSIT', 'A');
        $sectionB  = $this->seedSection('BSIT', 'B');

        $result = (new CourseOfferingModel())->createMerge(
            'AY2026-2027',
            'SHARED201',
            [$sectionA['section_id'], $sectionB['section_id']],
            null
        );

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('course_offerings', ['offering_id' => $result['offering_id'], 'subject_id' => $subjectId]);
    }

    // 2b. A new offering for a subject code that doesn't exist in the
    // catalog is rejected outright, never silently created.
    public function testCreateMergeRejectsUnknownSubjectCode(): void
    {
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');

        $result = (new CourseOfferingModel())->createMerge(
            'AY2026-2027',
            'DOES-NOT-EXIST',
            [$sectionA['section_id'], $sectionB['section_id']],
            null
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No subject found', $result['error']);
        $this->dontSeeInDatabase('course_offerings', ['subject_code' => 'DOES-NOT-EXIST']);
    }

    // 3. A nonexistent subject_id is rejected by the FK constraint.
    public function testInvalidSubjectIdIsRejectedByForeignKey(): void
    {
        $this->expectException(DatabaseException::class);

        db_connect()->table('course_offerings')->insert([
            'academic_year' => 'AY2026-2027',
            'subject_code'  => 'WHATEVER',
            'subject_id'    => 999999,
        ]);
    }

    // 4. Existing subject_code-based reads still work after the change.
    public function testSubjectCodeBasedReadsStillWork(): void
    {
        $this->seedSubject('SHARED202');
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BSIT', 'B');

        $offerings = new CourseOfferingModel();
        $result    = $offerings->createMerge('AY2026-2027', 'SHARED202', [$sectionA['section_id'], $sectionB['section_id']], null);

        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $found = $offerings->offeringForSection((int) $sectionA['section_id'], 'SHARED202');
        $this->assertNotNull($found);
        $this->assertSame($result['offering_id'], (int) $found['offering_id']);
    }

    // 5. Ambiguous matches are never silently resolved to a guess.
    // subjects.subject_code is DB-uniquely constrained, so this can't occur
    // via real data — tested at the resolver-logic level instead, which is
    // what both the migration and createMerge() delegate the decision to.
    public function testResolverNeverGuessesOnAmbiguousMatches(): void
    {
        $ambiguous = SubjectCodeResolver::resolve([
            ['subject_id' => 1],
            ['subject_id' => 2],
        ]);
        $this->assertSame(SubjectCodeResolver::AMBIGUOUS, $ambiguous['status']);
        $this->assertNull($ambiguous['subject_id']);

        $unmatched = SubjectCodeResolver::resolve([]);
        $this->assertSame(SubjectCodeResolver::UNMATCHED, $unmatched['status']);
        $this->assertNull($unmatched['subject_id']);

        $matched = SubjectCodeResolver::resolve([['subject_id' => 7]]);
        $this->assertSame(SubjectCodeResolver::MATCHED, $matched['status']);
        $this->assertSame(7, $matched['subject_id']);
    }

    // 6. Ownership can be derived: offering -> subject -> sub_department.
    public function testOwningSubDeptIdIsDerivedThroughSubject(): void
    {
        db_connect()->table('departments')->insert(['dept_id' => 1, 'code' => 'COL-A', 'name' => 'College A']);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => 1, 'college_id' => 1, 'code' => 'RELED', 'name' => 'Religious Education']);

        $this->seedSubject('RED104', 1);
        $sectionA = $this->seedSection('BSIT', 'A');
        $sectionB = $this->seedSection('BMUS', 'A');

        $offerings = new CourseOfferingModel();
        $result    = $offerings->createMerge('AY2026-2027', 'RED104', [$sectionA['section_id'], $sectionB['section_id']], null);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertSame(1, $offerings->owningSubDeptId((int) $result['offering_id']));
    }

    // 7. A cross-program shared offering remains program-agnostic: two
    // sections from different programs can merge into one offering, and
    // nothing about the offering/subject link introduces a program_id or
    // department_id dependency.
    public function testCrossProgramMergedOfferingStaysProgramAgnostic(): void
    {
        $this->seedSubject('SHARED203');
        $itSection    = $this->seedSection('BSIT', 'A');
        $musicSection = $this->seedSection('BMUS', 'A');

        $offerings = new CourseOfferingModel();
        $result    = $offerings->createMerge('AY2026-2027', 'SHARED203', [$itSection['section_id'], $musicSection['section_id']], null);

        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $offering = $offerings->find($result['offering_id']);
        $this->assertArrayNotHasKey('program_id', $offering);
        $this->assertArrayNotHasKey('department_id', $offering);

        $sectionIds = $offerings->sectionIdsFor((int) $result['offering_id']);
        sort($sectionIds);
        $expected = [(int) $itSection['section_id'], (int) $musicSection['section_id']];
        sort($expected);
        $this->assertSame($expected, $sectionIds);
    }
}
