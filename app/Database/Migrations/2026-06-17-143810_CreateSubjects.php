<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubjects extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'subject_id' => [
                'type'       => 'INT',
                'auto_increment' => true,
            ],

            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'subject_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 225,
            ],

            'units' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,1',
                'default'     => 3.0,
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

        $this->forge->addKey('subject_id', true);
        $this->forge->addUniqueKey('subject_code');
        $this->forge->createTable('subjects');
    }

    public function down()
    {
        $this->forge->dropTable('subjects');
    }
}
