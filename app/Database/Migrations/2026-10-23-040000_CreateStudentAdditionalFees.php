<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One-off, Finance-initiated charges outside the regular curriculum-driven
 * fee structure (program_fee_rates/assessment_items) — campus event fees,
 * penalties, late-enrollment surcharges, etc. Each row is a grant, mirroring
 * student_discounts exactly (status active/revoked, never hard-deleted so
 * the history stays on record); TuitionAssessmentService::recompute()
 * materializes every active grant into a 'debit' ledger_adjustments row the
 * same way it already does for discount grants (see ensureDiscountAdjustments).
 *
 * Unlike a discount grant, academic_year is required here — a one-off fee
 * is inherently tied to a specific term's billing, never an
 * every-term-until-revoked grant.
 */
class CreateStudentAdditionalFees extends Migration
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
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'revoked'],
                'default'    => 'active',
            ],
            'remarks' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            // Shared by every row created together in one bulk-apply action
            // (a UUID-ish token, not a FK) — lets the UI group/show "Applied
            // to 42 students on Oct 23" as one batch instead of 42 separate
            // unrelated-looking rows. Null for a single-student grant.
            'batch_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'granted_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'granted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['student_id', 'academic_year']);
        $this->forge->addKey('batch_key');
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');

        $this->forge->createTable('student_additional_fees');
    }

    public function down()
    {
        $this->forge->dropTable('student_additional_fees');
    }
}
