<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The formal debit/credit memo system — the only way to correct a student's
 * ledger once a charge or payment is posted, since student_payments and
 * assessment_items are never edited after the fact. A correction, waiver,
 * penalty, or discount is a new row here (append-only, enforced in
 * LedgerAdjustmentModel the same way AuditLogModel enforces it); a reversal
 * is a new opposite-signed row pointing back at what it corrects via
 * reverses_adjustment_id, never an edit to the original.
 *
 * discount_grant_id links a 'discount' category row back to the
 * student_discounts grant that produced it, so
 * TuitionAssessmentService::recompute() can tell "already applied for this
 * term" apart from "needs a new row" without re-deriving the amount from
 * scratch each time (see CreateStudentDiscounts migration).
 */
class CreateLedgerAdjustments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'assessment_id' => [
                'type' => 'INT',
            ],
            'student_id' => [
                'type' => 'INT',
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['debit', 'credit'],
            ],
            'category' => [
                'type'       => 'ENUM',
                'constraint' => ['discount', 'waiver', 'correction', 'penalty', 'other'],
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'discount_grant_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'reverses_adjustment_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('assessment_id');
        $this->forge->addKey('student_id');
        $this->forge->addKey('discount_grant_id');

        $this->forge->addForeignKey('assessment_id', 'tuition_assessments', 'assessment_id', '', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('reverses_adjustment_id', 'ledger_adjustments', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('ledger_adjustments');
    }

    public function down()
    {
        $this->forge->dropTable('ledger_adjustments');
    }
}
