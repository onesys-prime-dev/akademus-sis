<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Standard registrar-level personal details that never existed anywhere in
 * this schema (confirmed before adding this — no date_of_birth/gender/
 * address/emergency-contact columns on `students` or `users`). Bolted onto
 * `students` rather than `users`, since these are student-specific record
 * fields (like student_number, curriculum_id), not shared account identity
 * (first_name/contact_number live on `users` because every role has those —
 * these don't apply to faculty/admin/registrar accounts).
 */
class AddPersonalDetailsToStudents extends Migration
{
    public function up()
    {
        $this->forge->addColumn('students', [
            'date_of_birth' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'student_number',
            ],
            'gender' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'date_of_birth',
            ],
            'home_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'gender',
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'home_address',
            ],
            'emergency_contact_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'city',
            ],
            'emergency_contact_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'emergency_contact_name',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('students', [
            'date_of_birth', 'gender', 'home_address', 'city',
            'emergency_contact_name', 'emergency_contact_number',
        ]);
    }
}
