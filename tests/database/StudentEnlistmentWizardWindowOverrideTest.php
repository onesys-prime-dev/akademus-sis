<?php

use App\Models\AcademicYearModel;
use App\Models\EnlistmentWindowOverrideModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * HTTP-level coverage for StudentEnlistment::wizard()'s display-time window
 * gate: a granted EnlistmentWindowOverride must hide the "not yet open"
 * splash even though the global window is closed.
 *
 * @internal
 */
final class StudentEnlistmentWizardWindowOverrideTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private int $studentUserId = 920;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers($this->studentUserId);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $this->studentUserId, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);
        db_connect()->table('students')->insert([
            'user_id' => $this->studentUserId, 'applicant_id' => 'A920', 'student_number' => 'S920', 'student_status' => 'active',
        ]);
        $this->studentId = (int) db_connect()->insertID();

        // Upcoming term, window left closed (null opens/closes_at).
        // start_date must be in the future: AcademicYearModel::upcomingTerm()
        // is date-driven (`start_date > today`), not just `is_active = 0`.
        db_connect()->table('academic_years')->insert([
            'label' => '2026-2027', 'semester' => '1st', 'is_active' => 0,
            'start_date' => date('Y-m-d', strtotime('+30 days')),
        ]);

        $this->actingAs(auth()->getProvider()->findById($this->studentUserId));
    }

    public function testTheWizardShowsTheClosedSplashWithNoOverride(): void
    {
        $result = $this->withSession()->get('/student/enlistment');

        $result->assertSee('Enlistment is not yet open');
    }

    public function testAGrantedWindowOverrideHidesTheClosedSplash(): void
    {
        $enlistTerm = (new AcademicYearModel())->upcomingFormatted();
        (new EnlistmentWindowOverrideModel())->grant($this->studentId, $enlistTerm, 'Catch-up term', 801, 'chairperson');

        $result = $this->withSession()->get('/student/enlistment');

        $result->assertDontSee('Enlistment is not yet open');
    }

    public function testARevokedWindowOverrideShowsTheClosedSplashAgain(): void
    {
        $enlistTerm = (new AcademicYearModel())->upcomingFormatted();
        $granted    = (new EnlistmentWindowOverrideModel())->grant($this->studentId, $enlistTerm, 'Catch-up term', 801, 'chairperson');
        (new EnlistmentWindowOverrideModel())->revoke((int) $granted['id'], 802);

        $result = $this->withSession()->get('/student/enlistment');

        $result->assertSee('Enlistment is not yet open');
    }

    public function testAnOverrideForADifferentTermDoesNotHideTheClosedSplash(): void
    {
        (new EnlistmentWindowOverrideModel())->grant($this->studentId, 'AY 2099 - 2100 · 1st Semester', 'Wrong term', 801, 'chairperson');

        $result = $this->withSession()->get('/student/enlistment');

        $result->assertSee('Enlistment is not yet open');
    }
}
