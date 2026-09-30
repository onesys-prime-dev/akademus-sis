<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The itemized, snapshotted breakdown for one tuition_assessments row — one
 * line per program_fee_rates entry charged at the time of assessment.
 * label/amount are snapshots (survive a later rename/repricing of the
 * catalog fee_type), refreshed (delete+reinsert) every time
 * TuitionAssessmentService::recompute() runs, same as tuition_amount already
 * is. fee_type_id is kept nullable so a line survives the catalog entry
 * being deactivated later.
 */
class CreateAssessmentItems extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'assessment_item_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'assessment_id' => [
                'type' => 'INT',
            ],

            'fee_type_id' => [
                'type' => 'INT',
                'null' => true,
            ],

            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],

            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
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

        $this->forge->addKey('assessment_item_id', true);
        $this->forge->addKey('assessment_id');

        $this->forge->addForeignKey('assessment_id', 'tuition_assessments', 'assessment_id', '', 'CASCADE');
        $this->forge->addForeignKey('fee_type_id', 'fee_types', 'fee_type_id', '', 'SET NULL');

        $this->forge->createTable('assessment_items');
    }

    public function down()
    {
        $this->forge->dropTable('assessment_items');
    }
}
