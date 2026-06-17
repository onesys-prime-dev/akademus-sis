<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePrograms extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'program_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'program_code' => [
                'type'           => 'VARCHAR',
                'constraint'     => 20,
            ],

            'program_name' => [
                'type'           => 'VARCHAR',
                'constraint'     => 225,
            ],

            'description' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],

            'is_active' => [
                'type'           => 'TINYINT',
                'constraint'     => 1,
                'default'        => 1,
            ],

            'created_at' => [
                'type'           => 'DATETIME',
                'null'           => true,
            ],

            'updated_at' => [
                'type'           => 'DATETIME',
                'null'           => true,
            ],
        ]);

        $this->forge->addKey('program_id', true);
        $this->forge->addUniqueKey('program_code');
        $this->forge->createTable('programs');
    }

    public function down()
    {
        $this->forge->dropTable('programs');
    }
}
