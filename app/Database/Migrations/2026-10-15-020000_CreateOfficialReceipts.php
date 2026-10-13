<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One receipt per student_payments row, 1:1, generated the moment
 * TuitionAssessmentService::recordPayment() inserts the ledger row. Voiding
 * flips `status` and never deletes — the payment already happened; a void
 * is a flag for "do not honor this receipt", the actual monetary reversal
 * is a separate ledger_adjustments pair. Mirrors generated_documents'
 * immutable-artifact-plus-metadata-row shape from the Registrar module.
 */
class CreateOfficialReceipts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'payment_id' => [
                'type' => 'INT',
            ],
            'or_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'student_id' => [
                'type' => 'INT',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['issued', 'voided'],
                'default'    => 'issued',
            ],
            'issued_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'issued_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'voided_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'voided_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'void_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'stored_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('payment_id');
        $this->forge->addUniqueKey('or_number');
        $this->forge->addKey('student_id');

        $this->forge->addForeignKey('payment_id', 'student_payments', 'payment_id', '', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('issued_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('voided_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('official_receipts');
    }

    public function down()
    {
        $this->forge->dropTable('official_receipts');
    }
}
