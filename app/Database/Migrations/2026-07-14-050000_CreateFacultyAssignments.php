<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFacultyAssignments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'assignment_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            // Shield user id of the faculty member holding the load.
            'user_id' => [
                'type' => 'INT',
            ],

            // The load's identity: one subject of one section's schedule.
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

            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'method' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'claimed',
            ],

            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'assigned',
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

        $this->forge->addKey('assignment_id', true);
        $this->forge->addKey('user_id');

        // First come, first served: the database itself guarantees a load can
        // only ever be claimed once, no matter how simultaneous the clicks.
        $this->forge->addUniqueKey(['academic_year', 'program', 'year_level', 'section', 'subject_code']);

        $this->forge->createTable('faculty_assignments');
    }

    public function down()
    {
        $this->forge->dropTable('faculty_assignments');
    }
}
