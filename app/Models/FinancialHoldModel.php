<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Financial holds — see CreateFinancialHolds migration. isOnHold() is the
 * single check EnrollmentModel::enrollOne()/drop() and
 * GeneratedDocumentModel::generate() gate against.
 */
class FinancialHoldModel extends Model
{
    protected $table      = 'financial_holds';
    protected $primaryKey = 'id';

    protected $allowedFields = ['student_id', 'hold_type', 'reason', 'status', 'placed_by', 'placed_at', 'released_by', 'released_at'];

    protected $useTimestamps = false;

    public const TYPES = ['delinquent', 'unpaid_balance', 'manual', 'other'];

    public function place(int $studentId, string $type, string $reason, int $placedBy): array
    {
        if (! in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'error' => 'Invalid hold type.'];
        }

        // Lock the student row so two concurrent place() calls for the same
        // student can't both pass the isOnHold() check before either
        // inserts, which would create two active holds.
        $db     = $this->db;
        $suffix = $db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : '';
        $db->transStart();
        $db->query("SELECT student_id FROM students WHERE student_id = ?{$suffix}", [$studentId]);

        if ($this->isOnHold($studentId)) {
            $db->transComplete();

            return ['ok' => false, 'error' => 'This student already has an active hold.'];
        }

        $this->insert([
            'student_id' => $studentId,
            'hold_type'  => $type,
            'reason'     => $reason,
            'status'     => 'active',
            'placed_by'  => $placedBy,
            'placed_at'  => date('Y-m-d H:i:s'),
        ]);

        $id = $this->getInsertID();
        $db->transComplete();

        if ($db->transStatus() === false) {
            return ['ok' => false, 'error' => 'Could not place this hold. Please retry.'];
        }

        return ['ok' => true, 'id' => $id];
    }

    public function release(int $holdId, int $releasedBy): array
    {
        $hold = $this->find($holdId);

        if ($hold === null) {
            return ['ok' => false, 'error' => 'Hold not found.'];
        }

        if ($hold['status'] === 'released') {
            return ['ok' => false, 'error' => 'This hold is already released.'];
        }

        $this->update($holdId, [
            'status'      => 'released',
            'released_by' => $releasedBy,
            'released_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true];
    }

    public function isOnHold(int $studentId): bool
    {
        return $this->where('student_id', $studentId)->where('status', 'active')->countAllResults() > 0;
    }

    public function activeFor(int $studentId): array
    {
        return $this->where('student_id', $studentId)->where('status', 'active')->orderBy('placed_at', 'DESC')->findAll();
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId)->orderBy('placed_at', 'DESC')->findAll();
    }

    /** Every currently-active hold across every student — the Finance dashboard queue. */
    public function queue(): array
    {
        return $this->db->table('financial_holds h')
            ->select('h.id, h.hold_type, h.reason, h.placed_at, s.student_id, s.student_number, u.username')
            ->join('students s', 's.student_id = h.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->where('h.status', 'active')
            ->orderBy('h.placed_at', 'ASC')
            ->get()
            ->getResultArray();
    }
}
