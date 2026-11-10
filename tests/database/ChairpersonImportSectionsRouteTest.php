<?php

use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * HTTP-level reject-path coverage for POST chairperson/sections/import —
 * the paths that reject before SectionsImportParser ever runs, which don't
 * require simulating a real multipart file upload. See
 * ChairpersonImportSectionsTest for the accept-path / per-row behavior,
 * exercised directly against processSectionsImport().
 *
 * @internal
 */
final class ChairpersonImportSectionsRouteTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private function actingAsChairperson(int $chairId): void
    {
        $this->seedUsers($chairId);
        db_connect()->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById($chairId));
    }

    public function testImportRejectsWhenAcademicYearIsNotCreatable(): void
    {
        $this->actingAsChairperson(70701);

        $countBefore = db_connect()->table('sections')->countAllResults();

        $result = $this->withSession()->post('/chairperson/sections/import', ['academic_year' => 'Not A Real Term', csrf_token() => csrf_hash()]);

        $result->assertStatus(400);
        $body = json_decode($result->response()->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertSame($countBefore, db_connect()->table('sections')->countAllResults());
    }

    public function testImportRejectsWhenNoFileIsAttached(): void
    {
        $this->actingAsChairperson(70702);

        $result = $this->withSession()->post('/chairperson/sections/import', ['academic_year' => 'Not A Real Term', csrf_token() => csrf_hash()]);

        $result->assertStatus(400);
        $body = json_decode($result->response()->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertStringContainsString('file', strtolower($body['error']));
    }
}
