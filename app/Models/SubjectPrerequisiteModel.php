<?php

namespace App\Models;

use CodeIgniter\Model;

class SubjectPrerequisiteModel extends Model
{
    protected $table            = 'subject_prerequisites';
    protected $primaryKey       = 'subject_prerequisite_id';
    protected $allowedFields =
    [
        'subject_id',
        'prerequisite_subject_id'
    ];

    protected $useTimestamps = true;

    /**
     * The prerequisites of one subject, with their code/name for display.
     *
     * @return list<array{subject_prerequisite_id: int|string, subject_id: int|string, subject_code: string, subject_name: string}>
     */
    public function forSubject(int $subjectId): array
    {
        return $this->db->table('subject_prerequisites sp')
            ->select('sp.subject_prerequisite_id, s.subject_id, s.subject_code, s.subject_name')
            ->join('subjects s', 's.subject_id = sp.prerequisite_subject_id')
            ->where('sp.subject_id', $subjectId)
            ->orderBy('s.subject_code')
            ->get()
            ->getResultArray();
    }

    /**
     * Active subjects that could still be added as a prerequisite of
     * $subjectId: not itself, not already a prerequisite, and not one that
     * would create a cycle.
     *
     * @return list<array>
     */
    public function candidatesFor(int $subjectId): array
    {
        $graph   = $this->graph();
        $already = $graph[$subjectId] ?? [];

        $subjects = $this->db->table('subjects')
            ->where('is_active', 1)
            ->where('subject_id !=', $subjectId)
            ->orderBy('subject_code')
            ->get()
            ->getResultArray();

        return array_values(array_filter($subjects, function (array $s) use ($already, $graph, $subjectId): bool {
            $id = (int) $s['subject_id'];

            return ! in_array($id, $already, true) && ! $this->reaches($graph, $id, $subjectId);
        }));
    }

    /**
     * Adds $prerequisiteId as a prerequisite of $subjectId.
     *
     * Rejects self-reference, unknown subjects, duplicates, and anything
     * that would create a cycle (A requires B requires A would make the
     * subject impossible to ever enroll in).
     *
     * @return array{ok: bool, error?: string}
     */
    public function addPrerequisite(int $subjectId, int $prerequisiteId): array
    {
        if ($subjectId === $prerequisiteId) {
            return ['ok' => false, 'error' => 'A subject cannot be its own prerequisite.'];
        }

        $subjects = new SubjectModel();

        if ($subjects->find($subjectId) === null || $subjects->find($prerequisiteId) === null) {
            return ['ok' => false, 'error' => 'Subject not found.'];
        }

        $graph = $this->graph();

        if (in_array($prerequisiteId, $graph[$subjectId] ?? [], true)) {
            return ['ok' => false, 'error' => 'That prerequisite is already set.'];
        }

        if ($this->reaches($graph, $prerequisiteId, $subjectId)) {
            return ['ok' => false, 'error' => 'That would create a circular prerequisite chain.'];
        }

        $this->insert(['subject_id' => $subjectId, 'prerequisite_subject_id' => $prerequisiteId]);

        return ['ok' => true];
    }

    /**
     * Removes one prerequisite of $subjectId.
     */
    public function removePrerequisite(int $subjectId, int $prerequisiteId): bool
    {
        $this->where('subject_id', $subjectId)
            ->where('prerequisite_subject_id', $prerequisiteId)
            ->delete();

        return $this->db->affectedRows() > 0;
    }

    /**
     * The whole prerequisite graph: subject_id => list of prerequisite ids.
     *
     * @return array<int, list<int>>
     */
    private function graph(): array
    {
        $graph = [];

        foreach ($this->db->table('subject_prerequisites')->select('subject_id, prerequisite_subject_id')->get()->getResultArray() as $row) {
            $graph[(int) $row['subject_id']][] = (int) $row['prerequisite_subject_id'];
        }

        return $graph;
    }

    /**
     * Does $from's prerequisite chain (transitively) include $target?
     *
     * @param array<int, list<int>> $graph
     */
    private function reaches(array $graph, int $from, int $target): bool
    {
        $stack   = [$from];
        $visited = [];

        while ($stack !== []) {
            $node = array_pop($stack);

            if ($node === $target) {
                return true;
            }

            if (isset($visited[$node])) {
                continue;
            }
            $visited[$node] = true;

            foreach ($graph[$node] ?? [] as $next) {
                $stack[] = $next;
            }
        }

        return false;
    }
}
