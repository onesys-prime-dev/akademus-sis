<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Two composite indexes for the shared-subject demand aggregation queries
 * (SubjectDemandService) — both additive, no column/data changes. Each is
 * independently justified by an existing query gap, not speculative:
 *
 *  - curriculum_subjects(semester, subject_id, curriculum_id): the exact
 *    predicate both CurriculumModel::termSubjects() (existing) and
 *    SubjectDemandService's requirement-check hit.
 *  - enrollments(subject_code, status): hit by SubjectDemandService's
 *    assigned/enrolled computation and by several existing roster/capacity
 *    queries that filter on subject_code + status='enrolled'.
 *
 * curriculum_subjects.subject_id carries an FK
 * (curriculum_subjects_subject_id_foreign) but, surprisingly, had no
 * dedicated supporting index of its own — it silently relied on whichever
 * composite index happened to start with subject_id. A plain single-column
 * index is added first so that reliance is real instead of accidental, and
 * so the composite below can be added/dropped independently of it.
 *
 * up()/down() check index existence before acting — CI4's test harness
 * (DatabaseTestTrait with $refresh=true) regresses and re-latests migrations
 * around every test, and this migration needs to be safe to run through that
 * cycle repeatedly without erroring on an index that's already in the
 * desired state.
 */
class AddDemandQueryIndexes extends Migration
{
    public function up()
    {
        if (! $this->indexExists('curriculum_subjects', 'subject_id')) {
            $this->db->query('ALTER TABLE curriculum_subjects ADD INDEX subject_id (subject_id)');
        }

        if (! $this->indexExists('curriculum_subjects', 'semester_subject_id_curriculum_id')) {
            $this->db->query('ALTER TABLE curriculum_subjects ADD INDEX semester_subject_id_curriculum_id (semester, subject_id, curriculum_id)');
        }

        if (! $this->indexExists('enrollments', 'subject_code_status')) {
            $this->db->query('ALTER TABLE enrollments ADD INDEX subject_code_status (subject_code, status)');
        }
    }

    public function down()
    {
        if ($this->indexExists('enrollments', 'subject_code_status')) {
            $this->db->query('ALTER TABLE enrollments DROP INDEX subject_code_status');
        }

        if ($this->indexExists('curriculum_subjects', 'semester_subject_id_curriculum_id')) {
            $this->db->query('ALTER TABLE curriculum_subjects DROP INDEX semester_subject_id_curriculum_id');
        }

        // subject_id is intentionally left in place: once the composite
        // above is gone, it's the sole index backing
        // curriculum_subjects_subject_id_foreign, so MySQL refuses to drop
        // it anyway ("needed in a foreign key constraint") — and rightly
        // so, since that FK previously had no real supporting index at all
        // (see the class docblock). That fix is permanent, not scoped to
        // this feature's reversibility.
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $row = $this->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName])->getRowArray();

        return $row !== null;
    }
}
