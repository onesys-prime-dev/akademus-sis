<?php

use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * HTTP-level tests for the chairperson's merge-suggestion flow
 * (OfferingController::mergeSuggestions and, in later steps, the accept
 * endpoints that consume its response).
 *
 * @internal
 */
final class OfferingControllerMergeAcceptTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private const TERM = '1 · 1st Semester';

    private int $nextUserId = 60000;
    private int $deptSeq    = 1;
    private int $programSeq = 1;

    private function seedChairperson(?int $subDeptId = null): int
    {
        if ($subDeptId === null) {
            $collegeId = $this->deptSeq++;
            $subDeptId = $this->deptSeq++;
            db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
            db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => 'Unit ' . $subDeptId]);
        }

        $userId = $this->nextUserId++;
        $this->seedUsers($userId);
        db_connect()->table('users')->where('id', $userId)->update(['sub_department_id' => $subDeptId]);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $userId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        return $userId;
    }

    private function newSubDept(): int
    {
        $collegeId = $this->deptSeq++;
        $subDeptId = $this->deptSeq++;
        db_connect()->table('departments')->insert(['dept_id' => $collegeId, 'code' => 'COL' . $collegeId, 'name' => 'College ' . $collegeId]);
        db_connect()->table('sub_departments')->insert(['sub_dept_id' => $subDeptId, 'college_id' => $collegeId, 'code' => 'SD' . $subDeptId, 'name' => 'Unit ' . $subDeptId]);

        return $subDeptId;
    }

    private function seedSubject(string $code, ?int $subDeptId): int
    {
        db_connect()->table('subjects')->insert(['subject_code' => $code, 'subject_name' => $code . ' Title', 'sub_department_id' => $subDeptId]);

        return (int) db_connect()->insertID();
    }

    private function seedProgramForSubDept(string $name, int $subDeptId): void
    {
        $id = $this->programSeq++;
        db_connect()->table('programs')->insert([
            'program_id' => 2000 + $id, 'sub_department_id' => $subDeptId, 'program_code' => 'P' . (2000 + $id), 'program_name' => $name, 'is_active' => 1,
        ]);
    }

    /** Sections are keyed by the year-only part of the term (see AcademicYearModel::yearOnly()), unlike plotted rows. */
    private function seedSection(string $program, string $label, string $academicYear = '1'): int
    {
        $sections = new SectionModel();
        $sections->insert(['academic_year' => $academicYear, 'program' => $program, 'year_level' => '2nd Year', 'section' => $label]);

        return (int) $sections->getInsertID();
    }

    public function testMergeSuggestionsIncludesOfferingOrTargetSectionId(): void
    {
        $subDept = $this->newSubDept();
        $chair   = $this->seedChairperson($subDept);
        $this->seedProgramForSubDept('BSIT', $subDept);
        $this->seedSubject('ITE101', $subDept);
        $sectionA = $this->seedSection('BSIT', 'A');
        $this->seedSection('BSIT', 'B');
        db_connect()->table('rooms')->insert(['name' => 'Room 201', 'capacity' => 40, 'type' => 'Lecture', 'floor' => '2nd Floor', 'is_active' => 1]);
        // plot ITE101 for section A only (legacy row, no offering) — mirrors the live bug scenario
        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'A',
            'day' => 0, 'start_time' => '08:00:00', 'end_time' => '09:30:00', 'time_label' => '08:00-09:30',
            'subject_code' => 'ITE101', 'subject_title' => 'ITE101 Title', 'room' => 'Room 201', 'status' => 'published',
        ]);

        $this->actingAs(auth()->getProvider()->findById($chair));
        $result = $this->withSession()->get('/chairperson/offerings/merge-suggestions?' . http_build_query([
            'academic_year' => self::TERM, 'program' => 'BSIT', 'year_level' => '2nd Year', 'section' => 'B',
        ]));

        $body = json_decode($result->response()->getJSON(), true);
        $this->assertTrue($body['ok']);
        $this->assertNotEmpty($body['suggestions']);
        $this->assertNull($body['suggestions'][0]['offering_id']);
        $this->assertSame($sectionA, $body['suggestions'][0]['target_section_id']);
    }
}
