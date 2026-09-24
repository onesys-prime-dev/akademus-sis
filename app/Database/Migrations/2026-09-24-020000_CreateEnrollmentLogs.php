<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Append-only trail of enrollment status changes (drop / re-enroll).
 *
 * Enrollments are never hard-deleted: dropping flips status to 'dropped'
 * and the row stays as history (SIS handbook: never delete historical
 * academic records). This table records who did it, when and why — the
 * same pattern as student_status_logs / student_curriculum_logs.
 */
class CreateEnrollmentLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'log_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'enrollment_id' => [
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
            // Shield user who made the change (the student themselves for a
            // self-drop, or the registrar/admin). Nulled if that user is removed.
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
        $this->forge->addKey('enrollment_id');
        $this->forge->addForeignKey('enrollment_id', 'enrollments', 'enrollment_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('changed_by', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('enrollment_logs');
    }

    public function down()
    {
        $this->forge->dropTable('enrollment_logs');
    }
}
