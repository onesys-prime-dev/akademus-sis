<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pins a student to the specific curriculum *version* they are governed by,
 * so "what does this student need to graduate" is answerable from a stable
 * snapshot rather than whatever the current curriculum_subjects rows happen
 * to say. Nullable: a student can exist (and be promoted) before a program
 * has a published curriculum, or before a registrar has assigned one.
 */
class AddCurriculumIdToStudents extends Migration
{
    public function up()
    {
        $this->forge->addColumn('students', [
            'curriculum_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'program_id',
            ],
        ]);

        $this->forge->addForeignKey('curriculum_id', 'curriculum', 'curriculum_id', '', 'SET NULL');
        $this->forge->processIndexes('students');
    }

    public function down()
    {
        $this->forge->dropForeignKey('students', 'students_curriculum_id_foreign');
        $this->forge->dropColumn('students', 'curriculum_id');
    }
}
