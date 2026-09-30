<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Student-uploaded proof of payment awaiting staff review. Deliberately
 * separate from student_payments (the real ledger) — a proof never touches
 * amount_paid/balance until a staff member approves it, at which point
 * TuitionAssessmentService::approveProof() writes the actual ledger row.
 * stored_filename is a randomized name (never the original), and the file
 * is only ever served through an authenticated, ownership-checked
 * controller action (OWASP File Upload Cheat Sheet, cited in the SIS
 * handbook) — never a public URL into writable/uploads/.
 */
class CreatePaymentProofs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'proof_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'student_id' => [
                'type' => 'INT',
            ],

            'assessment_id' => [
                'type' => 'INT',
            ],

            'schedule_id' => [
                'type' => 'INT',
                'null' => true,
            ],

            'stored_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],

            'original_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],

            'remarks' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            // pending | approved | rejected
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pending',
            ],

            'cashier_remarks' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            'reviewed_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],

            'reviewed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('proof_id', true);
        $this->forge->addKey('student_id');
        $this->forge->addKey('assessment_id');
        $this->forge->addKey('status');

        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('assessment_id', 'tuition_assessments', 'assessment_id', '', 'CASCADE');
        $this->forge->addForeignKey('schedule_id', 'payment_schedules', 'schedule_id', '', 'SET NULL');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('payment_proofs');
    }

    public function down()
    {
        $this->forge->dropTable('payment_proofs');
    }
}
