<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Configurable catalog of graduation clearance items (Library, Accounting,
 * Department Chair, Registrar, ...) — analogous to fee_types being the
 * configurable catalog behind tuition_assessments. office_role is a
 * free-text label naming which office clears this item; it is NOT a
 * foreign key to a Shield group, since some clearing offices (Library,
 * Guidance) have no SIS account/role at all and are cleared by the
 * registrar on their behalf.
 */
class CreateClearanceRequirements extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'office_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
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
        $this->forge->createTable('clearance_requirements');

        $this->db->table('clearance_requirements')->insertBatch([
            ['name' => 'Library',            'office_role' => 'library',    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'Accounting',         'office_role' => 'accounting', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'Department Chair',   'office_role' => 'chairperson', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
            ['name' => 'Registrar',          'office_role' => 'registrar',  'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('clearance_requirements');
    }
}
