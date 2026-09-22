<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per enrollment — one final grade per student per subject per
 * section. user_id/subject_code/section_id/offering_id/academic_year are
 * denormalized from the enrollment (same style as faculty_assignments) so
 * roster/report queries don't need a join back to enrollments for the
 * common case. grading_scale_id is a SNAPSHOT of whichever scale was active
 * when the grade was recorded, so a later admin change to the active scale
 * never silently reinterprets a grade that already exists.
 */
class CreateGrades extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'grade_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'enrollment_id' => [
                'type' => 'INT',
            ],
            'user_id' => [
                'type' => 'INT',
            ],
            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'section_id' => [
                'type' => 'INT',
            ],
            'offering_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            'faculty_id' => [
                'type' => 'INT',
            ],
            'grading_scale_id' => [
                'type' => 'INT',
            ],
            // Raw entered value as a string ("1.75", "88.50", "B+") so all
            // three scale types share one column.
            'grade_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'is_passing' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'null'    => true,
            ],
            'remarks' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            // draft | submitted | locked
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'draft',
            ],
            'submitted_at' => ['type' => 'DATETIME', 'null' => true],
            'submitted_by' => ['type' => 'INT', 'null' => true],
            'locked_at'    => ['type' => 'DATETIME', 'null' => true],
            'locked_by'    => ['type' => 'INT', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('grade_id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('section_id');
        $this->forge->addKey('faculty_id');
        $this->forge->addUniqueKey('enrollment_id');

        $this->forge->addForeignKey('enrollment_id', 'enrollments', 'enrollment_id', '', 'CASCADE');
        $this->forge->addForeignKey('grading_scale_id', 'grading_scales', 'scale_id', '', 'RESTRICT');

        $this->forge->createTable('grades');
    }

    public function down()
    {
        $this->forge->dropTable('grades');
    }
}
