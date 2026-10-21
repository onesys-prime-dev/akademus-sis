<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Marks an assessment computed from the student's CURRICULUM (what their
 * year/term says they should take) rather than real enrollment — shown
 * before any class is actually plotted/enrolled, so a student isn't left
 * staring at "No tuition assessment yet" the moment their account exists.
 * TuitionAssessmentService::recompute() falls back to this whenever actual
 * enrolled units are 0, and clears it the moment real enrollment exists —
 * see that method's own docblock.
 */
class AddIsEstimatedToTuitionAssessments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tuition_assessments', [
            'is_estimated' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'total_units',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tuition_assessments', 'is_estimated');
    }
}
