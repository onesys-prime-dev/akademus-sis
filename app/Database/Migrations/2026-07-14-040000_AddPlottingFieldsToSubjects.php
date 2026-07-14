<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPlottingFieldsToSubjects extends Migration
{
    public function up()
    {
        $this->forge->addColumn('subjects', [
            // Length of one class meeting, in hours (e.g. 1.5).
            'contact_hours' => [
                'type'       => 'DECIMAL',
                'constraint' => '3,1',
                'default'    => 1.5,
                'after'      => 'units',
            ],

            // Laboratory subjects may only be plotted on Saturdays.
            'is_laboratory' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'contact_hours',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('subjects', ['contact_hours', 'is_laboratory']);
    }
}
