<?php

use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * The notification feed is JSON for the console's bell panel, which always
 * fetches it with X-Requested-With: XMLHttpRequest. A plain browser
 * navigation must be sent to the dashboard instead of rendering raw JSON —
 * that is exactly how a chairperson ends up staring at a JSON page right
 * after logging in, because Shield redirects a freshly authenticated user
 * back to the URL that triggered the login.
 *
 * @internal
 */
final class NotificationFeedTest extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private const CHAIR_ID = 60001;

    private function seedChairperson(): int
    {
        $this->seedUsers(self::CHAIR_ID);
        db_connect()->table('auth_groups_users')->insert([
            'user_id' => self::CHAIR_ID, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s'),
        ]);

        return self::CHAIR_ID;
    }

    private function notifyChair(string $message): void
    {
        db_connect()->table('notifications')->insert([
            'user_id'    => self::CHAIR_ID,
            'type'       => 'enlistment.submitted',
            'message'    => $message,
            'link'       => 'chairperson/enlistment-approvals',
            'is_read'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testBrowserNavigationRedirectsToTheDashboardInsteadOfRawJson(): void
    {
        $chair = $this->seedChairperson();
        $this->notifyChair('A student has submitted their enlistment for your review.');
        $this->actingAs(auth()->getProvider()->findById($chair));

        $result = $this->withSession()->get('/chairperson/notifications');

        $result->assertRedirectTo(site_url('dashboard'));
    }

    public function testThePanelsAjaxFetchStillReceivesTheJsonFeed(): void
    {
        $chair = $this->seedChairperson();
        $this->notifyChair('A student has submitted their enlistment for your review.');
        $this->actingAs(auth()->getProvider()->findById($chair));

        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->withSession()
            ->get('/chairperson/notifications');

        $result->assertStatus(200);

        $body = json_decode($result->getJSON(), true);

        $this->assertArrayHasKey('notifications', $body);
        $this->assertArrayHasKey('unread', $body);
        $this->assertCount(1, $body['notifications']);
        $this->assertSame(1, $body['unread']);
        $this->assertSame('A student has submitted their enlistment for your review.', $body['notifications'][0]['message']);
    }
}
