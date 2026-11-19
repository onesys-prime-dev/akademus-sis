<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A chairperson's own enlistment window for one program, for one term —
 * only consulted in Program-Scoped mode (see EnlistmentWindowResolver).
 * Always a narrowing of the Registrar's academic_years.enlistment_opens_at/
 * enlistment_closes_at, never wider than it: upsert() rejects any value
 * outside that range, and isOpen() is only ever one half of
 * EnlistmentWindowResolver::isOpenFor()'s AND-of-both-windows check, never
 * consulted alone.
 */
class ProgramEnlistmentWindowModel extends Model
{
    protected $table      = 'program_enlistment_windows';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'ay_id', 'program_id', 'enlistment_opens_at', 'enlistment_closes_at', 'updated_by',
    ];

    protected $useTimestamps = true;

    /**
     * @return array{ok: bool, error?: string}
     */
    public function upsert(int $ayId, int $programId, ?string $opensAt, ?string $closesAt, int $updatedBy): array
    {
        $term = (new AcademicYearModel())->find($ayId);

        if ($term === null || ($term['enlistment_opens_at'] === null && $term['enlistment_closes_at'] === null)) {
            return ['ok' => false, 'error' => "The Registrar hasn't opened enlistment for this term yet."];
        }

        if ($opensAt !== null && $closesAt !== null && $opensAt > $closesAt) {
            return ['ok' => false, 'error' => 'Opens must be before closes.'];
        }

        $termOpens  = $term['enlistment_opens_at'];
        $termCloses = $term['enlistment_closes_at'];

        if ($opensAt !== null && (($termOpens !== null && $opensAt < $termOpens) || ($termCloses !== null && $opensAt > $termCloses))) {
            return ['ok' => false, 'error' => "Must fall within the Registrar's window ({$termOpens} – {$termCloses})."];
        }

        if ($closesAt !== null && (($termOpens !== null && $closesAt < $termOpens) || ($termCloses !== null && $closesAt > $termCloses))) {
            return ['ok' => false, 'error' => "Must fall within the Registrar's window ({$termOpens} – {$termCloses})."];
        }

        $existing = $this->where(['ay_id' => $ayId, 'program_id' => $programId])->first();

        $data = [
            'ay_id'                 => $ayId,
            'program_id'            => $programId,
            'enlistment_opens_at'   => $opensAt,
            'enlistment_closes_at'  => $closesAt,
            'updated_by'            => $updatedBy,
        ];

        if ($existing !== null) {
            $this->update($existing['id'], $data);
        } else {
            $this->insert($data);
        }

        return ['ok' => true];
    }

    public function forProgram(int $ayId, int $programId): ?array
    {
        return $this->where(['ay_id' => $ayId, 'program_id' => $programId])->first();
    }

    /**
     * Whether this program's own window is open — never configured means
     * closed (a chair must deliberately open their program's window, same
     * "opt-in" philosophy as AcademicYearModel::isEnlistmentOpen()'s
     * term-level default-closed rule for enlistment specifically).
     */
    public function isOpen(int $ayId, int $programId, ?string $now = null): bool
    {
        $row = $this->forProgram($ayId, $programId);

        if ($row === null) {
            return false;
        }

        if ($row['enlistment_opens_at'] === null && $row['enlistment_closes_at'] === null) {
            return false;
        }

        $now ??= date('Y-m-d H:i:s');

        if ($row['enlistment_opens_at'] !== null && $now < $row['enlistment_opens_at']) {
            return false;
        }

        if ($row['enlistment_closes_at'] !== null && $now > $row['enlistment_closes_at']) {
            return false;
        }

        return true;
    }
}
