<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * academic_years had no date fields at all — "active" was a pure manual
 * toggle with nothing validating it against a calendar (confirmed: a
 * 2nd-Semester row was hand-flipped active on 2026-09-25, which is wrong
 * for a real September date under a normal Aug-Dec/Jan-May/Jun-Jul
 * calendar). This adds real ranges, backfills sensible ones for the
 * existing rows, and inserts the missing current term (AY 2026-2027 · 1st
 * Semester) so AcademicYearModel::termForDate() has something correct to
 * resolve today to. is_active itself is left untouched here — flipping it
 * is a deliberate follow-up action (AcademicYearModel::syncActiveToDate()),
 * not something a migration should silently do.
 */
class AddDateRangesToAcademicYears extends Migration
{
    public function up()
    {
        $this->forge->addColumn('academic_years', [
            'start_date' => ['type' => 'DATE', 'null' => true, 'after' => 'semester'],
            'end_date'   => ['type' => 'DATE', 'null' => true, 'after' => 'start_date'],
        ]);

        $ranges = [
            ['label' => '2024-2025', 'semester' => '1st',    'start' => '2024-08-01', 'end' => '2024-12-20'],
            ['label' => '2025-2026', 'semester' => '1st',    'start' => '2025-08-01', 'end' => '2025-12-19'],
            ['label' => '2025-2026', 'semester' => '2nd',    'start' => '2026-01-05', 'end' => '2026-05-29'],
            ['label' => '2025-2026', 'semester' => 'Summer', 'start' => '2026-06-01', 'end' => '2026-07-25'],
        ];

        foreach ($ranges as $r) {
            $this->db->table('academic_years')
                ->where('label', $r['label'])
                ->where('semester', $r['semester'])
                ->update(['start_date' => $r['start'], 'end_date' => $r['end']]);
        }

        $exists = $this->db->table('academic_years')
            ->where('label', '2026-2027')
            ->where('semester', '1st')
            ->countAllResults() > 0;

        if (! $exists) {
            $this->db->table('academic_years')->insert([
                'label'      => '2026-2027',
                'semester'   => '1st',
                'is_active'  => 0,
                'start_date' => '2026-08-03',
                'end_date'   => '2026-12-18',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->db->table('academic_years')->where('label', '2026-2027')->where('semester', '1st')->delete();
        $this->forge->dropColumn('academic_years', ['start_date', 'end_date']);
    }
}
