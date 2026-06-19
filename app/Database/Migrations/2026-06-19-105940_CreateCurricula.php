<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCurricula extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'curriculum_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'program_id' => [
                'type' => 'INT',
            ],

            'curriculum_code' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],

            'curriculum_name' => [
                'type' => 'VARCHAR',
                'constraint' => 225,
            ],

            'academic_year' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],

            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
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

        $this->forge->addKey('curriculum_id', true);

        $this->forge->addForeignKey(
            'program_id',
            'programs',
            'program_id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->createTable('curriculum');
    }

    public function down()
    {
        $this->forge->dropTable('curriculum');
    }
}
