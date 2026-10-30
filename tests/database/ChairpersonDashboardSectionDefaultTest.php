<?php

use App\Models\AcademicYearModel;
use App\Models\CurriculumModel;
use App\Models\SectionModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Live bug: loading Schedule Plotting for a BORROWING program with no
 * explicit year_level/section in the URL (the normal case — the sidebar
 * link, or a fresh login) embedded the WRONG section into PLOTTING_BOOT.context
 * when that program has sections under more than one year level (e.g. "A"
 * under 1st Year, "B" under 2nd Year). ChairpersonController::index() smart-
 * jumps $context['year_level'] to wherever the chairperson's owned subject is
 * actually needed (2nd Year, say), but sectionOptions() — called earlier,
 * from the raw query string, before that jump happens — had no year_level to
 * scope to and fell back to every section across every year level of that
 * program, defaulting to the alphabetically-first one ("A", from a
 * DIFFERENT year level) instead of the one that actually belongs to the
 * year level the page landed on.
 *
 * The embedded context is also what every subsequent save
 * (ChairpersonController::sync()) is built from client-side, so this wasn't
 * just a display bug: it made every save on a freshly-loaded page fail with
 * "Invalid or missing context: section", because sync()'s own (correct,
 * precisely-scoped) section check never accepted the leaked section.
 *
 * @internal
 */
final class ChairpersonDashboardSectionDefaultTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testFreshLoadOfABorrowedProgramDoesNotLeakAnotherYearLevelsSection(): void
    {
        $db = db_connect();
        $db->table('departments')->insert(['dept_id' => 1, 'code' => 'COL1', 'name' => 'College 1']);
        $db->table('sub_departments')->insert(['sub_dept_id' => 2, 'college_id' => 1, 'code' => 'SD1', 'name' => 'Owning Unit']);

        $chairId = 70401;
        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => 2]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);

        // Owning unit needs no program of its own for this scenario — only
        // that it owns a subject the borrowing program's 2nd Year curriculum
        // requires.
        $db->table('programs')->insert(['program_id' => 4101, 'sub_department_id' => 2, 'program_code' => 'P4101', 'program_name' => 'Owning Program', 'is_active' => 1]);
        $db->table('programs')->insert(['program_id' => 4102, 'sub_department_id' => 99, 'program_code' => 'P4102', 'program_name' => 'Borrowing Program', 'is_active' => 1]);

        $db->table('subjects')->insert(['subject_code' => 'GEC101', 'subject_name' => 'GEC101 Title', 'sub_department_id' => 2]);
        $subjectId = (int) $db->insertID();

        $term = AcademicYearModel::format('1', '1st');
        $db->table('academic_years')->insert(['label' => '1', 'semester' => '1st', 'is_active' => 1]);

        $curriculum = (new CurriculumModel())->liveFor(4102);
        $db->table('curriculum_subjects')->insert([
            'curriculum_id' => $curriculum['curriculum_id'], 'subject_id' => $subjectId,
            'year_level' => 2, 'semester' => 1, 'is_active' => 1,
        ]);

        $sectionYear = AcademicYearModel::yearOnly($term);
        $sections = new SectionModel();
        // Section "A" under 1st Year — alphabetically first, and has
        // NOTHING to do with the subject being plotted.
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Borrowing Program', 'year_level' => '1st Year', 'section' => 'A']);
        // Section "B" under 2nd Year — the one that actually needs GEC101.
        $sections->insert(['academic_year' => $sectionYear, 'program' => 'Borrowing Program', 'year_level' => '2nd Year', 'section' => 'B']);

        $this->actingAs(auth()->getProvider()->findById($chairId));

        // No year_level, no section in the query string — exactly a fresh
        // sidebar click or first login, letting the server's own smart
        // defaults (including the borrowed-subject year-level jump) decide.
        $result = $this->withSession()->get('/chairperson/dashboard?program=' . urlencode('Borrowing Program'));
        $result->assertStatus(200);

        $html = $result->response()->getBody();
        $this->assertStringContainsString('"year_level":"2nd Year"', $html, 'Expected the borrowed-subject smart jump to land on 2nd Year.');
        $this->assertStringContainsString('"section":"B"', $html, '1st Year\'s "A" leaked into the embedded context instead of 2nd Year\'s own "B".');
        $this->assertStringNotContainsString('"section":"A"', $html);
    }
}
