<?php

use App\Models\ProgramModel;
use App\Models\StudentModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Admission -> SIS handoff (StudentModel::promoteFromApplicant). Admissions
 * programs are dynamic, so an unknown program code must be created from the
 * name Admissions sends — once, not per applicant — and admins told.
 *
 * @internal
 */
final class StudentPromotionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private function payload(string $applicantId, array $overrides = []): array
    {
        return array_merge([
            'applicant_id' => $applicantId,
            'first_name'   => 'Test',
            'last_name'    => 'Student' . $applicantId,
            'email'        => "promo{$applicantId}@example.com",
        ], $overrides);
    }

    public function testKnownProgramIsUsedAndNothingIsCreated(): void
    {
        (new ProgramModel())->insert(['program_code' => 'BSIT', 'program_name' => 'BS Information Technology', 'is_active' => 1]);

        $result = (new StudentModel())->promoteFromApplicant($this->payload('A1', ['program_code' => 'bsit', 'program_name' => 'Ignored']));

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['program_created']);
        $this->assertSame(1, (new ProgramModel())->countAllResults());
    }

    public function testUnknownProgramIsCreatedFromTheAdmissionsName(): void
    {
        $result = (new StudentModel())->promoteFromApplicant(
            $this->payload('A2', ['program_code' => 'bsn', 'program_name' => 'Bachelor of Science in Nursing'])
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['program_created']);

        $program = (new ProgramModel())->where('program_code', 'BSN')->first();
        $this->assertNotNull($program);
        $this->assertSame('Bachelor of Science in Nursing', $program['program_name']);
        $this->assertNull($program['sub_department_id'], 'a new program has no college until an admin assigns one');
        $this->assertSame((int) $program['program_id'], (int) $result['student']['program_id']);
    }

    public function testTheSameNewProgramIsNotCreatedTwice(): void
    {
        $model = new StudentModel();

        $first  = $model->promoteFromApplicant($this->payload('B1', ['program_code' => 'BSN', 'program_name' => 'Nursing']));
        $second = $model->promoteFromApplicant($this->payload('B2', ['program_code' => 'BSN', 'program_name' => 'Nursing']));

        $this->assertTrue($first['program_created']);
        $this->assertFalse($second['program_created']);
        $this->assertSame(1, (new ProgramModel())->where('program_code', 'BSN')->countAllResults());
        $this->assertSame($first['student']['program_id'], $second['student']['program_id']);
    }

    public function testUnknownProgramWithoutAnAdmissionsNameIsRefused(): void
    {
        $result = (new StudentModel())->promoteFromApplicant($this->payload('C1', ['program_code' => 'BSXX']));

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Unknown program code', $result['error']);
        $this->assertSame(0, (new ProgramModel())->countAllResults());
        $this->assertSame(0, (new StudentModel())->countAllResults(), 'a refused handoff must not leave a student behind');
    }

    public function testMalformedProgramCodeIsRefused(): void
    {
        $result = (new StudentModel())->promoteFromApplicant(
            $this->payload('D1', ['program_code' => '<script>alert(1)</script>', 'program_name' => 'Evil'])
        );

        $this->assertFalse($result['ok']);
        $this->assertSame(0, (new ProgramModel())->countAllResults());
    }

    public function testRetryingTheSameApplicantReturnsTheSameStudent(): void
    {
        $model = new StudentModel();

        $first  = $model->promoteFromApplicant($this->payload('E1', ['program_code' => 'BSN', 'program_name' => 'Nursing']));
        $second = $model->promoteFromApplicant($this->payload('E1', ['program_code' => 'BSN', 'program_name' => 'Nursing']));

        $this->assertTrue($second['ok']);
        $this->assertSame($first['student']['student_number'], $second['student']['student_number']);
        $this->assertSame(1, $model->countAllResults());
    }

    public function testAdminsAreNotifiedWhenAProgramIsAutoCreated(): void
    {
        $db = db_connect();
        $db->table('users')->insert(['id' => 900, 'username' => 'admin900', 'active' => 1]);
        $db->table('users')->insert(['id' => 901, 'username' => 'student901', 'active' => 1]);
        $db->table('auth_groups_users')->insert(['user_id' => 900, 'group' => 'admin', 'created_at' => date('Y-m-d H:i:s')]);
        $db->table('auth_groups_users')->insert(['user_id' => 901, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);

        $model = new StudentModel();
        $model->promoteFromApplicant($this->payload('F1', ['program_code' => 'BSN', 'program_name' => 'Nursing']));
        $model->promoteFromApplicant($this->payload('F2', ['program_code' => 'BSN', 'program_name' => 'Nursing']));

        $rows = $db->table('notifications')->where('type', 'program.created')->get()->getResultArray();

        $this->assertCount(1, $rows, 'one notice for the one program, not one per applicant');
        $this->assertSame('900', (string) $rows[0]['user_id']);
        $this->assertStringContainsString('BSN', $rows[0]['message']);
    }
}
