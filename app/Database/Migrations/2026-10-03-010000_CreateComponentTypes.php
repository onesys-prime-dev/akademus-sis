<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Step 9: the catalog of schedulable "parts" a subject can be split into
 * (e.g. Lecture, Laboratory, Clinical/RLE, Individual Instruction) — never
 * hard-coded, so any institution defines its own set. Nothing is seeded
 * here; an admin creates the types this institution actually uses.
 */
class CreateComponentTypes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'name' => [
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
        $this->forge->addUniqueKey('code');

        $this->forge->createTable('component_types');
    }

    public function down()
    {
        $this->forge->dropTable('component_types');
    }
}
