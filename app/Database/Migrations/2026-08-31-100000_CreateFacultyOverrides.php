<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Audit trail for "Proceed Anyway" — when a chairperson knowingly assigns a
 * faculty member outside their marked availability. Kept separate from
 * faculty_assignments (which just holds the current state) so the override
 * record survives even if the assignment is later changed or removed.
 */
class CreateFacultyOverrides extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'override_id'     => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'chairperson_id'  => ['type' => 'INT', 'unsigned' => true],
            'faculty_id'      => ['type' => 'INT', 'unsigned' => true],
            'academic_year'   => ['type' => 'VARCHAR', 'constraint' => 60],
            'program'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'year_level'      => ['type' => 'VARCHAR', 'constraint' => 20],
            'section'         => ['type' => 'VARCHAR', 'constraint' => 30],
            'subject_code'    => ['type' => 'VARCHAR', 'constraint' => 20],
            'day'             => ['type' => 'TINYINT', 'unsigned' => true],
            'start_time'      => ['type' => 'VARCHAR', 'constraint' => 5],
            'end_time'        => ['type' => 'VARCHAR', 'constraint' => 5],
            'conflict_type'   => ['type' => 'VARCHAR', 'constraint' => 30], // 'unavailable' | 'schedule_conflict'
            'reason'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('override_id');
        $this->forge->addKey('faculty_id');
        $this->forge->addKey('chairperson_id');
        $this->forge->createTable('faculty_assignment_overrides');
    }

    public function down()
    {
        $this->forge->dropTable('faculty_assignment_overrides');
    }
}
