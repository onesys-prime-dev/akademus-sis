<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * academic_years already got real start_date/end_date ranges
 * (AddDateRangesToAcademicYears) covering the whole term. This adds two
 * narrower sub-windows inside that term: when faculty may submit grades,
 * and when students may register/enroll. Both nullable — a term with no
 * window set is treated as open (see AcademicYearModel::isGradingOpen()),
 * so existing rows/behavior are unaffected until a registrar deliberately
 * sets one.
 */
class AddGradingWindowToAcademicYears extends Migration
{
    public function up()
    {
        $this->forge->addColumn('academic_years', [
            'grading_opens_at'      => ['type' => 'DATETIME', 'null' => true, 'after' => 'end_date'],
            'grading_closes_at'     => ['type' => 'DATETIME', 'null' => true, 'after' => 'grading_opens_at'],
            'registration_opens_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'grading_closes_at'],
            'registration_closes_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'registration_opens_at'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('academic_years', [
            'grading_opens_at', 'grading_closes_at', 'registration_opens_at', 'registration_closes_at',
        ]);
    }
}
