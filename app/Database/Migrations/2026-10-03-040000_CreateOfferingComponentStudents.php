<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Step 9 explicit-roster membership: canonical users.id, never a section
 * reference — this is exactly what lets two lab groups split from the SAME
 * home section without home-section identity leaking into conflict
 * checking or capacity (see SchedulingConflictService::commitmentsForStudents()).
 *
 * The unique key prevents a student being inserted twice into the SAME
 * component instance; it intentionally does NOT attempt to enforce
 * "exactly one instance of a one_of template" — that rule spans multiple
 * component rows and is enforced in application code
 * (OfferingComponentModel::assignStudent()), not at the DB level.
 */
class CreateOfferingComponentStudents extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'offering_component_id' => ['type' => 'INT'],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['offering_component_id', 'user_id']);
        $this->forge->addKey('user_id');

        $this->forge->addForeignKey('offering_component_id', 'offering_components', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');

        $this->forge->createTable('offering_component_students');
    }

    public function down()
    {
        $this->forge->dropTable('offering_component_students');
    }
}
