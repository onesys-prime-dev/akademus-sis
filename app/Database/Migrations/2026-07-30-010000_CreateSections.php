<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A "section" (one cohort: program + year level + section, for one AY) as a
 * real, stable row — unlike plotted_schedules, whose rows for a context are
 * wholesale deleted and reinserted on every chairperson save. Enrollment
 * needs something durable to point at, and capacity/roster/(later) billing
 * concepts need somewhere to live; the tuple alone gave them nowhere.
 */
class CreateSections extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'section_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

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

            'section' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            // Max distinct students who may be enrolled in this section.
            // Null = unlimited. Chairperson-settable; not derived from room
            // size (a section can meet in different rooms across subjects).
            'capacity' => [
                'type' => 'INT',
                'null' => true,
            ],

            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
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

        $this->forge->addKey('section_id', true);
        $this->forge->addUniqueKey(['academic_year', 'program', 'year_level', 'section']);

        $this->forge->createTable('sections');
    }

    public function down()
    {
        $this->forge->dropTable('sections');
    }
}
