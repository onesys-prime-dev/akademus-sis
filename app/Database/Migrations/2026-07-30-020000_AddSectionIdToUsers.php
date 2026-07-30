<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Minimum student->cohort link needed for block enrollment: which section a
 * student currently belongs to. Mirrors the existing users.sub_department_id
 * precedent (added the same way for chairperson/faculty department scoping)
 * rather than introducing a new mapping table.
 *
 * This is a seam, not a student record: it says nothing about a student's
 * enrollment history, status, or anything else a real `students` table would
 * eventually own. Admin-settable only — never trusted from client input.
 */
class AddSectionIdToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'section_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'sub_department_id',
            ],
        ]);

        $this->forge->addForeignKey('section_id', 'sections', 'section_id', '', 'SET NULL');
        $this->forge->processIndexes('users');
    }

    public function down()
    {
        $this->forge->dropForeignKey('users', 'users_section_id_foreign');
        $this->forge->dropColumn('users', 'section_id');
    }
}
