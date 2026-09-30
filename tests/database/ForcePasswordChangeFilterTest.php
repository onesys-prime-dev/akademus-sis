<?php

use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Server-side backstop for the temp-password modal (see
 * layouts/admin_console.php + ForcePasswordChangeFilter): a flagged account
 * must be blocked from any state-changing request except the endpoint that
 * clears the flag, while GET requests always still render (the modal has to
 * show on something).
 *
 * @internal
 */
final class ForcePasswordChangeFilterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private function userFlagged(int $id, bool $flagged): \CodeIgniter\Shield\Entities\User
    {
        $db = db_connect();
        $db->table('users')->insert([
            'id'                    => $id,
            'username'              => "pwtest{$id}",
            'active'                => 1,
            'must_change_password'  => $flagged ? 1 : 0,
        ]);
        $db->table('auth_identities')->insert([
            'user_id' => $id,
            'type'    => 'email_password',
            'secret'  => "pwtest{$id}@example.com",
            'secret2' => password_hash('irrelevant', PASSWORD_DEFAULT),
        ]);
        $db->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);

        return auth()->getProvider()->findById($id);
    }

    /** CSRF is on globally (see Config\Filters), so every POST in these tests must carry a valid token. */
    private function withCsrf(array $params): array
    {
        return $params + [csrf_token() => csrf_hash()];
    }

    public function testFlaggedUserIsRedirectedBackFromAnOrdinaryFormPost(): void
    {
        $this->actingAs($this->userFlagged(2001, true));

        $result = $this->withSession()->post('/student/enroll', $this->withCsrf([]));

        $result->assertRedirect();
        $result->assertSessionHas('errors', ['Please set a new password before continuing.']);
    }

    public function testFlaggedUserGetsAJsonRejectionFromAnAjaxPost(): void
    {
        $this->actingAs($this->userFlagged(2005, true));

        $result = $this->withSession()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('/student/enroll', $this->withCsrf([]));

        $result->assertStatus(403);
        $result->assertJSONFragment(['success' => false]);
    }

    public function testFlaggedUserCanStillReachTheSetTempPasswordEndpoint(): void
    {
        $this->actingAs($this->userFlagged(2002, true));

        $result = $this->withSession()->post('/account/set-temp-password', $this->withCsrf([
            'new_password'     => 'a-real-password-123',
            'confirm_password' => 'a-real-password-123',
        ]));

        $result->assertStatus(200);
        $result->assertJSONFragment(['success' => true]);

        $row = db_connect()->table('users')->where('id', 2002)->get()->getRowArray();
        $this->assertSame(0, (int) $row['must_change_password'], 'the flag must be cleared after a successful change');
    }

    public function testFlaggedUserCanStillLoadAPageViaGet(): void
    {
        $this->actingAs($this->userFlagged(2003, true));

        $result = $this->withSession()->get('/student/dashboard');

        $result->assertStatus(200);
    }

    public function testUnflaggedUserIsNotBlocked(): void
    {
        $this->actingAs($this->userFlagged(2004, false));

        $result = $this->withSession()->post('/account/set-temp-password', $this->withCsrf([
            'new_password'     => 'a-real-password-123',
            'confirm_password' => 'a-real-password-123',
        ]));

        $this->assertNotSame(403, $result->response()->getStatusCode());
    }
}
