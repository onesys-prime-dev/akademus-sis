<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The proper curriculum-subject assignment record: one row per
 * (curriculum preset, subject), holding THIS curriculum's lab/lecture
 * classification independently of the subject's own global default on
 * `subjects.is_laboratory`. The same subject can be lecture in one
 * curriculum (year level) and laboratory in another.
 *
 * `curriculum_presets.subject_codes` (JSON) stays as a derived convenience
 * cache of "which codes are in this year" — this table is the source of
 * truth for per-assignment metadata like is_laboratory.
 */
class CreateCurriculumPresetSubjects extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'preset_id' => [
                'type' => 'INT',
            ],
            'subject_id' => [
                'type' => 'INT',
            ],
            'is_laboratory' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['preset_id', 'subject_id']);
        $this->forge->addForeignKey('preset_id', 'curriculum_presets', 'preset_id', '', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', '', 'CASCADE');
        $this->forge->createTable('curriculum_preset_subjects');
    }

    public function down()
    {
        $this->forge->dropTable('curriculum_preset_subjects');
    }
}
