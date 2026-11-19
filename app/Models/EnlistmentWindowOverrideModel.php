<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A staff-granted exception letting one student use the self-enlistment
 * wizard for one term, bypassing the global enlistment window's open/closed
 * state for that student only (see StudentEnlistment::wizard()/
 * StudentEnlistmentModel::submit()). Granted by a chairperson or registrar
 * for an irregular/catch-up student who needs to enlist outside the normal
 * window — every grant/revoke is audit-logged. Sibling to
 * EnlistmentOverrideModel, which gates subject eligibility instead of
 * window timing.
 */
class EnlistmentWindowOverrideModel extends Model
{
    protected $table      = 'enlistment_window_overrides';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'student_id', 'academic_year', 'reason',
        'granted_by', 'granted_by_role', 'status', 'revoked_by', 'revoked_at',
    ];

    protected $useTimestamps = true;

    public const ROLES = ['chairperson', 'registrar'];

    /**
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function grant(int $studentId, string $academicYear, string $reason, int $grantedBy, string $grantedByRole): array
    {
        if (! in_array($grantedByRole, self::ROLES, true)) {
            return ['ok' => false, 'error' => 'Invalid granting role.'];
        }

        if (trim($reason) === '') {
            return ['ok' => false, 'error' => 'A reason is required.'];
        }

        $student = (new StudentModel())->find($studentId);

        if ($student === null) {
            return ['ok' => false, 'error' => 'Student not found.'];
        }

        if ($this->hasOverride($studentId, $academicYear)) {
            return ['ok' => false, 'error' => 'This student already has an active window override for this term.'];
        }

        $this->insert([
            'student_id'      => $studentId,
            'academic_year'   => $academicYear,
            'reason'          => trim($reason),
            'granted_by'      => $grantedBy,
            'granted_by_role' => $grantedByRole,
            'status'          => 'active',
        ]);

        $id = $this->getInsertID();

        (new AuditLogModel())->record(
            'enlistment.window_override_granted',
            'enlistment_window_override',
            $id,
            null,
            ['student_id' => $studentId, 'academic_year' => $academicYear, 'reason' => trim($reason)],
            null,
            $grantedBy,
            $grantedByRole
        );

        helper('url');
        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'enlistment.window_override_granted',
            "You've been cleared to enlist for {$academicYear}.",
            site_url('student/enlistment')
        );

        return ['ok' => true, 'id' => $id];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function revoke(int $id, int $revokedBy): array
    {
        $override = $this->find($id);

        if ($override === null || $override['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This override is not active.'];
        }

        $this->update($id, [
            'status'      => 'revoked',
            'revoked_by'  => $revokedBy,
            'revoked_at'  => date('Y-m-d H:i:s'),
        ]);

        (new AuditLogModel())->record(
            'enlistment.window_override_revoked',
            'enlistment_window_override',
            $id,
            ['status' => 'active'],
            ['status' => 'revoked'],
            null,
            $revokedBy,
            null
        );

        return ['ok' => true];
    }

    public function hasOverride(int $studentId, string $academicYear): bool
    {
        return $this->where('student_id', $studentId)
            ->where('academic_year', $academicYear)
            ->where('status', 'active')
            ->countAllResults() > 0;
    }

    /**
     * Every active window override for a student this term — for display on
     * the Enlistment Window page's "Student Overrides" panel.
     */
    public function activeForStudent(int $studentId, string $academicYear): array
    {
        return $this->where('student_id', $studentId)
            ->where('academic_year', $academicYear)
            ->where('status', 'active')
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }
}
