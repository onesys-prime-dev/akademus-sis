<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * misc_fee_amount is superseded by assessment_items — the itemized total is
 * derived by summing those rows instead of one flat stored number.
 */
class DropMiscFeeAmountFromTuitionAssessments extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('tuition_assessments', 'misc_fee_amount');
    }

    public function down()
    {
        $this->forge->addColumn('tuition_assessments', [
            'misc_fee_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
                'after'      => 'tuition_amount',
            ],
        ]);
    }
}
