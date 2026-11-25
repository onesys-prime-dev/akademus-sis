<?php

use App\Models\ProgramEnlistmentWindowModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * A program's own enlistment window — only meaningful in Program-Scoped
 * mode. Mirrors EnlistmentOverrideModel's {ok, error} return style; see
 * docs/superpowers/specs/2026-11-19-enlistment-window-scoping-design.md.
 *
 * @internal
 */
final class ProgramEnlistmentWindowModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private int $chairUserId = 801;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers($this->chairUserId);
    }

    private function seedTerm(?string $opens, ?string $closes): int
    {
        db_connect()->table('academic_years')->insert([
            'label' => '2026-2027', 'semester' => '1st',
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

    public function testUpsertRejectsWhenTheTermHasNoOuterWindowSet(): void
    {
        $ayId      = $this->seedTerm(null, null);
        $programId = $this->seedProgram();

        $result = (new ProgramEnlistmentWindowModel())->upsert($ayId, $programId, '2026-10-05 08:00:00', '2026-10-10 17:00:00', $this->chairUserId);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString("hasn't opened enlistment", $result['error']);
    }

    public function testUpsertRejectsDatesOutsideTheRegistrarsWindow(): void
    {
        $ayId      = $this->seedTerm('2026-10-01 00:00:00', '2026-10-31 23:59:59');
        $programId = $this->seedProgram();

        $result = (new ProgramEnlistmentWindowModel())->upsert($ayId, $programId, '2026-09-01 00:00:00', '2026-10-15 00:00:00', $this->chairUserId);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString("Registrar's window", $result['error']);
    }

    public function testUpsertAcceptsDatesExactlyAtTheRegistrarsBoundary(): void
    {
        $ayId      = $this->seedTerm('2026-10-01 00:00:00', '2026-10-31 23:59:59');
        $programId = $this->seedProgram();

        $result = (new ProgramEnlistmentWindowModel())->upsert($ayId, $programId, '2026-10-01 00:00:00', '2026-10-31 23:59:59', $this->chairUserId);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    public function testUpsertAcceptsHtmlDatetimeLocalFormatExactlyAtTheRegistrarsBoundary(): void
    {
        $ayId      = $this->seedTerm('2026-10-01 00:00:00', '2026-10-31 23:59:59');
        $programId = $this->seedProgram();

        // Browser <input type="datetime-local"> posts "T"-separated values
        // (no seconds), not the "Y-m-d H:i:s" the DB column stores.
        $result = (new ProgramEnlistmentWindowModel())->upsert($ayId, $programId, '2026-10-01T00:00', '2026-10-31T23:59', $this->chairUserId);

        $this->assertTrue($result['ok'], $result['error'] ?? '');
    }

    public function testUpsertRejectsOpensAfterCloses(): void
    {
        $ayId      = $this->seedTerm('2026-10-01 00:00:00', '2026-10-31 23:59:59');
        $programId = $this->seedProgram();

        $result = (new ProgramEnlistmentWindowModel())->upsert($ayId, $programId, '2026-10-20 00:00:00', '2026-10-10 00:00:00', $this->chairUserId);

        $this->assertFalse($result['ok']);
    }

    public function testUpsertInsertsThenLaterUpsertsReplaceTheSameRow(): void
    {
        $ayId      = $this->seedTerm('2026-10-01 00:00:00', '2026-10-31 23:59:59');
        $programId = $this->seedProgram();
        $model     = new ProgramEnlistmentWindowModel();

        $first  = $model->upsert($ayId, $programId, '2026-10-02 00:00:00', '2026-10-10 00:00:00', $this->chairUserId);
        $second = $model->upsert($ayId, $programId, '2026-10-05 00:00:00', '2026-10-20 00:00:00', $this->chairUserId);

        $this->assertTrue($first['ok']);
        $this->assertTrue($second['ok']);
        $this->assertSame(1, db_connect()->table('program_enlistment_windows')->where(['ay_id' => $ayId, 'program_id' => $programId])->countAllResults());
        $this->seeInDatabase('program_enlistment_windows', ['ay_id' => $ayId, 'program_id' => $programId, 'enlistment_opens_at' => '2026-10-05 00:00:00']);
    }

    public function testIsOpenIsFalseWhenNoRowExistsForTheProgram(): void
    {
        $ayId      = $this->seedTerm('2026-10-01 00:00:00', '2026-10-31 23:59:59');
        $programId = $this->seedProgram();

        $this->assertFalse((new ProgramEnlistmentWindowModel())->isOpen($ayId, $programId));
    }

    public function testIsOpenReflectsTheSavedProgramWindow(): void
    {
        $ayId      = $this->seedTerm('2026-10-01 00:00:00', '2026-10-31 23:59:59');
        $programId = $this->seedProgram();
        $model     = new ProgramEnlistmentWindowModel();
        $model->upsert($ayId, $programId, '2026-10-05 00:00:00', '2026-10-10 00:00:00', $this->chairUserId);

        $this->assertTrue($model->isOpen($ayId, $programId, '2026-10-07 00:00:00'));
        $this->assertFalse($model->isOpen($ayId, $programId, '2026-10-20 00:00:00'));
    }
}
