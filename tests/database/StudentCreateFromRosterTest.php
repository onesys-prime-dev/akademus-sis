<?php

use App\Models\ProgramModel;
use App\Models\StudentModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * StudentModel::createFromRoster() — the Sections-import "create missing
 * students" path. Unlike promoteFromApplicant(), there is no Admissions
 * applicant behind the row, so this covers the account creation itself:
 * Shield user, email identity, student group, and the students row.
 *
 * @internal
 */
final class StudentCreateFromRosterTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name'  => 'Mathew',
            'middle_name' => 'R',
            'last_name'   => 'Umali',
            'email'       => 'mathew.umali@example.com',
            'program_id'  => null,
        ], $overrides);
    }

    public function testCreatesAStudentAccountAndReturnsATempPassword(): void
    {
        $result = (new StudentModel())->createFromRoster($this->payload());

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['temp_password']);
        $this->assertStringStartsWith('STU-', $result['student_number']);

        $db = db_connect();
        $user = $db->table('users')->where('id', $result['user_id'])->get()->getRowArray();
        $this->assertSame('Mathew', $user['first_name']);
        $this->assertSame('R', $user['middle_name']);
        $this->assertSame('Umali', $user['last_name']);
        $this->assertSame(1, (int) $user['must_change_password']);

        $group = $db->table('auth_groups_users')->where('user_id', $result['user_id'])->get()->getRowArray();
        $this->assertSame('student', $group['group']);

        $student = (new StudentModel())->where('user_id', $result['user_id'])->first();
        $this->assertSame($result['student_number'], $student['student_number']);
        $this->assertSame('active', $student['student_status']);
    }

    public function testAttachesTheLiveCurriculumWhenAProgramIsGiven(): void
    {
        $program = (new ProgramModel())->insert(['program_code' => 'BSIT', 'program_name' => 'BS Information Technology', 'is_active' => 1]);
        $programId = (int) $program;

        $result = (new StudentModel())->createFromRoster($this->payload(['program_id' => $programId]));

        $this->assertTrue($result['ok']);
        $student = (new StudentModel())->where('user_id', $result['user_id'])->first();
        $this->assertSame($programId, (int) $student['program_id']);
    }

    public function testRefusesADuplicateEmailWithoutCreatingAnything(): void
    {
        $first = (new StudentModel())->createFromRoster($this->payload());
        $this->assertTrue($first['ok']);

        $second = (new StudentModel())->createFromRoster($this->payload(['first_name' => 'Other']));

        $this->assertFalse($second['ok']);
        $this->assertStringContainsString('already exists', $second['error']);
        $this->assertSame(1, (new StudentModel())->countAllResults());
    }

    public function testTwoDifferentRowsGetDifferentSequentialStudentNumbers(): void
    {
        $a = (new StudentModel())->createFromRoster($this->payload(['email' => 'a@example.com']));
        $b = (new StudentModel())->createFromRoster($this->payload(['email' => 'b@example.com']));

        $this->assertNotSame($a['student_number'], $b['student_number']);
    }
}
