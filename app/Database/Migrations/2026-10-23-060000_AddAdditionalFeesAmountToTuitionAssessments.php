<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mirrors discount_amount: the sum of every active 'additional_fee' ledger
 * adjustment currently folded into total_amount, stored so the breakdown UI
 * (student tuition page, Finance views) can show it as its own line without
 * re-deriving it from ledger_adjustments on every render.
 */
class AddAdditionalFeesAmountToTuitionAssessments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tuition_assessments', [
            'additional_fees_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
                'null'       => false,
                'after'      => 'discount_amount',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tuition_assessments', 'additional_fees_amount');
    }
}
