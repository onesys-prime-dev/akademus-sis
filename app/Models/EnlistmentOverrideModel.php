<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A staff-granted exception letting one student enlist in one subject for
 * one term, bypassing the prerequisite-list exclusion and the corequisite
 * submit-time gate for that subject only (see
 * StudentEnlistment::wizard()/StudentEnlistmentModel::submit()). Granted by
 * a chairperson or registrar in person after clearing an irregular/catch-up
 * student — every grant/revoke is audit-logged.
 */
class EnlistmentOverrideModel extends Model
{
    protected $table      = 'enlistment_overrides';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'student_id', 'subject_code', 'academic_year', 'reason',
        'granted_by', 'granted_by_role', 'status', 'revoked_by', 'revoked_at',
    ];

    protected $useTimestamps = true;

    public const ROLES = ['chairperson', 'registrar'];

    /**
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function grant(int $studentId, string $subjectCode, string $academicYear, string $reason, int $grantedBy, string $grantedByRole): array
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

        if ($this->hasOverride($studentId, $subjectCode, $academicYear)) {
            return ['ok' => false, 'error' => 'This student already has an active override for this subject/term.'];
        }

        $this->insert([
            'student_id'      => $studentId,
            'subject_code'    => $subjectCode,
            'academic_year'   => $academicYear,
            'reason'          => trim($reason),
            'granted_by'      => $grantedBy,
            'granted_by_role' => $grantedByRole,
            'status'          => 'active',
        ]);

        $id = $this->getInsertID();

        (new AuditLogModel())->record(
            'enlistment.override_granted',
            'enlistment_override',
            $id,
            null,
            ['student_id' => $studentId, 'subject_code' => $subjectCode, 'academic_year' => $academicYear, 'reason' => trim($reason)],
            null,
            $grantedBy,
            $grantedByRole
        );

        helper('url');
        (new NotificationModel())->notify(
            (int) $student['user_id'],
            'enlistment.override_granted',
            "You've been cleared to enlist in {$subjectCode} for {$academicYear}.",
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
            'enlistment.override_revoked',
            'enlistment_override',
            $id,
            ['status' => 'active'],
            ['status' => 'revoked'],
            null,
            $revokedBy,
            null
        );

        return ['ok' => true];
    }

    public function hasOverride(int $studentId, string $subjectCode, string $academicYear): bool
    {
        return $this->where('student_id', $studentId)
            ->where('subject_code', $subjectCode)
            ->where('academic_year', $academicYear)
            ->where('status', 'active')
            ->countAllResults() > 0;
    }

    /**
     * Every active override for a student this term — for display on the
     * staff grant-tool's student detail page.
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
