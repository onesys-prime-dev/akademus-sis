<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRequestedAssignmentToSwaps extends Migration
{
    public function up()
    {
        // Exchange swaps: the requester offers assignment_id and asks for the
        // target's requested_assignment_id in return. NULL = plain handover.
        $this->forge->addColumn('swap_requests', [
            'requested_assignment_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'assignment_id',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('swap_requests', 'requested_assignment_id');
    }
}
