<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The shared-subject request/placement workflow (Step 3 of the shared-subject
 * architecture work). A non-owning scheduler requests that their section be
 * placed into (or an offering created for) a subject another unit owns —
 * they never get direct write access to that unit's course_offerings; the
 * owning scheduler (or an authorized override) decides the actual
 * composition via OfferingPlacementRequestModel.
 *
 * subject_id/section_id/target_offering_id/requested_by/reviewed_by are all
 * canonical FKs, not the subject_code string — Step 2 made subject_id
 * reliable enough to build on directly.
 */
class CreateOfferingPlacementRequests extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'subject_id' => [
                'type' => 'INT',
            ],
            // Snapshot of which unit asked, for the record/audit trail —
            // never trusted for live authorization decisions (those always
            // re-check SchedulingAuthorization at action time).
            'requesting_sub_dept_id' => [
                'type' => 'INT',
            ],
            'requested_by' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'section_id' => [
                'type' => 'INT',
            ],
            'request_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30, // placement | additional_offering | conflict_report
            ],
            'target_offering_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20, // pending | approved | denied | fulfilled | cancelled
                'default'    => 'pending',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
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
            'review_notes' => [
                'type' => 'TEXT',
                'null' => true,
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

        $this->forge->addKey('id', true);
        $this->forge->addKey(['subject_id', 'status']);
        $this->forge->addKey('requesting_sub_dept_id');
        $this->forge->addKey('section_id');
        $this->forge->addKey('status');

        $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', '', 'CASCADE');
        $this->forge->addForeignKey('requesting_sub_dept_id', 'sub_departments', 'sub_dept_id', '', 'CASCADE');
        $this->forge->addForeignKey('requested_by', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('section_id', 'sections', 'section_id', '', 'CASCADE');
        $this->forge->addForeignKey('target_offering_id', 'course_offerings', 'offering_id', '', 'SET NULL');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('offering_placement_requests');
    }

    public function down()
    {
        $this->forge->dropTable('offering_placement_requests');
    }
}
