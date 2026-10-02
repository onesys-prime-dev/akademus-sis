<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Step 9 catalog-level rule: "this subject is split into these component
 * types, each with its own required hours/capacity/room preference." An
 * offering created from a subject that has active templates instantiates
 * one offering_component row per template at creation time (a snapshot —
 * later template edits affect future offerings only, never already-
 * instantiated ones, matching the catalog-snapshot convention used
 * elsewhere in this system).
 *
 * assignment_rule expresses whether a student may belong to multiple
 * instances of this template at once when it's split into several (e.g.
 * two lab groups):
 *  - 'shared'  (default): any number of instances, no exclusivity.
 *  - 'one_of': each current parent-roster student must belong to exactly
 *    one active instance — enforced at assignment time, not just reported
 *    after the fact.
 * Never hard-coded to any particular component type — a Lecture template
 * simply never needs more than one active instance, so its default
 * ('shared') is never meaningfully exercised.
 */
class CreateSubjectComponentTemplates extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'subject_id' => ['type' => 'INT'],
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
            'default_capacity' => [
                'type' => 'INT',
                'null' => true,
            ],
            'preferred_room_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'assignment_rule' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'shared',
            ],
            'sort_order' => [
                'type'       => 'INT',
                'default'    => 0,
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
        $this->forge->addKey('subject_id');
        $this->forge->addKey('component_type_id');

        $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', '', 'CASCADE');
        $this->forge->addForeignKey('component_type_id', 'component_types', 'id', '', '');

        $this->forge->createTable('subject_component_templates');
    }

    public function down()
    {
        $this->forge->dropTable('subject_component_templates');
    }
}
