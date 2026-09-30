<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The price list registrar/admin sets per program per academic year — the
 * piece nothing in either app had: a per-unit tuition rate plus a flat misc
 * fee. TuitionAssessmentService reads this to compute a student's assessment;
 * without a row here for a program+year, that student is simply "not yet
 * assessed" rather than erroring.
 */
class CreateTuitionRates extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'rate_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'program_id' => [
                'type' => 'INT',
            ],

            // Matches sections.academic_year's width exactly (see
            // 2026-08-31-080000_WidenAcademicYearColumns.php) — this is
            // looked up with the same formatted string AcademicYearModel
            // produces, e.g. "AY 2025 - 2026 · 1st Semester".
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],

            'per_unit_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
            ],

            'misc_fee' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
            ],

            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('rate_id', true);
        $this->forge->addUniqueKey(['program_id', 'academic_year']);

        $this->forge->addForeignKey('program_id', 'programs', 'program_id', '', 'CASCADE');

        $this->forge->createTable('tuition_rates');
    }

    public function down()
    {
        $this->forge->dropTable('tuition_rates');
    }
}
