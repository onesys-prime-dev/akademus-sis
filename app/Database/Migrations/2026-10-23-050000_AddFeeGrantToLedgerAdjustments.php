<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mirrors discount_grant_id's role, but for student_additional_fees grants:
 * links an 'additional_fee' category row back to the grant that produced
 * it, so TuitionAssessmentService::recompute() can tell "already
 * materialized for this term" apart from "needs a new row" idempotently —
 * see ensureAdditionalFeeAdjustments() / LedgerAdjustmentModel::
 * activeForFeeGrant().
 */
class AddFeeGrantToLedgerAdjustments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('ledger_adjustments', [
            'fee_grant_id' => [
                'type'       => 'INT',
                'null'       => true,
                'after'      => 'discount_grant_id',
            ],
        ]);

        $this->db->query('ALTER TABLE ledger_adjustments ADD INDEX ledger_adjustments_fee_grant_id (fee_grant_id)');
        $this->db->query('ALTER TABLE ledger_adjustments ADD CONSTRAINT ledger_adjustments_fee_grant_id_foreign FOREIGN KEY (fee_grant_id) REFERENCES student_additional_fees (id)');

        // category enum needs a new value for this; MySQL enums are changed
        // via MODIFY COLUMN, not an addColumn-style forge call.
        $this->db->query("ALTER TABLE ledger_adjustments MODIFY COLUMN category ENUM('discount','waiver','correction','penalty','other','additional_fee') NOT NULL");
    }

    public function down()
    {
        $this->db->query('ALTER TABLE ledger_adjustments DROP FOREIGN KEY ledger_adjustments_fee_grant_id_foreign');
        $this->db->query('ALTER TABLE ledger_adjustments DROP INDEX ledger_adjustments_fee_grant_id');
        $this->forge->dropColumn('ledger_adjustments', 'fee_grant_id');
        $this->db->query("ALTER TABLE ledger_adjustments MODIFY COLUMN category ENUM('discount','waiver','correction','penalty','other') NOT NULL");
    }
}
