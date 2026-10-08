<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Part D of the hierarchy-hardening batch: sections currently identify
 * their Program only by free text (`sections.program`, matched against
 * `programs.program_name`/legacy aliases via DepartmentModel's static
 * helpers — see SchedulingAuthorization::matchSection(), SectionModel::
 * forSubDepartment(), SharedSubjectCoverageService::build()). That's
 * fragile: a Program rename or re-resolution bug silently detaches
 * sections from their department.
 *
 * `program_id` is added NULLABLE and the text field is NOT removed —
 * per the batch's explicit staging requirement, this is additive only.
 * `ON DELETE SET NULL` rather than blocking program deletion: Program
 * deletion is already blocked elsewhere whenever dependents exist (see
 * ProgramController — delete() is disabled in favor of toggleStatus()), so
 * this FK's delete rule is a defensive backstop, not the primary guard.
 */
class AddProgramIdToSections extends Migration
{
    public function up()
    {
        $this->forge->addColumn('sections', [
            'program_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'program',
            ],
        ]);

        $this->db->query('ALTER TABLE sections ADD INDEX idx_sections_program_id (program_id)');
        $this->db->query(
            'ALTER TABLE sections ADD CONSTRAINT fk_sections_program_id FOREIGN KEY (program_id) REFERENCES programs (program_id) ON DELETE SET NULL'
        );
    }

    public function down()
    {
        $this->db->query('ALTER TABLE sections DROP FOREIGN KEY fk_sections_program_id');
        $this->db->query('ALTER TABLE sections DROP INDEX idx_sections_program_id');
        $this->forge->dropColumn('sections', 'program_id');
    }
}
