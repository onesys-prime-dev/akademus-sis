<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The schema fix identified in the SIS development roadmap's "Sectioning &
 * Merging" analysis: a `section` today plays two roles at once (the
 * administrative cohort AND the class roster a schedule attaches to), which
 * makes merging two cohorts' shared-subject classes impossible to express.
 *
 * A `course_offering` is the actual class instance. `offering_sections` is
 * the many-to-many join that lets two or more sections point at the same
 * offering for one subject — that IS a merge. Sections and their own
 * curricula/rosters for every OTHER subject are completely untouched.
 */
class CreateCourseOfferings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'offering_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            // Merged capacity ceiling for the class as a whole, independent
            // of any single source section's own capacity. Null = unlimited.
            'capacity' => [
                'type' => 'INT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
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

        $this->forge->addKey('offering_id', true);
        $this->forge->createTable('course_offerings');

        // Join table. subject_code is denormalized from the parent offering
        // on purpose: it lets a single unique key on (subject_code,
        // section_id) enforce the real invariant — a section can only be
        // merged into ONE offering per subject at a time — without a
        // composite FK back into course_offerings just to read subject_code.
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'offering_id' => [
                'type' => 'INT',
            ],
            'section_id' => [
                'type' => 'INT',
            ],
            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['subject_code', 'section_id']);
        $this->forge->addKey('offering_id');
        $this->forge->addForeignKey('offering_id', 'course_offerings', 'offering_id', '', 'CASCADE');
        $this->forge->addForeignKey('section_id', 'sections', 'section_id', '', 'CASCADE');
        $this->forge->createTable('offering_sections');

        // Enrollments gain an optional pointer to the merged offering they
        // actually belong to. `section_id` is kept exactly as-is (a
        // student's home cohort for rostering/admin purposes) — this is
        // purely additive, so every existing enrollment row and every
        // existing query against `enrollments` keeps working unchanged.
        $this->forge->addColumn('enrollments', [
            'offering_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'section_id',
            ],
        ]);
        $this->forge->addForeignKey('offering_id', 'course_offerings', 'offering_id', '', 'SET NULL');
        $this->forge->processIndexes('enrollments');
    }

    public function down()
    {
        $this->forge->dropForeignKey('enrollments', 'enrollments_offering_id_foreign');
        $this->forge->dropColumn('enrollments', 'offering_id');
        $this->forge->dropTable('offering_sections');
        $this->forge->dropTable('course_offerings');
    }
}
