<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * `academic_year` on these tables was VARCHAR(20), sized for the old
 * "AY 2025 - 2026" format (14 chars). Once AcademicYearModel::format()
 * started folding semester in ("AY 2025 - 2026 · 1st Semester", ~29
 * chars — "Summer" runs longer still), MySQL was silently truncating every
 * write to 20 characters instead of erroring, so a saved curriculum/
 * schedule/section could never be found again by its own (correct,
 * untruncated) lookup key. Widened generously so no reasonable label +
 * semester combination can overflow it again.
 *
 * `curriculum`'s own academic_year column is NOT touched here — that table
 * is unrelated legacy/orphaned scaffolding (see the curriculum-subject
 * planning notes), not part of the live scheduling engine.
 */
class WidenAcademicYearColumns extends Migration
{
    private const TABLES = ['plotted_schedules', 'faculty_assignments', 'sections', 'curriculum_presets'];

    public function up()
    {
        foreach (self::TABLES as $table) {
            $this->forge->modifyColumn($table, [
                'academic_year' => [
                    'name'       => 'academic_year',
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => false,
                ],
            ]);
        }
    }

    public function down()
    {
        foreach (self::TABLES as $table) {
            $this->forge->modifyColumn($table, [
                'academic_year' => [
                    'name'       => 'academic_year',
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => false,
                ],
            ]);
        }
    }
}
