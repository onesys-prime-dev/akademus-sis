<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDetailFieldsToDepartments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('departments', [
            'location' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'name',
            ],
            'established_year' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'location',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('departments', ['location', 'established_year']);
    }
}
