<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Curriculum versioning. Per the SIS handbook (Part V / Part XVIII): editing
 * a curriculum in place is the single most common cause of corrupted
 * historical academic records — a subject removed from "the curriculum"
 * silently orphans every past student's completed-subject record. A
 * curriculum must instead be a versioned, immutable-once-published snapshot,
 * with each student pinned to the specific version they were admitted under.
 *
 * `status` (draft|published|archived) gates editability: curriculum_subjects
 * rows may only be added/edited/removed while their parent curriculum is
 * 'draft'. Publishing locks the snapshot. `base_curriculum_id` links every
 * version of "the same curriculum" together (a version's own row for v1,
 * self-referencing) so the lineage can be queried and the "current" version
 * for new admissions can be marked via `is_current`.
 *
 * `is_active` (pre-existing) is left untouched — it remains the simple
 * visibility toggle used by dropdowns elsewhere; it is orthogonal to the
 * new lifecycle/immutability state added here.
 */
class AddVersioningToCurriculum extends Migration
{
    public function up()
    {
        $this->forge->addColumn('curriculum', [
            'version_number' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
                'after'      => 'curriculum_code',
            ],
            'base_curriculum_id' => [
                'type'       => 'INT',
                'null'       => true,
                'after'      => 'version_number',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
                'after'      => 'base_curriculum_id',
            ],
            'is_current' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'status',
            ],
            'published_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'is_current',
            ],
        ]);

        $this->forge->addForeignKey('base_curriculum_id', 'curriculum', 'curriculum_id', '', 'SET NULL');
        $this->forge->addKey(['base_curriculum_id', 'version_number']);
        $this->forge->processIndexes('curriculum');

        // Backfill: every pre-existing curriculum row becomes the root of
        // its own lineage (self-referencing) at version 1. They are left as
        // 'draft' rather than 'published' — nothing has corrupted them yet
        // (curriculum_subjects' edit/update/toggleStatus/destroy were empty
        // stubs until this same change implements them), so this is the
        // last moment it's free to mark them draft instead of guessing they
        // were already meant to be immutable.
        $this->db->query('UPDATE curriculum SET base_curriculum_id = curriculum_id WHERE base_curriculum_id IS NULL');
    }

    public function down()
    {
        $this->forge->dropForeignKey('curriculum', 'curriculum_base_curriculum_id_foreign');
        $this->forge->dropColumn('curriculum', ['version_number', 'base_curriculum_id', 'status', 'is_current', 'published_at']);
    }
}
