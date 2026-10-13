<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The enlistment cart's header / state machine. One in-flight row per
 * (student_id, academic_year) — enforced in StudentEnlistmentModel::
 * openOrCreate(), same invariant shape as GraduationClearanceModel::
 * initiate()'s one-open-case guard. returned_* columns record the most
 * recent bounce-back (from chair, dean, or registrar) when status is sent
 * back to 'draft' — they are overwritten on each new return and cleared on
 * the next submit(), not a history log (AuditLogModel already keeps that).
 */
class CreateStudentEnlistments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'student_id' => [
                'type'     => 'INT',
            ],

            // Composite format, matching sections.academic_year
            // (AcademicYearModel::format()), e.g. "AY 2026 - 2027 · 1st Semester".
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],

            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],

            'submitted_at' => ['type' => 'DATETIME', 'null' => true],

            'chair_recommended_by' => ['type' => 'INT', 'null' => true],
            'chair_recommended_at' => ['type' => 'DATETIME', 'null' => true],
            'chair_notes'          => ['type' => 'TEXT', 'null' => true],

            'dean_noted_by' => ['type' => 'INT', 'null' => true],
            'dean_noted_at' => ['type' => 'DATETIME', 'null' => true],
            'dean_notes'    => ['type' => 'TEXT', 'null' => true],

            'registrar_approved_by' => ['type' => 'INT', 'null' => true],
            'registrar_approved_at' => ['type' => 'DATETIME', 'null' => true],
            'registrar_notes'       => ['type' => 'TEXT', 'null' => true],

            'returned_by'      => ['type' => 'INT', 'null' => true],
            'returned_by_role' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'returned_at'      => ['type' => 'DATETIME', 'null' => true],
            'return_notes'     => ['type' => 'TEXT', 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['student_id', 'academic_year']);
        $this->forge->addKey('status');

        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');

        $this->forge->createTable('student_enlistments');
    }

    public function down()
    {
        $this->forge->dropTable('student_enlistments');
    }
}
