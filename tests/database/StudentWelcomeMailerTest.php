<?php

use App\Libraries\StudentWelcomeMailer;
use App\Models\PasswordResetTokenModel;
use App\Models\StudentModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * The first-login email for students created by the Admissions handoff.
 * (Actual SMTP delivery is not exercised — only what gets issued and built.)
 *
 * @internal
 */
final class StudentWelcomeMailerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    public function testSetPasswordLinkStoresOnlyAHashedTokenThatLastsAWeek(): void
    {
        $this->seedUsers(800);

        $link  = (new StudentWelcomeMailer())->issueSetPasswordLink(800);
        $token = basename($link);

        $row = (new PasswordResetTokenModel())->where('user_id', 800)->first();

        $this->assertNotNull($row);
        $this->assertSame(hash('sha256', $token), $row['token'], 'the DB must hold the hash, never the raw token');
        $this->assertNotSame($token, $row['token']);

        $seconds = strtotime($row['expires_at']) - time();
        $this->assertGreaterThan(6.9 * 86400, $seconds);
        $this->assertLessThan(7.1 * 86400, $seconds);
    }

    public function testANewLinkReplacesTheOldOne(): void
    {
        $this->seedUsers(801);
        $mailer = new StudentWelcomeMailer();

        $first  = basename($mailer->issueSetPasswordLink(801));
        $second = basename($mailer->issueSetPasswordLink(801));

        $rows = (new PasswordResetTokenModel())->where('user_id', 801)->findAll();

        $this->assertCount(1, $rows);
        $this->assertSame(hash('sha256', $second), $rows[0]['token']);
        $this->assertNotSame(hash('sha256', $first), $rows[0]['token']);
    }

    public function testMessageCarriesTheStudentNumberAndLinkAndEscapesTheName(): void
    {
        $html = (new StudentWelcomeMailer())->buildMessage(
            '<script>alert(1)</script>Ana',
            'STU-2026-00042',
            'ana@example.com',
            'http://localhost/reset-password/abc123'
        );

        $this->assertStringContainsString('STU-2026-00042', $html);
        $this->assertStringContainsString('ana@example.com', $html);
        $this->assertStringContainsString('reset-password/abc123', $html);
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
}
