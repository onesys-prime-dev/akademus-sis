<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Once a subject is chairperson-assigned (ChairpersonController::sync()),
 * a faculty member who can't accept it uses this — separate from
 * swap_requests, since a change request is one-directional ("I can't do
 * this, please review") rather than a peer-to-peer trade both parties act on.
 */
class CreateFacultyChangeRequests extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'request_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'assignment_id' => [
                'type' => 'INT',
            ],
            'faculty_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'reason' => [
                'type' => 'TEXT',
            ],
            'proposed_alternative' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'under_review', 'approved', 'rejected', 'cancelled'],
                'default'    => 'pending',
            ],
            'reviewed_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'reviewed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('request_id', true);
        $this->forge->addKey(['assignment_id', 'status']);
        $this->forge->addKey('faculty_id');
        $this->forge->addForeignKey('assignment_id', 'faculty_assignments', 'assignment_id', '', 'CASCADE');
        $this->forge->addForeignKey('faculty_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('faculty_change_requests');
    }

    public function down()
    {
        $this->forge->dropTable('faculty_change_requests');
    }
}
