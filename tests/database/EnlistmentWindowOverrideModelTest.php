<?php

use App\Models\EnlistmentWindowOverrideModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Traits\SeedsUsers;

/**
 * A staff-granted exception letting one specific student use the
 * self-enlistment wizard for one specific term regardless of the global
 * window's open/closed state. Sibling to EnlistmentOverrideModel's
 * grant/revoke/audit pattern, but gates window timing instead of subject
 * eligibility.
 *
 * @internal
 */
final class EnlistmentWindowOverrideModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use SeedsUsers;

    protected $refresh   = true;
    protected $namespace = ['CodeIgniter\Shield', 'App'];

    private const AY = 'AY 2026 - 2027 · 1st Semester';
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(910);
        db_connect()->table('students')->insert([
            'user_id' => 910, 'applicant_id' => 'A910', 'student_number' => 'S910', 'student_status' => 'active',
        ]);
        $this->studentId = (int) db_connect()->insertID();
    }

    public function testGrantSucceedsAndIsAuditedAndNotifies(): void
    {
        $result = (new EnlistmentWindowOverrideModel())->grant($this->studentId, self::AY, 'Shifted from BSIT', 801, 'chairperson');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->seeInDatabase('enlistment_window_overrides', ['id' => $result['id'], 'student_id' => $this->studentId, 'status' => 'active']);
        $this->seeInDatabase('audit_logs', ['action' => 'enlistment.window_override_granted', 'entity_type' => 'enlistment_window_override']);
        $this->seeInDatabase('notifications', ['user_id' => 910, 'type' => 'enlistment.window_override_granted']);
    }

    public function testGrantRejectsABlankReason(): void
    {
        $result = (new EnlistmentWindowOverrideModel())->grant($this->studentId, self::AY, '   ', 801, 'chairperson');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, db_connect()->table('enlistment_window_overrides')->countAllResults());
    }

    public function testGrantRejectsAnInvalidRole(): void
    {
        $result = (new EnlistmentWindowOverrideModel())->grant($this->studentId, self::AY, 'Reason', 801, 'dean');

        $this->assertFalse($result['ok']);
    }

    public function testGrantRejectsADuplicateActiveGrant(): void
    {
        $first = (new EnlistmentWindowOverrideModel())->grant($this->studentId, self::AY, 'First', 801, 'chairperson');
        $this->assertTrue($first['ok']);

        $second = (new EnlistmentWindowOverrideModel())->grant($this->studentId, self::AY, 'Second', 801, 'chairperson');

        $this->assertFalse($second['ok']);
        $this->assertSame(1, db_connect()->table('enlistment_window_overrides')->countAllResults());
    }

    public function testHasOverrideReflectsActiveStatusOnly(): void
    {
        $model   = new EnlistmentWindowOverrideModel();
        $granted = $model->grant($this->studentId, self::AY, 'Reason', 801, 'chairperson');

        $this->assertTrue($model->hasOverride($this->studentId, self::AY));
        $this->assertFalse($model->hasOverride($this->studentId, 'AY 2099 - 2100 · 1st Semester'));

        $model->revoke((int) $granted['id'], 802);

        $this->assertFalse($model->hasOverride($this->studentId, self::AY));
        $this->seeInDatabase('enlistment_window_overrides', ['id' => $granted['id'], 'status' => 'revoked', 'revoked_by' => 802]);
        $this->seeInDatabase('audit_logs', ['action' => 'enlistment.window_override_revoked']);
    }

    public function testRevokeOnAnAlreadyRevokedOverrideFails(): void
    {
        $model   = new EnlistmentWindowOverrideModel();
        $granted = $model->grant($this->studentId, self::AY, 'Reason', 801, 'chairperson');
        $model->revoke((int) $granted['id'], 802);

        $result = $model->revoke((int) $granted['id'], 802);

        $this->assertFalse($result['ok']);
    }

    public function testActiveForStudentListsOnlyActiveRowsForThatTerm(): void
    {
        $model         = new EnlistmentWindowOverrideModel();
        $granted       = $model->grant($this->studentId, self::AY, 'Keep', 801, 'chairperson');
        $otherTermGrant = $model->grant($this->studentId, 'AY 2099 - 2100 · 1st Semester', 'Other term', 801, 'chairperson');

        $active = $model->activeForStudent($this->studentId, self::AY);

        $this->assertCount(1, $active);
        $this->assertSame((int) $granted['id'], (int) $active[0]['id']);
    }
}
