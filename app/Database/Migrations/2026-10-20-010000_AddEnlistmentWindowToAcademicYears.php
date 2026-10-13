<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Same shape as the existing grading_opens_at/registration_opens_at pair —
 * read by the new AcademicYearModel::isEnlistmentOpen(), which reuses the
 * existing private isWindowOpen() helper verbatim. Null on both columns
 * means open-by-default, matching the other two windows.
 */
class AddEnlistmentWindowToAcademicYears extends Migration
{
    public function up()
    {
        $this->forge->addColumn('academic_years', [
            'enlistment_opens_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'registration_closes_at',
            ],
            'enlistment_closes_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'enlistment_opens_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('academic_years', 'enlistment_opens_at');
        $this->forge->dropColumn('academic_years', 'enlistment_closes_at');
    }
}
