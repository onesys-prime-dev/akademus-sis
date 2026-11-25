<?php

use App\Libraries\EnlistmentWindowResolver;
use App\Models\ProgramEnlistmentWindowModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * The single enlistment-window gating decision — see
 * docs/superpowers/specs/2026-11-19-enlistment-window-scoping-design.md.
 *
 * @internal
 */
final class EnlistmentWindowResolverTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'App'];

    private int $chairUserId = 801;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers($this->chairUserId);
        setting('Enlistment.windowMode', null);
    }

    private function seedTerm(?string $opens, ?string $closes, ?string $startDate = null): int
    {
        db_connect()->table('academic_years')->insert([
            'label' => '2026-2027', 'semester' => '1st', 'start_date' => $startDate,
            'enlistment_opens_at' => $opens, 'enlistment_closes_at' => $closes,
        ]);

        return (int) db_connect()->insertID();
    }

    private function seedProgram(): int
    {
        db_connect()->table('programs')->insert([
            'program_code' => 'BSIT-' . uniqid(), 'program_name' => 'BS Information Technology',
        ]);

        return (int) db_connect()->insertID();
    }

    public function testInRegistrarOnlyModeAProgramWindowIsIgnored(): void
    {
        setting('Enlistment.windowMode', 'registrar_only');
        $ayId      = $this->seedTerm(date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+60 days')));
        $programId = $this->seedProgram();
        (new ProgramEnlistmentWindowModel())->upsert($ayId, $programId, date('Y-m-d H:i:s', strtotime('+99 years')), date('Y-m-d H:i:s', strtotime('+99 years +1 day')), $this->chairUserId);

        $this->assertTrue((new EnlistmentWindowResolver())->isOpenFor($ayId, $programId));
    }

    public function testInProgramScopedModeBothWindowsMustBeOpen(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        $ayId      = $this->seedTerm(date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+60 days')));
        $programId = $this->seedProgram();
        (new ProgramEnlistmentWindowModel())->upsert($ayId, $programId, date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+60 days')), $this->chairUserId);

        $this->assertTrue((new EnlistmentWindowResolver())->isOpenFor($ayId, $programId));
    }

    public function testInProgramScopedModeTermClosedOverridesAnOpenProgramWindow(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        $ayId      = $this->seedTerm(date('Y-m-d H:i:s', strtotime('+30 days')), date('Y-m-d H:i:s', strtotime('+60 days'))); // term not yet open
        $programId = $this->seedProgram();
        // Program window itself is wide open — only the closed term should gate this.
        (new ProgramEnlistmentWindowModel())->upsert($ayId, $programId, date('Y-m-d H:i:s', strtotime('+30 days')), date('Y-m-d H:i:s', strtotime('+60 days')), $this->chairUserId);

        $this->assertFalse((new EnlistmentWindowResolver())->isOpenFor($ayId, $programId));
    }

    public function testInProgramScopedModeAnUnconfiguredProgramIsClosed(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        $ayId      = $this->seedTerm(date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+60 days')));
        $programId = $this->seedProgram();

        $this->assertFalse((new EnlistmentWindowResolver())->isOpenFor($ayId, $programId));
    }

    public function testANullProgramIdFallsBackToTermOnlyRegardlessOfMode(): void
    {
        setting('Enlistment.windowMode', 'program_scoped');
        $ayId = $this->seedTerm(date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+60 days')));

        $this->assertTrue((new EnlistmentWindowResolver())->isOpenFor($ayId, null));
    }

    public function testANullAyIdDefaultsToTheUpcomingTerm(): void
    {
        $ayId = $this->seedTerm(date('Y-m-d H:i:s', strtotime('-1 day')), date('Y-m-d H:i:s', strtotime('+60 days')), date('Y-m-d', strtotime('+30 days')));

        $this->assertTrue((new EnlistmentWindowResolver())->isOpenFor(null, null));
    }

    public function testModeDefaultsToRegistrarOnlyWhenNeverSet(): void
    {
        $this->assertSame('registrar_only', (new EnlistmentWindowResolver())->mode());
    }
}
