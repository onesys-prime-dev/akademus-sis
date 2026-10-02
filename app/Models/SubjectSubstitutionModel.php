<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Explicit, auditable "substitute may satisfy required" relationships.
 * Directional — see CreateSubjectSubstitutions migration for the full
 * reasoning. All reads for actual rule evaluation go through
 * App\Libraries\SubjectRequirementService, never this model directly, to
 * keep "does X satisfy Y" centralized in one place.
 */
class SubjectSubstitutionModel extends Model
{
    protected $table      = 'subject_substitutions';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'required_subject_id',
        'substitute_subject_id',
        'notes',
        'is_active',
        'created_by',
    ];

    protected $useTimestamps = true;

    /**
     * Creates a new active relationship, or reactivates the existing
     * (inactive) row for the same direction rather than inserting a
     * duplicate — the unique key on (required_subject_id,
     * substitute_subject_id) means a second insert would fail outright, but
     * more importantly, reactivating preserves the original row's history
     * (created_by/created_at) instead of fabricating a new identity for a
     * rule that already existed once.
     *
     * @return array{ok: bool, error?: string, id?: int, reactivated?: bool}
     */
    public function createOrReactivate(int $requiredSubjectId, int $substituteSubjectId, ?string $notes, int $actorId): array
    {
        if ($requiredSubjectId === $substituteSubjectId) {
            return ['ok' => false, 'error' => 'A subject cannot be configured to substitute for itself.'];
        }

        $requiredExists = $this->db->table('subjects')->where('subject_id', $requiredSubjectId)->countAllResults() > 0;
        $substituteExists = $this->db->table('subjects')->where('subject_id', $substituteSubjectId)->countAllResults() > 0;

        if (! $requiredExists || ! $substituteExists) {
            return ['ok' => false, 'error' => 'Both subjects must exist in the catalog.'];
        }

        $existing = $this->where('required_subject_id', $requiredSubjectId)
            ->where('substitute_subject_id', $substituteSubjectId)
            ->first();

        if ($existing !== null) {
            if ((int) $existing['is_active'] === 1) {
                return ['ok' => false, 'error' => 'This relationship is already configured.'];
            }

            $this->update($existing['id'], ['is_active' => 1, 'notes' => $notes]);

            return ['ok' => true, 'id' => (int) $existing['id'], 'reactivated' => true];
        }

        $this->insert([
            'required_subject_id'   => $requiredSubjectId,
            'substitute_subject_id' => $substituteSubjectId,
            'notes'                 => $notes,
            'is_active'             => 1,
            'created_by'            => $actorId,
        ]);

        return ['ok' => true, 'id' => $this->getInsertID(), 'reactivated' => false];
    }

    public function deactivate(int $id): array
    {
        $row = $this->find($id);

        if ($row === null) {
            return ['ok' => false, 'error' => 'Relationship not found.'];
        }

        if ((int) $row['is_active'] === 0) {
            return ['ok' => false, 'error' => 'This relationship is already inactive.'];
        }

        $this->update($id, ['is_active' => 0]);

        return ['ok' => true];
    }

    /**
     * Every active row, joined to both subjects' codes/names for display.
     */
    public function listWithSubjects(): array
    {
        return $this->db->table('subject_substitutions ss')
            ->select('ss.id, ss.notes, ss.is_active, ss.created_at, req.subject_code AS required_code, req.subject_name AS required_name, sub.subject_code AS substitute_code, sub.subject_name AS substitute_name')
            ->join('subjects req', 'req.subject_id = ss.required_subject_id')
            ->join('subjects sub', 'sub.subject_id = ss.substitute_subject_id')
            ->orderBy('ss.is_active', 'DESC')
            ->orderBy('req.subject_code', 'ASC')
            ->get()
            ->getResultArray();
    }
}
