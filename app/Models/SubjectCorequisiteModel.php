<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * A subject that must be taken concurrently with another (not necessarily
 * already passed). Unlike SubjectPrerequisiteModel, a mutual A<->B pair is
 * valid and expected (e.g. a lecture + its lab), so adding one never checks
 * for a cycle the way prerequisites do.
 */
class SubjectCorequisiteModel extends Model
{
    protected $table      = 'subject_corequisites';
    protected $primaryKey = 'subject_corequisite_id';
    protected $allowedFields =
    [
        'subject_id',
        'corequisite_subject_id'
    ];

    protected $useTimestamps = true;

    /**
     * The corequisites of one subject, with their code/name for display.
     *
     * @return list<array{subject_corequisite_id: int|string, subject_id: int|string, subject_code: string, subject_name: string}>
     */
    public function forSubject(int $subjectId): array
    {
        return $this->db->table('subject_corequisites sc')
            ->select('sc.subject_corequisite_id, s.subject_id, s.subject_code, s.subject_name')
            ->join('subjects s', 's.subject_id = sc.corequisite_subject_id')
            ->where('sc.subject_id', $subjectId)
            ->orderBy('s.subject_code')
            ->get()
            ->getResultArray();
    }

    /**
     * Active subjects that could still be added as a corequisite of
     * $subjectId: not itself, not already set. No cycle check — a mutual
     * pair is a normal case for corequisites.
     *
     * @return list<array>
     */
    public function candidatesFor(int $subjectId): array
    {
        $already = array_column(
            $this->where('subject_id', $subjectId)->findAll(),
            'corequisite_subject_id'
        );

        return $this->db->table('subjects')
            ->where('is_active', 1)
            ->where('subject_id !=', $subjectId)
            ->whereNotIn('subject_id', $already === [] ? [0] : $already)
            ->orderBy('subject_code')
            ->get()
            ->getResultArray();
    }

    /**
     * Sets $corequisiteId as a corequisite of $subjectId.
     *
     * Rejects self-reference, unknown subjects, and duplicates. A mutual
     * pairing (A requires B AND B requires A) is valid and must be added as
     * two separate rows — one in each direction.
     *
     * @return array{ok: bool, error?: string}
     */
    public function addCorequisite(int $subjectId, int $corequisiteId): array
    {
        if ($subjectId === $corequisiteId) {
            return ['ok' => false, 'error' => 'A subject cannot be its own corequisite.'];
        }

        $subjects = new SubjectModel();

        if ($subjects->find($subjectId) === null || $subjects->find($corequisiteId) === null) {
            return ['ok' => false, 'error' => 'Subject not found.'];
        }

        $exists = $this->where('subject_id', $subjectId)->where('corequisite_subject_id', $corequisiteId)->first();

        if ($exists !== null) {
            return ['ok' => false, 'error' => 'That corequisite is already set.'];
        }

        $this->insert(['subject_id' => $subjectId, 'corequisite_subject_id' => $corequisiteId]);

        return ['ok' => true];
    }

    /**
     * Removes one corequisite of $subjectId.
     */
    public function removeCorequisite(int $subjectId, int $corequisiteId): bool
    {
        $this->where('subject_id', $subjectId)
            ->where('corequisite_subject_id', $corequisiteId)
            ->delete();

        return $this->db->affectedRows() > 0;
    }
}
