<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Real lab/non-lab signal for rooms, separate from the free-text `type`
 * label. `type` stays as a display string (e.g. "Computer Laboratory 1");
 * this boolean is what the scheduler actually reads to sort/badge/warn,
 * so behavior never depends on string-matching against `type`.
 */
class AddIsLaboratoryToRooms extends Migration
{
    public function up()
    {
        $this->forge->addColumn('rooms', [
            'is_laboratory' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'type',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('rooms', 'is_laboratory');
    }
}
