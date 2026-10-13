<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Informational/display column only — total_amount continues to be the
 * NET amount owed (tuition + fees - discounts) so every existing reader
 * of balance/total_amount (StudentRecordController, student/tuition view,
 * PaymentScheduleModel::withBalances()) keeps working unmodified.
 * discount_amount is just what the Assessment table shows as a separate
 * "Discounts" line.
 */
class AddDiscountAmountToTuitionAssessments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tuition_assessments', [
            'discount_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
                'after'      => 'tuition_amount',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tuition_assessments', 'discount_amount');
    }
}
