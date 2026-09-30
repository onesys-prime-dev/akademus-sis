<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the installment count used to auto-generate a payment_schedules row
 * set, and drops the old flat misc_fee column — now superseded by
 * program_fee_rates (one row per fee_types entry instead of one number).
 */
class AlterTuitionRatesForItemizedFees extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tuition_rates', [
            'installment_count' => [
                'type'       => 'INT',
                'default'    => 1,
                'after'      => 'per_unit_rate',
            ],
        ]);

        $this->forge->dropColumn('tuition_rates', 'misc_fee');
    }

    public function down()
    {
        $this->forge->addColumn('tuition_rates', [
            'misc_fee' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
                'after'      => 'per_unit_rate',
            ],
        ]);

        $this->forge->dropColumn('tuition_rates', 'installment_count');
    }
}
