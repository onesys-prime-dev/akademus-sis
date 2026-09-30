<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The ledger against a tuition_assessments row: one row per payment a
 * registrar/admin records for a student. Never edited or deleted — a
 * correction is a new (possibly negative) row, so the ledger stays an audit
 * trail, matching the convention used by student_status_logs and
 * student_curriculum_logs.
 */
class CreateStudentPayments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'payment_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'assessment_id' => [
                'type' => 'INT',
            ],

            'student_id' => [
                'type' => 'INT',
            ],

            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
            ],

            'payment_date' => [
                'type' => 'DATETIME',
            ],

            'method' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],

            'reference_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],

            // Shield user id of the staff member who recorded the payment.
            'recorded_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],

            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('payment_id', true);
        $this->forge->addKey('assessment_id');
        $this->forge->addKey('student_id');

        $this->forge->addForeignKey('assessment_id', 'tuition_assessments', 'assessment_id', '', 'CASCADE');
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('recorded_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('student_payments');
    }

    public function down()
    {
        $this->forge->dropTable('student_payments');
    }
}
