<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Formal correction workflow for an already-locked grade — mirrors
 * faculty_change_requests (2026-08-31-040000) exactly: a one-directional
 * request with a reason, reviewed (approved/rejected) by the same authority
 * that can lock grades (the chairperson), fully audited. A grade is never
 * directly editable once locked; this table is the only path to change one.
 */
class CreateGradeChangeRequests extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'request_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'grade_id' => [
                'type' => 'INT',
            ],
            'requested_by' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'current_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'requested_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'reason' => [
                'type' => 'TEXT',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'approved', 'rejected'],
                'default'    => 'pending',
            ],
            'reviewed_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'review_notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('request_id', true);
        $this->forge->addKey(['grade_id', 'status']);
        $this->forge->addKey('requested_by');
        $this->forge->addForeignKey('grade_id', 'grades', 'grade_id', '', 'CASCADE');
        $this->forge->addForeignKey('requested_by', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('grade_change_requests');
    }

    public function down()
    {
        $this->forge->dropTable('grade_change_requests');
    }
}
