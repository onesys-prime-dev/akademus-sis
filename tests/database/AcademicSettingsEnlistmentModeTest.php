<?php

use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * Admin Panel's enlistment-window-mode toggle — the new
 * Enlistment.windowMode setting, persisted and read exactly like the
 * existing Academic.* / Finance.* keys. See
 * docs/superpowers/specs/2026-11-19-enlistment-window-scoping-design.md.
 *
 * @internal
 */
final class AcademicSettingsEnlistmentModeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private int $adminId = 70900;

    private function actingAsAdmin(): void
    {
        $this->seedUsers($this->adminId);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $this->adminId, 'group' => 'admin', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById($this->adminId));
    }

    private function postAs(string $url, array $data = [])
    {
        return $this->withHeaders(['X-CSRF-TOKEN' => csrf_hash()])->withSession()->post($url, $data);
    }

    public function testUpdateSetsTheEnlistmentWindowMode(): void
    {
        $this->actingAsAdmin();

        $result = $this->postAs('admin/academic-settings/update', [
            'lecture_hours_per_unit' => 1, 'lab_hours_per_unit' => 1, 'max_faculty_units' => 24,
            'max_meetings_per_week' => 3, 'max_student_units' => 24,
            'enlistment_window_mode' => 'program_scoped',
        ]);

        $result->assertRedirect();
        $this->assertSame('program_scoped', setting('Enlistment.windowMode'));
    }

    public function testUpdateDefaultsToRegistrarOnlyWhenNoModePosted(): void
    {
        $this->actingAsAdmin();

        $this->postAs('admin/academic-settings/update', [
            'lecture_hours_per_unit' => 1, 'lab_hours_per_unit' => 1, 'max_faculty_units' => 24,
            'max_meetings_per_week' => 3, 'max_student_units' => 24,
        ]);

        $this->assertSame('registrar_only', setting('Enlistment.windowMode'));
    }

    public function testIndexShowsTheCurrentMode(): void
    {
        $this->actingAsAdmin();
        setting('Enlistment.windowMode', 'program_scoped');

        $response = $this->withSession()->get('admin/academic-settings');

        $response->assertStatus(200);
        $this->assertStringContainsString('value="program_scoped" selected', (string) $response->getBody());
    }
}
