<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Extends the existing faculty_assignments table (not a new one) with
 * offering/offering-component identity, so Step 9 component faculty
 * assignment reuses the same table and the same TeachingLoads unit-cap
 * machinery instead of forking it.
 *
 * program/year_level/section become nullable (same reasoning as Step 5's
 * AddOfferingIdToPlottedSchedules): an offering/component-based assignment
 * has no single correct section value once multiple sections are attached,
 * so storing a placeholder there would be fake data.
 *
 * No new DB-level uniqueness is added for the (offering_id,
 * offering_component_id) case — verified empirically that MySQL allows
 * duplicate rows with the same non-NULL offering_id when another column in
 * a composite unique key is NULL, so duplicate prevention for offering-
 * based assignment is application-level (see FacultyAssignmentModel::
 * upsertForOffering()), not a database constraint.
 */
class AddOfferingFieldsToFacultyAssignments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('faculty_assignments', [
            'offering_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'user_id',
            ],
            'offering_component_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'offering_id',
            ],
        ]);

        $this->forge->addForeignKey('offering_id', 'course_offerings', 'offering_id', '', '');
        $this->forge->addForeignKey('offering_component_id', 'offering_components', 'id', '', '');
        $this->forge->processIndexes('faculty_assignments');

        $this->forge->modifyColumn('faculty_assignments', [
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
        // NOT NULL — same reasoning as AddOfferingIdToPlottedSchedules::down().
        $this->forge->dropForeignKey('faculty_assignments', 'faculty_assignments_offering_id_foreign');
        $this->forge->dropForeignKey('faculty_assignments', 'faculty_assignments_offering_component_id_foreign');
        $this->forge->dropColumn('faculty_assignments', ['offering_id', 'offering_component_id']);
    }
}
