<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Records which installment a payment was credited toward, so payment
 * history can show "Downpayment" / "3rd Payment" / etc. instead of only a
 * lump amount. Nullable: a payment not tied to one installment (cashier's
 * general-payment path) has none, same as the payment_proofs.schedule_id
 * it mirrors.
 */
class AddScheduleIdToStudentPayments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('student_payments', [
            'schedule_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'assessment_id',
            ],
        ]);

        $this->db->query('ALTER TABLE student_payments ADD INDEX idx_student_payments_schedule_id (schedule_id)');
        $this->db->query(
            'ALTER TABLE student_payments ADD CONSTRAINT fk_student_payments_schedule_id FOREIGN KEY (schedule_id) REFERENCES payment_schedules (schedule_id) ON DELETE SET NULL'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE student_payments DROP FOREIGN KEY fk_student_payments_schedule_id');
        $this->db->query('ALTER TABLE student_payments DROP INDEX idx_student_payments_schedule_id');
        $this->forge->dropColumn('student_payments', 'schedule_id');
    }
}
