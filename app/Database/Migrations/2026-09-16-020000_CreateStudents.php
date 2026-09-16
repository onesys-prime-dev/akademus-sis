<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The real student record `users.section_id` explicitly said SIS didn't have
 * yet (see 2026-07-30-020000_AddSectionIdToUsers.php's docblock). One row per
 * promoted applicant. `applicant_id` is the idempotency key for the Admission
 * -> SIS handoff (StudentAPIController::promote) — it is what stops a retried
 * webhook call from minting a duplicate student.
 */
class CreateStudents extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'student_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            // Shield user id — the account this student record belongs to.
            // unsigned to match users.id exactly (required for the FK below).
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            // Admissions-side applicant id. The idempotency key: promote()
            // must look this up before creating anything.
            'applicant_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],

            // Format STU-{YYYY}-{00001}, generated here, never by Admissions.
            'student_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'program_id' => [
                'type' => 'INT',
                'null' => true,
            ],

            'admission_date' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            // active | inactive | graduated | dropped. Kept as VARCHAR to
            // match the rest of the app's status-column convention (see
            // enrollments.status, payment.status equivalents) rather than an
            // ENUM type.
            'student_status' => [
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

        $this->forge->addKey('student_id', true);
        $this->forge->addUniqueKey('user_id');
        $this->forge->addUniqueKey('applicant_id');
        $this->forge->addUniqueKey('student_number');
        $this->forge->addKey('program_id');

        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('program_id', 'programs', 'program_id', '', 'SET NULL');

        $this->forge->createTable('students');
    }

    public function down()
    {
        $this->forge->dropTable('students');
    }
}
