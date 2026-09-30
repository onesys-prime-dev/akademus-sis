<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The dynamic fee catalog — staff add a new fee here once (e.g. "Library",
 * "Retreat Fee") and it becomes available to price per program via
 * program_fee_rates, with no code change. "tuition" itself is deliberately
 * NOT a category here — it's a computed per-unit line, kept on tuition_rates.
 */
class CreateFeeTypes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'fee_type_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            // miscellaneous | other | additional | course
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
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

            'sort_order' => [
                'type'    => 'INT',
                'default' => 0,
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

        $this->forge->addKey('fee_type_id', true);
        $this->forge->addUniqueKey('code');

        $this->forge->createTable('fee_types');
    }

    public function down()
    {
        $this->forge->dropTable('fee_types');
    }
}
