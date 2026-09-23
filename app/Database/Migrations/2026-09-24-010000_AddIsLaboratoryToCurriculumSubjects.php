<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lecture/laboratory classification moves onto the versioned curriculum.
 *
 * Until now the per-term "is this subject a lab here?" flag lived on
 * curriculum_preset_subjects, which the chairperson edited term by term.
 * Now that a published curriculum version is the source of truth for what
 * a term offers (see App\Libraries\TermCurriculum), the flag must live on
 * curriculum_subjects so it is versioned and immutable with the rest of
 * the snapshot. NULL means "use the subject's own global default".
 */
class AddIsLaboratoryToCurriculumSubjects extends Migration
{
    public function up()
    {
        $this->forge->addColumn('curriculum_subjects', [
            'is_laboratory' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
                'default'    => null,
                'after'      => 'is_required',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('curriculum_subjects', 'is_laboratory');
    }
}
