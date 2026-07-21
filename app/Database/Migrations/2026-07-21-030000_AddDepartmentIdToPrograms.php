<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDepartmentIdToPrograms extends Migration
{
    public function up()
    {
        $this->forge->addColumn('programs', [
            'department_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'program_id',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('programs', 'department_id');
    }
}
