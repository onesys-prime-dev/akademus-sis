<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Catalog-level rule: "this subject is split into these component types."
 * Read by OfferingComponentModel::instantiateForOffering() at offering-
 * creation time only — editing or deactivating a template never mutates
 * offering_components rows that were already instantiated from it (the
 * same snapshot principle used across this system's catalog tables).
 */
class SubjectComponentTemplateModel extends Model
{
    protected $table      = 'subject_component_templates';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'subject_id',
        'component_type_id',
        'name',
        'is_required',
        'required_hours',
        'default_capacity',
        'preferred_room_type',
        'assignment_rule',
        'sort_order',
        'is_active',
    ];

    protected $useTimestamps = true;

    public const ASSIGNMENT_RULES = ['shared', 'one_of'];

    /**
     * @return list<array<string, mixed>>
     */
    public function forSubject(int $subjectId, bool $activeOnly = true): array
    {
        $query = $this->db->table('subject_component_templates sct')
            ->select('sct.*, ct.code AS component_type_code, ct.name AS component_type_name')
            ->join('component_types ct', 'ct.id = sct.component_type_id')
            ->where('sct.subject_id', $subjectId)
            ->orderBy('sct.sort_order')
            ->orderBy('sct.id');

        if ($activeOnly) {
            $query->where('sct.is_active', 1);
        }

        return $query->get()->getResultArray();
    }

    public function deactivate(int $id): array
    {
        $row = $this->find($id);

        if ($row === null) {
            return ['ok' => false, 'error' => 'Template not found.'];
        }

        $this->update($id, ['is_active' => 0]);

        return ['ok' => true];
    }
}
