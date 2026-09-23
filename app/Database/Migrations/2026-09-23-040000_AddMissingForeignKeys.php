<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Closes referential-integrity gaps flagged in the gap analysis:
 * grades.user_id / section_id / faculty_id / offering_id and
 * enrollments.user_id had plain indexes (or, for grades.offering_id, no
 * index at all) but no FK constraint — an account or section deletion could
 * silently orphan grade/enrollment rows with no DB-level backstop.
 *
 * grades.user_id/faculty_id and enrollments.user_id were originally created
 * as plain (signed) INT, but users.id is INT UNSIGNED (CodeIgniter Shield's
 * default) — InnoDB requires the referencing and referenced columns to match
 * exactly, including signedness, or CREATE/ALTER with a FOREIGN KEY fails
 * with errno 150. Both columns only ever hold positive IDs, so widening them
 * to UNSIGNED is a pure type-correction with no data-range consequence.
 *
 * onDelete choices:
 *  - user_id / faculty_id -> users.id: RESTRICT. Users are never hard-deleted
 *    by this app today (no delete route exists — UserController has no
 *    delete method), but grade/enrollment history must never be allowed to
 *    silently vanish if that ever changes; RESTRICT forces an explicit
 *    decision instead of a silent cascade.
 *  - section_id -> sections.section_id: CASCADE, matching the existing
 *    enrollments.section_id precedent (2026-07-30-030000_CreateEnrollments).
 *  - offering_id -> course_offerings.offering_id: SET NULL, matching the
 *    existing enrollments.offering_id precedent
 *    (2026-09-16-040000_CreateCourseOfferings) — un-merging a class should
 *    not delete grades, only detach them from the (now-gone) offering.
 */
class AddMissingForeignKeys extends Migration
{
    public function up()
    {
        // 'null' => false is explicit here (not just the type/unsigned
        // change) because CodeIgniter's modifyColumn() resets a column to
        // nullable unless told otherwise — both columns were originally
        // NOT NULL and must stay that way.
        $this->forge->modifyColumn('grades', [
            'user_id'    => ['name' => 'user_id', 'type' => 'INT', 'unsigned' => true, 'null' => false],
            'faculty_id' => ['name' => 'faculty_id', 'type' => 'INT', 'unsigned' => true, 'null' => false],
        ]);

        $this->forge->modifyColumn('enrollments', [
            'user_id' => ['name' => 'user_id', 'type' => 'INT', 'unsigned' => true, 'null' => false],
        ]);

        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('faculty_id', 'users', 'id', '', 'RESTRICT');
        $this->forge->addForeignKey('section_id', 'sections', 'section_id', '', 'CASCADE');
        // No addKey('offering_id') here — an earlier failed run of this same
        // migration already left that index in place (MySQL's ADD KEY step
        // ran before the ADD CONSTRAINT step that failed); adding it again
        // would error with "Duplicate key name". The existing index is fine
        // to build the FK on.
        $this->forge->addForeignKey('offering_id', 'course_offerings', 'offering_id', '', 'SET NULL');
        $this->forge->processIndexes('grades');

        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'RESTRICT');
        $this->forge->processIndexes('enrollments');
    }

    public function down()
    {
        $this->forge->dropForeignKey('grades', 'grades_user_id_foreign');
        $this->forge->dropForeignKey('grades', 'grades_faculty_id_foreign');
        $this->forge->dropForeignKey('grades', 'grades_section_id_foreign');
        $this->forge->dropForeignKey('grades', 'grades_offering_id_foreign');
        $this->forge->dropForeignKey('enrollments', 'enrollments_user_id_foreign');

        $this->forge->modifyColumn('grades', [
            'user_id'    => ['name' => 'user_id', 'type' => 'INT', 'unsigned' => false, 'null' => false],
            'faculty_id' => ['name' => 'faculty_id', 'type' => 'INT', 'unsigned' => false, 'null' => false],
        ]);

        $this->forge->modifyColumn('enrollments', [
            'user_id' => ['name' => 'user_id', 'type' => 'INT', 'unsigned' => false, 'null' => false],
        ]);
    }
}
