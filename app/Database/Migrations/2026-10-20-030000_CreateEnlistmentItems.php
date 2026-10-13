<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cart lines. No per-item status — the whole cart is reviewed as one unit
 * by each office (chair/dean/registrar), unlike graduation_clearance_items
 * where each item belongs to a different office. Freely added/removed by
 * the student only while the parent student_enlistments.status = 'draft'
 * (enforced in StudentEnlistmentModel, not here). units is a snapshot from
 * subjects.units at add-time so the cap-sum/review display never silently
 * shifts if a subject's unit value is edited elsewhere mid-cycle.
 */
class CreateEnlistmentItems extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'enlistment_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'section_id' => [
                'type' => 'INT',
            ],

            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'units' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0,
            ],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['enlistment_id', 'section_id', 'subject_code']);

        $this->forge->addForeignKey('enlistment_id', 'student_enlistments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('section_id', 'sections', 'section_id', '', 'CASCADE');

        $this->forge->createTable('enlistment_items');
    }

    public function down()
    {
        $this->forge->dropTable('enlistment_items');
    }
}
