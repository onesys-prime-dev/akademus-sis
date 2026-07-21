<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAcademicYears extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'ay_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'semester' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],

            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
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

        $this->forge->addKey('ay_id', true);
        $this->forge->createTable('academic_years');
    }

    public function down()
    {
        $this->forge->dropTable('academic_years');
    }
}
