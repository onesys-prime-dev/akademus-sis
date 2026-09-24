<?php

use App\Libraries\SectionBalancer;
use App\Models\CapacityPolicyModel;
use App\Models\SectionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class SectionBalancerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const AY      = 'AY2026-2027';
    private const PROGRAM = 'BS Computer Science';
    private const YEAR    = '1st Year';

    private int $nextUser = 1000;

    private function section(string $name, array $extra = []): array
    {
        $m = new SectionModel();
        $m->insert(array_merge([
            'academic_year' => self::AY, 'program' => self::PROGRAM, 'year_level' => self::YEAR,
            'section' => $name, 'status' => 'open',
        ], $extra));

        return $m->find($m->getInsertID());
    }

    private function students(int $sectionId, int $n): void
    {
        $db = db_connect();

        for ($i = 0; $i < $n; $i++) {
            $id = $this->nextUser++;
            $db->table('users')->insert(['id' => $id, 'username' => "stu{$id}", 'active' => 1, 'section_id' => $sectionId]);
            $db->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);
        }
    }

    private function sizes(): array
    {
        $rows = db_connect()->query(
            "SELECT s.section, COUNT(u.id) AS c FROM sections s LEFT JOIN users u ON u.section_id = s.section_id
             WHERE s.status != 'closed' GROUP BY s.section_id, s.section ORDER BY c DESC, s.section"
        )->getResultArray();

        return array_map('intval', array_column($rows, 'c'));
    }

    public function testSplitsOneOverfullSectionIntoTwoBalancedOnes(): void
    {
        $a = $this->section('1A');
        $this->students((int) $a['section_id'], 24);

        $result = (new SectionBalancer())->apply(self::AY, self::PROGRAM, self::YEAR, null, 'chairperson', 'Enrollment growth');

        $this->assertTrue($result['ok']);
        $this->assertSame(['1B'], $result['created']);
        $this->assertSame(12, $result['moved']);
        $this->assertSame([12, 12], $this->sizes());

        $new = (new SectionModel())->forContext(self::AY, self::PROGRAM, self::YEAR, '1B');
        $this->assertSame('proposed', $new['status']);
        $this->assertSame('auto', $new['origin']);

        $this->assertSame(1, db_connect()->table('audit_logs')->where('action', 'sections.rebalance')->countAllResults());
    }

    public function testSixteenStudentsStayTogether(): void
    {
        $a = $this->section('1A');
        $this->students((int) $a['section_id'], 16);

        $analysis = (new SectionBalancer())->analyze(self::AY, self::PROGRAM, self::YEAR);

        $this->assertFalse($analysis['has_changes']);
        $this->assertStringContainsString('below the configured minimum of 10', $analysis['explanation']);
    }

    public function testRebalanceEvensOutSkewedSections(): void
    {
        $a = $this->section('1A');
        $b = $this->section('1B');
        $this->students((int) $a['section_id'], 18);
        $this->students((int) $b['section_id'], 7);

        $preview = (new SectionBalancer())->analyze(self::AY, self::PROGRAM, self::YEAR);
        $this->assertSame([18, 7], $preview['current_sizes']);
        $this->assertSame([13, 12], $preview['plan']['sizes']);
        $this->assertSame(5, $preview['students_affected']);

        $this->assertTrue((new SectionBalancer())->apply(self::AY, self::PROGRAM, self::YEAR, null, null, 'Balance')['ok']);
        $this->assertSame([13, 12], $this->sizes());
    }

    public function testReasonIsRequired(): void
    {
        $this->section('1A');

        $this->assertFalse((new SectionBalancer())->apply(self::AY, self::PROGRAM, self::YEAR, null, null, '  ')['ok']);
    }

    public function testSectionWithScheduleIsNeverTouched(): void
    {
        $a = $this->section('1A');
        $this->students((int) $a['section_id'], 30);

        db_connect()->table('plotted_schedules')->insert([
            'academic_year' => self::AY, 'program' => self::PROGRAM, 'year_level' => self::YEAR, 'section' => '1A',
            'day' => 0, 'start_time' => '08:00', 'end_time' => '10:00', 'time_label' => '8-10',
            'subject_code' => 'CS101', 'subject_title' => 'X', 'room' => 'R1', 'theme' => 'blue', 'status' => 'published',
        ]);

        $result = (new SectionBalancer())->apply(self::AY, self::PROGRAM, self::YEAR, null, null, 'Try');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('schedule', $result['error']);
        $this->assertSame([30], $this->sizes());
    }

    public function testLockedAndAutoBalanceOffSectionsAreSkipped(): void
    {
        $a = $this->section('1A', ['status' => 'locked']);
        $b = $this->section('1B', ['auto_balance' => 0]);
        $this->students((int) $a['section_id'], 25);
        $this->students((int) $b['section_id'], 25);

        $analysis = (new SectionBalancer())->analyze(self::AY, self::PROGRAM, self::YEAR);

        $this->assertSame([], $analysis['free_sections']);
        $this->assertStringContainsString('blocked', $analysis['explanation']);
    }

    public function testProgramPolicyOverridesSystemDefault(): void
    {
        $policies = new CapacityPolicyModel();
        $policies->insert(['scope_type' => 'system', 'scope_id' => '', 'year_level' => '', 'max_capacity' => 40, 'target_capacity' => 35, 'min_capacity' => 20, 'overflow_tolerance' => 5]);
        $policies->insert(['scope_type' => 'program', 'scope_id' => self::PROGRAM, 'year_level' => '', 'max_capacity' => 30, 'target_capacity' => 25]);

        $resolved = $policies->resolve(['program' => self::PROGRAM, 'year_level' => self::YEAR]);

        $this->assertSame(30, $resolved['max']);      // program wins
        $this->assertSame(25, $resolved['target']);
        $this->assertSame(20, $resolved['min']);      // inherited from system
        $this->assertSame(5, $resolved['overflow']);

        // a section's own capacity sits above every scope
        $this->assertSame(12, $policies->resolve(['program' => self::PROGRAM, 'capacity' => 12])['max']);
    }

    public function testAssignEnforcesCeilingUnlessOverridden(): void
    {
        $a = $this->section('1A');
        $this->students((int) $a['section_id'], 20); // ceiling is 15 + 5

        $db = db_connect();
        $db->table('users')->insert(['id' => 5000, 'username' => 'late', 'active' => 1]);
        $db->table('auth_groups_users')->insert(['user_id' => 5000, 'group' => 'student', 'created_at' => date('Y-m-d H:i:s')]);

        $balancer = new SectionBalancer();

        $refused = $balancer->assignStudent(5000, (int) $a['section_id'], 1, 'chairperson');
        $this->assertFalse($refused['ok']);
        $this->assertTrue($refused['over_capacity']);

        $ok = $balancer->assignStudent(5000, (int) $a['section_id'], 1, 'chairperson', 'Transferee approved by dean');
        $this->assertTrue($ok['ok']);
        $this->assertSame(1, $db->table('audit_logs')->where('action', 'sections.assign_override')->countAllResults());
    }

    public function testClosedSectionRefusesStudents(): void
    {
        $a = $this->section('1A', ['status' => 'closed']);
        db_connect()->table('users')->insert(['id' => 5001, 'username' => 'x', 'active' => 1]);

        $this->assertFalse((new SectionBalancer())->assignStudent(5001, (int) $a['section_id'], null, null)['ok']);
    }

    public function testAuditLogIsAppendOnly(): void
    {
        $this->expectException(LogicException::class);
        (new \App\Models\AuditLogModel())->delete(1);
    }

    public function testDuplicateSectionNameRejectedByUniqueKey(): void
    {
        $this->section('1A');

        $this->expectException(Throwable::class);
        db_connect()->table('sections')->insert([
            'academic_year' => self::AY, 'program' => self::PROGRAM, 'year_level' => self::YEAR, 'section' => '1A',
        ]);
    }
}
