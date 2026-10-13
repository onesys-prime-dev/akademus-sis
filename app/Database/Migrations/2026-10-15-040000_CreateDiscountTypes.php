<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Configurable catalog of discounts/scholarships (Academic Scholarship,
 * Athletic Grant, Sibling Discount, Employee Dependent, ...) — mirrors
 * fee_types/clearance_requirements' existing catalog-table shape exactly.
 */
class CreateDiscountTypes extends Migration
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
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'value_type' => [
                'type'       => 'ENUM',
                'constraint' => ['percentage', 'fixed'],
            ],
            'default_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'sort_order' => [
                'type'     => 'INT',
                'default'  => 0,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('discount_types');
    }

    public function down()
    {
        $this->forge->dropTable('discount_types');
    }
}
