<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per student per subject — the foundation for both per-subject
 * (irregular) and block enrollment. Block enrollment is not a separate
 * mechanism: it's many of these rows inserted together for a cohort.
 *
 * No billing columns yet (out of scope). `enrollment_id` is a real surrogate
 * PK specifically so a later fee-assessment table can FK to it without any
 * restructuring here.
 */
class CreateEnrollments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'enrollment_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            // Shield user id of the student.
            'user_id' => [
                'type' => 'INT',
            ],

            // Which section's roster this enrollment counts against.
            'section_id' => [
                'type' => 'INT',
            ],

            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'method' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'block',
            ],

            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'enrolled',
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

        $this->forge->addKey('enrollment_id', true);
        $this->forge->addKey('user_id');
        $this->forge->addKey('section_id');

        // A student cannot double-enroll in the same subject within a section.
        $this->forge->addUniqueKey(['user_id', 'section_id', 'subject_code']);

        $this->forge->addForeignKey('section_id', 'sections', 'section_id', '', 'CASCADE');

        $this->forge->createTable('enrollments');
    }

    public function down()
    {
        $this->forge->dropTable('enrollments');
    }
}
