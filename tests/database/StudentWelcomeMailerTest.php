<?php

use App\Libraries\StudentWelcomeMailer;
use App\Models\StudentModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * The first-login email for students created by the Admissions handoff.
 * (Actual SMTP delivery is not exercised — only what gets built.)
 *
 * @internal
 */
final class StudentWelcomeMailerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testMessageCarriesTheStudentNumberAndTempPasswordAndEscapesTheName(): void
    {
        $html = (new StudentWelcomeMailer())->buildMessage(
            '<script>alert(1)</script>Ana',
            'STU-2026-00042',
            'ana@example.com',
            'a1b2c3d4e5f6'
        );

        $this->assertStringContainsString('STU-2026-00042', $html);
        $this->assertStringContainsString('ana@example.com', $html);
        $this->assertStringContainsString('a1b2c3d4e5f6', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testOnlyABrandNewStudentIsFlaggedAsCreated(): void
    {
        $model = new StudentModel();
        $data  = [
            'applicant_id' => 'W1',
            'first_name'   => 'Welcome',
            'last_name'    => 'Test',
            'email'        => 'welcome.w1@example.com',
        ];

        $first  = $model->promoteFromApplicant($data);
        $second = $model->promoteFromApplicant($data);

        $this->assertTrue($first['ok']);
        $this->assertTrue($first['created']);
        $this->assertArrayHasKey('user_id', $first['student']);

        $this->assertTrue($second['ok']);
        $this->assertEmpty($second['created'] ?? false, 'a retried handoff must not trigger a second email');
    }

    public function testPromotionSetsMustChangePasswordAndReturnsATempPasswordOnceOnly(): void
    {
        $model = new StudentModel();
        $data  = [
            'applicant_id' => 'W2',
            'first_name'   => 'Temp',
            'last_name'    => 'Pw',
            'email'        => 'temp.pw@example.com',
        ];

        $first  = $model->promoteFromApplicant($data);
        $second = $model->promoteFromApplicant($data);

        $this->assertNotEmpty($first['temp_password']);
        $this->assertArrayNotHasKey('temp_password', $second, 'a retried handoff must not reissue a temp password');

        $row = db_connect()->table('users')->where('id', $first['student']['user_id'])->get()->getRowArray();
        $this->assertSame(1, (int) $row['must_change_password']);
    }
}
