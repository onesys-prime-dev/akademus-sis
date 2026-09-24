<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Foundations for dynamic sectioning — purely additive, no existing column or
 * row is changed:
 *
 *  - capacity_policies: min/target/max/overflow at system, program(+year
 *    level), subject or offering scope. Resolved most-specific-first by
 *    SectionPolicyResolver; sections.capacity stays the per-section override.
 *  - sections: origin (auto|manual), auto_balance switch and an optional
 *    per-section minimum. Existing rows keep status 'active' and become
 *    manual sections that the balancer only touches on request.
 *  - audit_logs: the generic append-only trail the SIS handbook calls for
 *    (actor, role, action, entity, before/after, reason, IP, timestamp).
 */
class AddDynamicSectioning extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sections', [
            'origin' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'default'    => 'manual',
            ],
            'auto_balance' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'min_capacity' => [
                'type' => 'INT',
                'null' => true,
            ],
        ]);

        $this->forge->addField([
            'policy_id' => ['type' => 'INT', 'auto_increment' => true],
            // system | program | subject | offering
            'scope_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            // program name / subject code / offering id; '' for system.
            'scope_id'   => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
            // Only meaningful for program scope; '' = every year level.
            'year_level' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => ''],
            'min_capacity'    => ['type' => 'INT', 'null' => true],
            'target_capacity' => ['type' => 'INT', 'null' => true],
            'max_capacity'    => ['type' => 'INT', 'null' => true],
            'overflow_tolerance' => ['type' => 'INT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('policy_id', true);
        $this->forge->addUniqueKey(['scope_type', 'scope_id', 'year_level']);
        $this->forge->createTable('capacity_policies');

        $this->forge->addField([
            'log_id'      => ['type' => 'INT', 'auto_increment' => true],
            'actor_id'    => ['type' => 'INT', 'null' => true],
            'actor_role'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'entity_type' => ['type' => 'VARCHAR', 'constraint' => 40],
            'entity_id'   => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'before_json' => ['type' => 'TEXT', 'null' => true],
            'after_json'  => ['type' => 'TEXT', 'null' => true],
            'reason'      => ['type' => 'TEXT', 'null' => true],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('log_id', true);
        $this->forge->addKey(['entity_type', 'entity_id']);
        $this->forge->createTable('audit_logs');
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs');
        $this->forge->dropTable('capacity_policies');
        $this->forge->dropColumn('sections', ['origin', 'auto_balance', 'min_capacity']);
    }
}
