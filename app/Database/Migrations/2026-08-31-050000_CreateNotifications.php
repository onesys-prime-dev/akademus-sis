<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Minimal notifications, written at the moments introduced by the
 * chairperson-assigns rework (new assignment, change-request submitted/
 * decided, swap decided) and read on the next page load / long-poll tick —
 * no push, no separate polling loop. The bell icons already existed in
 * several views as decoration; this is what finally wires them up.
 */
class CreateNotifications extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'message' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'link' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'is_read' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'is_read']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('notifications');
    }

    public function down()
    {
        $this->forge->dropTable('notifications');
    }
}
