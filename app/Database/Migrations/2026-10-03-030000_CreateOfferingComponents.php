<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Step 9: one schedulable instance of a subject's component split (e.g.
 * "Lecture", "Laboratory Group A", "Laboratory Group B") belonging to one
 * course_offering. A template can produce many instances — the FK back to
 * subject_component_templates is nullable (SET NULL) so deleting a
 * template never strands or deletes an already-instantiated offering
 * component; it only loses the (informational) link back to its origin.
 *
 * roster_mode:
 *  - 'inherited' (default): every student enrolled in the parent offering
 *    is automatically a member — no offering_component_students rows
 *    needed, and this is what makes a plain single-Lecture-instance
 *    subject work with zero extra roster bookkeeping.
 *  - 'explicit': membership is exactly the rows in
 *    offering_component_students — required for split groups (e.g. two
 *    lab sections) where "everyone in the parent offering" is wrong.
 *
 * is_active is soft-deactivation, never hard-delete — a deactivated
 * component keeps its historical schedule/roster/faculty rows but is
 * excluded from current coverage, completeness, and conflict checking
 * (see OfferingComponentModel).
 */
class CreateOfferingComponents extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'offering_id' => ['type' => 'INT'],
            'subject_component_template_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'component_type_id' => ['type' => 'INT'],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'is_required' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'required_hours' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,1',
                'null'       => true,
            ],
            'capacity' => [
                'type' => 'INT',
                'null' => true,
            ],
            'preferred_room_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'roster_mode' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'inherited',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('offering_id');
        $this->forge->addKey('subject_component_template_id');
        $this->forge->addKey('component_type_id');

        $this->forge->addForeignKey('offering_id', 'course_offerings', 'offering_id', '', 'CASCADE');
        $this->forge->addForeignKey('subject_component_template_id', 'subject_component_templates', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('component_type_id', 'component_types', 'id', '', '');

        $this->forge->createTable('offering_components');
    }

    public function down()
    {
        $this->forge->dropTable('offering_components');
    }
}
