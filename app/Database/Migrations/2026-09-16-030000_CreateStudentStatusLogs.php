<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Append-only audit trail for students.student_status changes — Student
 * Records must never let a status flip silently (see the SIS development
 * roadmap's "Student Records" completion criteria: "Status change is
 * audited").
 */
class CreateStudentStatusLogs extends Migration
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
            'from_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'to_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
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
        $this->forge->addKey('student_id');
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('changed_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('student_status_logs');
    }

    public function down()
    {
        $this->forge->dropTable('student_status_logs');
    }
}
