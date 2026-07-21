<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsActiveToSubDepartments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sub_departments', [
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'after'      => 'name',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('sub_departments', 'is_active');
    }
}
