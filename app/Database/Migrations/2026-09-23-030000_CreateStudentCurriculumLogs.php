<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Append-only audit trail for curriculum (re-)assignment, mirroring
 * student_status_logs. Per the handbook: "version migrations are explicit,
 * approved, audited events" — a student's curriculum assignment is never a
 * silent field edit.
 */
class CreateStudentCurriculumLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'log_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'student_id' => [
                'type' => 'INT',
            ],
            'from_curriculum_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'to_curriculum_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'changed_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('log_id', true);
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('from_curriculum_id', 'curriculum', 'curriculum_id', '', 'SET NULL');
        $this->forge->addForeignKey('to_curriculum_id', 'curriculum', 'curriculum_id', '', 'SET NULL');
        $this->forge->addForeignKey('changed_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('student_curriculum_logs');
    }

    public function down()
    {
        $this->forge->dropTable('student_curriculum_logs');
    }
}
