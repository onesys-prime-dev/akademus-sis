<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Widens grade_change_requests from a single-step review (faculty files,
 * chairperson approves/rejects once — status pending/approved/rejected) to
 * the full institutional correction chain: registrar issues the form,
 * faculty responds, chairperson recommends, dean recommends, registrar
 * gives final approval. The old reviewed_by/review_notes/reviewed_at/
 * status='pending' columns and values are left untouched for existing rows
 * — GradeModel::requestChange()/approveChangeRequest()/rejectChangeRequest()
 * keep working as a fallback; new requests go through the new columns via
 * GradeChangeRequestModel's new chain methods instead.
 */
class AlterGradeChangeRequestsForApprovalChain extends Migration
{
    public function up()
    {
        $this->db->query(
            "ALTER TABLE grade_change_requests MODIFY status ENUM('pending','approved','rejected','registrar_form_issued','faculty_submitted','chair_recommended','dean_recommended','registrar_final_approved') NOT NULL DEFAULT 'pending'"
        );

        $this->forge->addColumn('grade_change_requests', [
            'issued_by'             => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'status'],
            'issued_at'             => ['type' => 'DATETIME', 'null' => true, 'after' => 'issued_by'],
            'chair_recommended_by'  => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'issued_at'],
            'chair_recommended_at'  => ['type' => 'DATETIME', 'null' => true, 'after' => 'chair_recommended_by'],
            'chair_notes'           => ['type' => 'TEXT', 'null' => true, 'after' => 'chair_recommended_at'],
            'dean_recommended_by'   => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'chair_notes'],
            'dean_recommended_at'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'dean_recommended_by'],
            'dean_notes'            => ['type' => 'TEXT', 'null' => true, 'after' => 'dean_recommended_at'],
            'final_approved_by'     => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'dean_notes'],
            'final_approved_at'     => ['type' => 'DATETIME', 'null' => true, 'after' => 'final_approved_by'],
        ]);

        $this->forge->addForeignKey('issued_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('chair_recommended_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('dean_recommended_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('final_approved_by', 'users', 'id', '', 'SET NULL');
        $this->forge->processIndexes('grade_change_requests');
    }

    public function down()
    {
        $this->forge->dropForeignKey('grade_change_requests', 'grade_change_requests_issued_by_foreign');
        $this->forge->dropForeignKey('grade_change_requests', 'grade_change_requests_chair_recommended_by_foreign');
        $this->forge->dropForeignKey('grade_change_requests', 'grade_change_requests_dean_recommended_by_foreign');
        $this->forge->dropForeignKey('grade_change_requests', 'grade_change_requests_final_approved_by_foreign');

        $this->forge->dropColumn('grade_change_requests', [
            'issued_by', 'issued_at',
            'chair_recommended_by', 'chair_recommended_at', 'chair_notes',
            'dean_recommended_by', 'dean_recommended_at', 'dean_notes',
            'final_approved_by', 'final_approved_at',
        ]);

        $this->db->query("ALTER TABLE grade_change_requests MODIFY status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
    }
}
