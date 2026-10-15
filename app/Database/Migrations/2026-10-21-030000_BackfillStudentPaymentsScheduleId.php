<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One-time backfill for rows inserted before student_payments.schedule_id
 * existed (see AddScheduleIdToStudentPayments). A proof-approved payment
 * always recorded 'PROOF-{proof_id}' as its reference_no (see
 * TuitionAssessmentService::approveProof()), so the installment the
 * student originally picked can be recovered from payment_proofs instead
 * of being lost as "General Payment". Cash/check/etc. payments recorded
 * directly by a cashier never had a schedule_id to begin with — those are
 * correctly left untouched.
 */
class BackfillStudentPaymentsScheduleId extends Migration
{
    public function up()
    {
        $this->db->query(
            "UPDATE student_payments sp
             JOIN payment_proofs pp ON pp.proof_id = CAST(SUBSTRING(sp.reference_no, 7) AS UNSIGNED)
             SET sp.schedule_id = pp.schedule_id
             WHERE sp.reference_no LIKE 'PROOF-%'
               AND sp.schedule_id IS NULL
               AND pp.schedule_id IS NOT NULL"
        );
    }

    public function down()
    {
        // Data backfill only — not reversible without losing information
        // about which rows were touched.
    }
}
