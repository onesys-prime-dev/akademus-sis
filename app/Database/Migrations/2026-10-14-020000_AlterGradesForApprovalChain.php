<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Widens the grade lifecycle from draft -> submitted -> locked (faculty ->
 * chairperson only) to the full institutional chain: draft -> submitted ->
 * chair_recommended -> dean_noted -> registrar_approved -> locked. Each new
 * state gets its own at/by column pair, following the exact same shape as
 * the existing submitted_at/by and locked_at/by columns — no new table for
 * the state machine itself. Comments at each node are carried by
 * AuditLogModel (append-only, already has actor/timestamp/action/reason),
 * not a new column here.
 */
class AlterGradesForApprovalChain extends Migration
{
    public function up()
    {
        $this->db->query(
            "ALTER TABLE grades MODIFY status ENUM('draft','submitted','chair_recommended','dean_noted','registrar_approved','locked') NOT NULL DEFAULT 'draft'"
        );

        $this->forge->addColumn('grades', [
            'chair_recommended_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'submitted_by'],
            'chair_recommended_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'chair_recommended_at'],
            'dean_noted_at'         => ['type' => 'DATETIME', 'null' => true, 'after' => 'chair_recommended_by'],
            'dean_noted_by'         => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'dean_noted_at'],
            'registrar_approved_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'dean_noted_by'],
            'registrar_approved_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'registrar_approved_at'],
        ]);

        $this->forge->addForeignKey('chair_recommended_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('dean_noted_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('registrar_approved_by', 'users', 'id', '', 'SET NULL');
        $this->forge->processIndexes('grades');
    }

    public function down()
    {
        $this->forge->dropForeignKey('grades', 'grades_chair_recommended_by_foreign');
        $this->forge->dropForeignKey('grades', 'grades_dean_noted_by_foreign');
        $this->forge->dropForeignKey('grades', 'grades_registrar_approved_by_foreign');

        $this->forge->dropColumn('grades', [
            'chair_recommended_at',
            'chair_recommended_by',
            'dean_noted_at',
            'dean_noted_by',
            'registrar_approved_at',
            'registrar_approved_by',
        ]);

        $this->db->query("ALTER TABLE grades MODIFY status ENUM('draft','submitted','locked') NOT NULL DEFAULT 'draft'");
    }
}
