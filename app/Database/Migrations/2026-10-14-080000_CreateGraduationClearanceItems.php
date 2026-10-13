<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-office sign-off rows for one graduation_clearances case. The
 * registrar's finalizeGraduation() requires every item here to be
 * 'cleared' before the student can be marked graduated.
 */
class CreateGraduationClearanceItems extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'clearance_id' => [
                'type' => 'INT',
            ],
            'requirement_id' => [
                'type' => 'INT',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'cleared', 'denied'],
                'default'    => 'pending',
            ],
            'cleared_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'cleared_at' => ['type' => 'DATETIME', 'null' => true],
            'remarks'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['clearance_id', 'requirement_id']);
        $this->forge->addForeignKey('clearance_id', 'graduation_clearances', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('requirement_id', 'clearance_requirements', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('cleared_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('graduation_clearance_items');
    }

    public function down()
    {
        $this->forge->dropTable('graduation_clearance_items');
    }
}
