<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Links a plotted schedule to a shared course offering, so a merged class
 * (one offering, many attached sections) gets ONE real schedule instead of
 * one duplicated per participating section.
 *
 * Fully additive/backward-compatible:
 *  - offering_id is nullable. Existing rows keep offering_id = NULL and are
 *    completely untouched — the legacy (academic_year, program, year_level,
 *    section) tuple identity still works exactly as before for them.
 *  - program/year_level/section become nullable, but ONLY for offering-linked
 *    rows (offering_id IS NOT NULL). An offering-linked row has no single
 *    correct section value once multiple sections are attached — storing a
 *    synthetic placeholder string there would be fake data, not real
 *    identity. Existing (legacy) rows are untouched and keep their real
 *    values; nothing narrows or rewrites them.
 *
 * FK uses the default (RESTRICT) delete behavior, deliberately NOT
 * ON DELETE SET NULL: with program/year_level/section nullable, a SET NULL
 * on offering deletion would strand a schedule row with no offering AND no
 * valid legacy identity — a genuinely broken orphan. RESTRICT at the DB
 * level backs the application-level guard added to
 * CourseOfferingModel::unmergeSection() (an offering is never auto-deleted
 * while schedules still reference it).
 */
class AddOfferingIdToPlottedSchedules extends Migration
{
    public function up()
    {
        $this->forge->addColumn('plotted_schedules', [
            'offering_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'schedule_id',
            ],
        ]);

        $this->forge->addForeignKey('offering_id', 'course_offerings', 'offering_id', '', '');
        $this->forge->processIndexes('plotted_schedules');

        $this->forge->modifyColumn('plotted_schedules', [
            'program' => [
                'name'       => 'program',
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'year_level' => [
                'name'       => 'year_level',
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'section' => [
                'name'       => 'section',
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        // Deliberately NOT reverting program/year_level/section back to
        // NOT NULL: CI4's test harness regresses/re-latests migrations
        // around every test, and reverting would fail ("data truncated")
        // the moment any offering-linked (NULL) row exists at that point in
        // the reverse sequence. Nullable is a strict superset of what
        // NOT NULL could store, so leaving it nullable on rollback loses
        // nothing and keeps down() safe to run at any time.
        $this->forge->dropForeignKey('plotted_schedules', 'plotted_schedules_offering_id_foreign');
        $this->forge->dropColumn('plotted_schedules', 'offering_id');
    }
}
