<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per student per academic year — the actual bill. Recomputed
 * whenever the student's enrollments change (see EnrollmentModel::enrollOne/
 * drop), from tuition_rates + the student's currently-enrolled subject units.
 * Keyed on academic_year alone (not a separate semester column) because
 * that's the only term granularity sections/enrollments already carry — a
 * semester dimension doesn't exist anywhere else in the schema to join
 * against.
 */
class CreateTuitionAssessments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'assessment_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'student_id' => [
                'type' => 'INT',
            ],

            // Matches sections.academic_year's width exactly (see
            // 2026-08-31-080000_WidenAcademicYearColumns.php).
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],

            'total_units' => [
                'type'       => 'DECIMAL',
                'constraint' => '6,1',
                'default'    => 0,
            ],

            'tuition_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
            ],

            'misc_fee_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
            ],

            'total_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
            ],

            'amount_paid' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
            ],

            'balance' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
            ],

            // unassessed | unpaid | partial | paid. VARCHAR to match the rest
            // of the app's status-column convention (see students.student_status).
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'unassessed',
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

        $this->forge->addKey('assessment_id', true);
        $this->forge->addUniqueKey(['student_id', 'academic_year']);

        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');

        $this->forge->createTable('tuition_assessments');
    }

    public function down()
    {
        $this->forge->dropTable('tuition_assessments');
    }
}
