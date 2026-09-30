<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The installment plan for one assessment — generated once (see
 * TuitionAssessmentService::recompute()) by splitting total_amount evenly
 * across tuition_rates.installment_count, remainder folded into the last
 * row. Deliberately never regenerated after creation: a later rate
 * correction changes total_amount/balance but must not reshuffle a payment
 * plan a student may already be paying against. Each row's remaining
 * balance is computed at render time (FIFO against payments), not stored.
 */
class CreatePaymentSchedules extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'schedule_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'assessment_id' => [
                'type' => 'INT',
            ],

            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],

            'due_date' => [
                'type' => 'DATE',
                'null' => true,
            ],

            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],

            'sort_order' => [
                'type'    => 'INT',
                'default' => 0,
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

        $this->forge->addKey('schedule_id', true);
        $this->forge->addKey('assessment_id');

        $this->forge->addForeignKey('assessment_id', 'tuition_assessments', 'assessment_id', '', 'CASCADE');

        $this->forge->createTable('payment_schedules');
    }

    public function down()
    {
        $this->forge->dropTable('payment_schedules');
    }
}
