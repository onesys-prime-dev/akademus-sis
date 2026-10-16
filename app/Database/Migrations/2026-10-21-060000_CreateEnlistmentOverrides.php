<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A staff-granted exception letting one specific student enlist in one
 * specific subject for one specific term, bypassing the prerequisite-list
 * exclusion and the corequisite submit-time gate for that subject only —
 * e.g. an irregular/catch-up student a chairperson has cleared in person.
 * See EnlistmentOverrideModel.
 */
class CreateEnlistmentOverrides extends Migration
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
            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
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
        $this->forge->addKey(['student_id', 'subject_code', 'academic_year', 'status']);

        $this->forge->addForeignKey('student_id', 'students', 'student_id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('enlistment_overrides');
    }

    public function down()
    {
        $this->forge->dropTable('enlistment_overrides');
    }
}
