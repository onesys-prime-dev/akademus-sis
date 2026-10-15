<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One row per student per graduation cycle — the clearance case header.
 * Per-item sign-off lives in graduation_clearance_items, resolved here via
 * GraduationClearanceItemModel. Mirrors the {ok, error}-returning,
 * audited-mutation convention used throughout (GradeModel, EnrollmentModel).
 */
class GraduationClearanceModel extends Model
{
    protected $table      = 'graduation_clearances';
    protected $primaryKey = 'id';

    protected $allowedFields = ['student_id', 'academic_year', 'status', 'initiated_by'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * Opens a clearance case for a student, seeding one item per active
     * clearance_requirements row. Refuses a second open case for the same
     * student (one in-flight clearance cycle at a time, same invariant
     * shape as GradeChangeRequestModel::hasOpenRequest()).
     */
    public function initiate(int $studentId, string $academicYear, int $initiatedBy): array
    {
        $requirements = (new ClearanceRequirementModel())->active();

        if ($requirements === []) {
            return ['ok' => false, 'error' => 'No clearance requirements are configured. Contact an administrator.'];
        }

        // Lock the student row so two concurrent initiate() calls for the
        // same student can't both pass the open-case check before either
        // inserts, which would open two overlapping clearance cases.
        $db     = $this->db;
        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
        $db->transStart();
        $db->query("SELECT student_id FROM students WHERE student_id = ?{$suffix}", [$studentId]);

        if ($this->where('student_id', $studentId)->where('status', 'pending')->countAllResults() > 0) {
            $db->transComplete();

            return ['ok' => false, 'error' => 'A clearance case is already in progress for this student.'];
        }

        $this->insert([
            'student_id'    => $studentId,
            'academic_year' => $academicYear,
            'status'        => 'pending',
            'initiated_by'  => $initiatedBy,
        ]);

        $clearanceId = $this->getInsertID();
        $itemModel   = new GraduationClearanceItemModel();

        foreach ($requirements as $requirement) {
            $itemModel->insert([
                'clearance_id'   => $clearanceId,
                'requirement_id' => $requirement['id'],
                'status'         => 'pending',
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not open the clearance case. Please try again.'];
        }

        (new AuditLogModel())->record(
            'clearance.initiated',
            'graduation_clearance',
            $clearanceId,
            null,
            ['student_id' => $studentId, 'academic_year' => $academicYear],
            null,
            $initiatedBy,
            'registrar'
        );

        return ['ok' => true, 'clearance_id' => $clearanceId];
    }

    /**
     * $authorizedOffices, when given (ClearanceAuthorization::
     * authorizedOfficeRoles()), restricts this to items whose
     * clearance_requirements.office_role the actor is actually allowed to
     * act on — e.g. only Finance may clear the 'accounting' item. Omit it
     * (null) only for trusted internal callers that have already checked
     * authorization themselves; every controller action should pass it.
     */
    public function clearItem(int $clearanceId, int $itemId, int $clearedBy, ?string $remarks = null, ?array $authorizedOffices = null): array
    {
        return $this->decideItem($clearanceId, $itemId, 'cleared', $clearedBy, $remarks, $authorizedOffices);
    }

    public function denyItem(int $clearanceId, int $itemId, int $clearedBy, ?string $remarks = null, ?array $authorizedOffices = null): array
    {
        return $this->decideItem($clearanceId, $itemId, 'denied', $clearedBy, $remarks, $authorizedOffices);
    }

    private function decideItem(int $clearanceId, int $itemId, string $decision, int $actorId, ?string $remarks, ?array $authorizedOffices): array
    {
        $itemModel = new GraduationClearanceItemModel();
        $item      = $this->db->table('graduation_clearance_items i')
            ->select('i.*, r.office_role')
            ->join('clearance_requirements r', 'r.id = i.requirement_id')
            ->where('i.id', $itemId)
            ->where('i.clearance_id', $clearanceId)
            ->get()
            ->getRowArray();

        if ($item === null) {
            return ['ok' => false, 'error' => 'Clearance item not found.'];
        }

        if ($authorizedOffices !== null && ! in_array($item['office_role'], $authorizedOffices, true)) {
            return ['ok' => false, 'error' => 'You are not authorized to act on this clearance item.'];
        }

        $itemModel->update($itemId, [
            'status'     => $decision,
            'cleared_by' => $actorId,
            'cleared_at' => date('Y-m-d H:i:s'),
            'remarks'    => $remarks,
        ]);

        (new AuditLogModel())->record(
            'clearance_item.' . $decision,
            'graduation_clearance_item',
            $itemId,
            ['status' => $item['status']],
            ['status' => $decision],
            $remarks,
            $actorId,
            null
        );

        if ($decision === 'denied') {
            $this->update($clearanceId, ['status' => 'denied']);
        }

        return ['ok' => true];
    }

    public function isFullyCleared(int $clearanceId): bool
    {
        $items = (new GraduationClearanceItemModel())->where('clearance_id', $clearanceId)->findAll();

        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            if ($item['status'] !== 'cleared') {
                return false;
            }
        }

        return true;
    }

    /**
     * Requires every item cleared, then marks the clearance case cleared
     * and the student graduated via StudentModel::changeStatus() (which
     * itself audits + notifies).
     */
    public function finalizeGraduation(int $clearanceId, int $registrarId): array
    {
        $clearance = $this->find($clearanceId);

        if ($clearance === null) {
            return ['ok' => false, 'error' => 'Clearance case not found.'];
        }

        if ($clearance['status'] !== 'pending') {
            return ['ok' => false, 'error' => 'This clearance case has already been finalized or denied.'];
        }

        if (! $this->isFullyCleared($clearanceId)) {
            return ['ok' => false, 'error' => 'Every clearance item must be cleared before finalizing graduation.'];
        }

        $studentModel = new StudentModel();
        $result       = $studentModel->changeStatus((int) $clearance['student_id'], 'graduated', 'Graduation clearance completed.', $registrarId);

        if (! $result['ok']) {
            return $result;
        }

        $this->update($clearanceId, ['status' => 'cleared']);

        (new AuditLogModel())->record(
            'clearance.finalized',
            'graduation_clearance',
            $clearanceId,
            null,
            ['status' => 'cleared'],
            null,
            $registrarId,
            'registrar'
        );

        return ['ok' => true];
    }

    /**
     * Queue of in-progress clearance cases with their items, for the
     * registrar's queue view.
     */
    public function queue(): array
    {
        $cases = $this->db->table('graduation_clearances c')
            ->select('c.id, c.student_id, c.academic_year, c.status, c.created_at, s.student_number, u.username')
            ->join('students s', 's.student_id = c.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('c.status', 'pending')
            ->orderBy('c.created_at', 'ASC')
            ->get()
            ->getResultArray();

        return $cases;
    }

    /**
     * Every pending item across every in-progress case whose office_role
     * is in $officeRoles — Finance's cross-student "accounting" queue
     * (distinct from queue()/withItems(), which are Registrar's
     * per-case views).
     *
     * @param list<string> $officeRoles
     */
    public function pendingForOffices(array $officeRoles): array
    {
        if ($officeRoles === []) {
            return [];
        }

        return $this->db->table('graduation_clearance_items i')
            ->select('i.id, i.clearance_id, i.status, r.name AS requirement_name, r.office_role,
                      c.student_id, c.academic_year, s.student_number, u.username')
            ->join('clearance_requirements r', 'r.id = i.requirement_id')
            ->join('graduation_clearances c', 'c.id = i.clearance_id')
            ->join('students s', 's.student_id = c.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('i.status', 'pending')
            ->where('c.status', 'pending')
            ->whereIn('r.office_role', $officeRoles)
            ->orderBy('c.created_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{case: ?array, items: list<array>}
     */
    public function withItems(int $clearanceId): array
    {
        $case = $this->db->table('graduation_clearances c')
            ->select('c.id, c.student_id, c.academic_year, c.status, c.created_at, s.student_number, u.username')
            ->join('students s', 's.student_id = c.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('c.id', $clearanceId)
            ->get()
            ->getRowArray();

        if ($case === null) {
            return ['case' => null, 'items' => []];
        }

        $items = $this->db->table('graduation_clearance_items i')
            ->select('i.id, i.clearance_id, i.status, i.cleared_at, i.remarks, r.name AS requirement_name, r.office_role')
            ->join('clearance_requirements r', 'r.id = i.requirement_id')
            ->where('i.clearance_id', $clearanceId)
            ->orderBy('r.name', 'ASC')
            ->get()
            ->getResultArray();

        return ['case' => $case, 'items' => $items];
    }
}
