<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A student can have multiple holds over time; only `status` ever
 * transitions (place -> release), never a content edit — same
 * append-only-in-spirit pattern as grades/graduation_clearance_items.
 * Enrollment/drop/document-release gates check isOnHold(), which only
 * ever looks at status='active' rows — history of released holds stays
 * on record for context, never deleted.
 */
class CreateFinancialHolds extends Migration
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
            'hold_type' => [
                'type'       => 'ENUM',
                'constraint' => ['delinquent', 'unpaid_balance', 'manual', 'other'],
            ],
            'reason' => [
                'type' => 'TEXT',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'released'],
                'default'    => 'active',
            ],
            'placed_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'placed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'released_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'released_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('student_id');
        $this->forge->addKey('status');

        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('placed_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('released_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('financial_holds');
    }

    public function down()
    {
        $this->forge->dropTable('financial_holds');
    }
}
