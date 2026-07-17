<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSwapRequests extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'swap_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            // The assignment being offered to another faculty member.
            'assignment_id' => [
                'type' => 'INT',
            ],

            // Current holder of the load.
            'requester_id' => [
                'type' => 'INT',
            ],

            // Faculty member being asked to take it over.
            'target_id' => [
                'type' => 'INT',
            ],

            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pending', // pending | accepted | declined | cancelled
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

        $this->forge->addKey('swap_id', true);
        $this->forge->addKey('assignment_id');
        $this->forge->addKey('target_id');

        $this->forge->createTable('swap_requests');
    }

    public function down()
    {
        $this->forge->dropTable('swap_requests');
    }
}
