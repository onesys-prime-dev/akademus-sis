<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Sections stop being recreated every semester. Historically
 * `sections.academic_year` stored the combined "AY 2026 - 2027 · 1st
 * Semester" string, so the same cohort (e.g. IT1, 1st Year) got a brand-new
 * row each term. From here on `sections.academic_year` means the school
 * year only ("AY 2026 - 2027") — one row per program/year-level/section for
 * the whole year, roster/capacity included. `plotted_schedules` and
 * `faculty_assignments` are untouched: they don't FK to section_id, they
 * match the legacy (academic_year, program, year_level, section) tuple by
 * string, and they keep storing the combined term-scoped string — the
 * actual class schedule still varies per semester under the same section.
 *
 * Because `enrollments` never had its own semester identity (it inherited
 * one purely through sections.academic_year), this also adds
 * enrollments.academic_year and backfills it from each enrollment's current
 * section BEFORE the merge below destroys that string — losing this value
 * is the one way this migration could introduce a real regression
 * (StudentEnrollment::isActiveYear()-style checks silently always-true).
 */
class MergeSectionsToSchoolYear extends Migration
{
    public function up()
    {
        $this->forge->addColumn('enrollments', [
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'after'      => 'section_id',
            ],
        ]);

        $this->db->query(
            'UPDATE enrollments e
             JOIN sections s ON s.section_id = e.section_id
             SET e.academic_year = s.academic_year
             WHERE e.academic_year IS NULL'
        );

        $this->mergeDuplicateSections();
    }

    /**
     * Groups existing sections rows by (program, year_level, section, year
     * label stripped of its "· Nth Semester" suffix), and for every group
     * with more than one row: repoints every FK that targets
     * sections.section_id from the losing row(s) onto a surviving row, then
     * deletes the losers. The survivor's academic_year is rewritten to the
     * label-only value and its capacity becomes the MAX of the group's
     * non-null capacities (never shrinking what any already-admitted
     * student was counted against).
     */
    private function mergeDuplicateSections(): void
    {
        $rows = $this->db->query(
            "SELECT section_id, program, year_level, section, academic_year, capacity,
                    TRIM(SUBSTRING_INDEX(academic_year, '\u{00B7}', 1)) AS year_only
             FROM sections
             ORDER BY program, year_level, section, section_id"
        )->getResultArray();

        $groups = [];
        foreach ($rows as $row) {
            $key              = $row['program'] . '|' . $row['year_level'] . '|' . $row['section'] . '|' . $row['year_only'];
            $groups[$key][]   = $row;
        }

        // Every section_id -> section_id FK in this schema, discovered
        // rather than hardcoded so a future table with its own section_id
        // FK is covered automatically.
        $fkColumns = $this->db->query(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'sections'
               AND REFERENCED_COLUMN_NAME = 'section_id'"
        )->getResultArray();

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, static fn (array $a, array $b): int => $a['section_id'] <=> $b['section_id']);

            $survivor = array_shift($group);
            $survivorId = (int) $survivor['section_id'];

            $capacity = $survivor['capacity'] !== null ? (int) $survivor['capacity'] : null;
            foreach ($group as $loser) {
                if ($loser['capacity'] !== null) {
                    $capacity = $capacity === null ? (int) $loser['capacity'] : max($capacity, (int) $loser['capacity']);
                }
            }

            foreach ($group as $loser) {
                $loserId = (int) $loser['section_id'];

                foreach ($fkColumns as $fk) {
                    $table  = $fk['TABLE_NAME'];
                    $column = $fk['COLUMN_NAME'];

                    // UPDATE IGNORE: a row that would collide with an
                    // existing survivor-pointed row under a unique key
                    // (e.g. enrollments' (user_id, section_id,
                    // subject_code)) is left pointing at the loser — it's a
                    // duplicate of data the survivor already has, so it's
                    // safe to drop outright afterward instead of repointed.
                    $this->db->query(
                        "UPDATE IGNORE `{$table}` SET `{$column}` = ? WHERE `{$column}` = ?",
                        [$survivorId, $loserId]
                    );
                    $this->db->query("DELETE FROM `{$table}` WHERE `{$column}` = ?", [$loserId]);
                }

                $this->db->query('DELETE FROM sections WHERE section_id = ?', [$loserId]);
            }

            $this->db->query(
                'UPDATE sections SET academic_year = ?, capacity = ? WHERE section_id = ?',
                [$survivor['year_only'], $capacity, $survivorId]
            );
        }

        // Groups of exactly one row still need academic_year rewritten to
        // the label-only form even though there was nothing to merge.
        $this->db->query(
            "UPDATE sections SET academic_year = TRIM(SUBSTRING_INDEX(academic_year, '\u{00B7}', 1))
             WHERE academic_year LIKE '%\u{00B7}%'"
        );
    }

    /**
     * Not meaningfully reversible: once duplicate section rows are merged,
     * which original row absorbed which FKs is lost. Left as a no-op
     * rather than a fake/partial reversal that would misrepresent what
     * happened.
     */
    public function down()
    {
    }
}
