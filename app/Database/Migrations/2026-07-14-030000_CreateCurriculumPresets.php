<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCurriculumPresets extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'preset_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            // One preset per AY + program + year level. Sections of the same
            // year level share the same curriculum, so section is not part
            // of the key.
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'program' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],

            'year_level' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            // JSON array of subject codes included in this curriculum.
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
    }

    public function down()
    {
        $this->forge->dropTable('curriculum_presets');
    }
}
