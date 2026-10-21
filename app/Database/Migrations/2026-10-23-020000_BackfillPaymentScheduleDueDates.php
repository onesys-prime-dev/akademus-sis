<?php

namespace App\Database\Migrations;

use App\Services\TuitionAssessmentService;
use CodeIgniter\Database\Migration;

/**
 * One-time backfill for payment_schedules rows created before due-date
 * auto-generation existed (see TuitionAssessmentService::generateSchedule()
 * and ::dueDatesFor()) — every such row was inserted with due_date NULL,
 * which is the "Not set" / blank Due Date column reported by the student
 * and finance billing views. Delegates to
 * TuitionAssessmentService::backfillDueDates() so the date math lives in
 * exactly one place; see that method's docblock for what it does and does
 * not touch.
 */
class BackfillPaymentScheduleDueDates extends Migration
{
    public function up()
    {
        (new TuitionAssessmentService())->backfillDueDates();
    }

    public function down()
    {
        // Data backfill only — not reversible without losing information
        // about which rows were touched.
    }
}
