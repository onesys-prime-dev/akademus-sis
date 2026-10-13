<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One clearance case per student per graduation cycle — the header row.
 * Per-item sign-off lives in graduation_clearance_items (next migration).
 */
class CreateGraduationClearances extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'student_id' => [
                'type' => 'INT',
            ],
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'cleared', 'denied'],
                'default'    => 'pending',
            ],
            'initiated_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('student_id');
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('initiated_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('graduation_clearances');
    }

    public function down()
    {
        $this->forge->dropTable('graduation_clearances');
    }
}
