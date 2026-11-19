<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A staff-granted exception letting one specific student use the
 * self-enlistment wizard for one specific term regardless of the global
 * enlistment window's open/closed state — e.g. an irregular student who
 * needs to enlist outside the normal cohort schedule. Sibling to
 * enlistment_overrides (subject eligibility), but gates window TIMING
 * instead. See EnlistmentWindowOverrideModel.
 */
class CreateEnlistmentWindowOverrides extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'student_id' => [
                'type' => 'INT',
            ],
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'reason' => [
                'type' => 'TEXT',
            ],
            'granted_by' => [
                'type' => 'INT',
            ],
            'granted_by_role' => [
                'type'       => 'ENUM',
                'constraint' => ['chairperson', 'registrar'],
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'revoked'],
                'default'    => 'active',
            ],
            'revoked_by' => [
                'type' => 'INT',
                'null' => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey(['student_id', 'academic_year', 'status']);

        $this->forge->addForeignKey('student_id', 'students', 'student_id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('enlistment_window_overrides');
    }

    public function down()
    {
        $this->forge->dropTable('enlistment_window_overrides');
    }
}
