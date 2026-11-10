<?php

use App\Libraries\SectionsExporter;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * HTTP-level reject-path coverage for POST chairperson/sections/import —
 * including the paths that reject before SectionsImportParser ever runs,
 * plus the academic_year whitelist check, which needs a real uploaded file
 * to reach (CI4's superglobals service lets a feature test simulate one —
 * see uploadValidXlsxAs()). See ChairpersonImportSectionsTest for the
 * per-row accept-path behavior, exercised directly against
 * processSectionsImport().
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

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function actingAsChairperson(int $chairId): void
    {
        $db = db_connect();

        if ($db->table('departments')->where('dept_id', 90001)->countAllResults() === 0) {
            $db->table('colleges')->insert(['college_id' => 90001, 'code' => 'ROUTECOL', 'name' => 'Route Test College']);
            $db->table('departments')->insert(['dept_id' => 90001, 'college_id' => 90001, 'code' => 'ROUTEDEPT', 'name' => 'Route Test Department']);
        }

        $this->seedUsers($chairId);
        $db->table('users')->where('id', $chairId)->update(['sub_department_id' => 90001]);
        $db->table('auth_groups_users')->insert(['user_id' => $chairId, 'group' => 'chairperson', 'created_at' => date('Y-m-d H:i:s')]);
        $this->actingAs(auth()->getProvider()->findById($chairId));
    }

    /**
     * Populates the "superglobals" service's files array the same shape
     * PHP's own $_FILES would have for a real <input type="file"> upload
     * named $fieldName, so IncomingRequest::getFile($fieldName) resolves it
     * during the request this test is about to make.
     */
    private function uploadValidXlsxAs(string $fieldName): void
    {
        $path = tempnam(sys_get_temp_dir(), 'route_test_upload_') . '.xlsx';
        $this->tempFiles[] = $path;
        file_put_contents($path, SectionsExporter::toXlsxString(SectionsExporter::build([], [])));

        service('superglobals')->setFilesArray([
            $fieldName => [
                'name'     => 'upload.xlsx',
                'type'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'tmp_name' => $path,
                'error'    => 0,
                'size'     => filesize($path),
            ],
        ]);
    }

    public function testImportRejectsWhenAcademicYearIsNotCreatable(): void
    {
        $this->actingAsChairperson(70701);
        $this->uploadValidXlsxAs('file');

        $countBefore = db_connect()->table('sections')->countAllResults();

        $result = $this->withSession()->post('/chairperson/sections/import', ['academic_year' => 'Not A Real Term', csrf_token() => csrf_hash()]);

        $result->assertStatus(400);
        $body = json_decode($result->response()->getBody(), true);
        $this->assertSame('Invalid academic year.', $body['error']);
        $this->assertSame($countBefore, db_connect()->table('sections')->countAllResults());
    }

    public function testImportRejectsWhenNoFileIsAttached(): void
    {
        $this->actingAsChairperson(70702);
        service('superglobals')->setFilesArray([]);

        $result = $this->withSession()->post('/chairperson/sections/import', ['academic_year' => 'Not A Real Term', csrf_token() => csrf_hash()]);

        $result->assertStatus(400);
        $body = json_decode($result->response()->getBody(), true);
        $this->assertFalse($body['ok']);
        $this->assertStringContainsString('file', strtolower($body['error']));
    }
}
