<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drops the legacy curriculum_presets / curriculum_preset_subjects tables.
 *
 * The chairperson-facing preset system they backed (ChairpersonController::
 * savePreset()/updateSubjectType()/copyCurriculum(), the old "Curriculum"
 * page) has been retired in favor of the formal, versioned curriculum/
 * curriculum_subjects system (CurriculumBuilderController, CurriculumModel),
 * which has been the sole source of truth read by SubjectDemandService and
 * EnrollmentModel::outsideCurriculum() since that migration. Both real
 * programs that ever used presets (BSCS, BSIT) have fully published
 * equivalents in the formal system; no application code references these
 * tables any longer.
 *
 * Deliberately NOT run automatically alongside the code retirement — apply
 * this migration only once that's independently confirmed in this
 * environment too.
 */
class DropCurriculumPresetTables extends Migration
{
    public function up()
    {
        $this->forge->dropTable('curriculum_preset_subjects', true);
        $this->forge->dropTable('curriculum_presets', true);
    }

    public function down()
    {
        $this->forge->addField([
            'preset_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'program' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'year_level' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'subject_codes' => [
                'type' => 'TEXT',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('preset_id', true);
        $this->forge->addUniqueKey(['academic_year', 'program', 'year_level']);
        $this->forge->createTable('curriculum_presets');

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
}
