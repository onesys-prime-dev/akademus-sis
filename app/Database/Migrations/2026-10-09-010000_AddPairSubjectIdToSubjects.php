<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets two subject rows (one lecture, one laboratory) represent a single
 * subject that has both components — e.g. "Data Structures" meets as both
 * a lecture and a lab. Each row stays an ordinary single-type subject (no
 * change to is_laboratory/contact_hours/weekly_hours semantics, so the
 * plotting/scheduling engine needs no changes); pair_subject_id only links
 * the two rows for display/edit-together convenience on the Subject
 * Ownership page.
 *
 * SET NULL on delete: subjects are never hard-deleted in this app (only
 * deactivated), but if that ever changes, the surviving partner should
 * simply become an unpaired subject rather than being blocked from deletion.
 */
class AddPairSubjectIdToSubjects extends Migration
{
    public function up()
    {
        $this->forge->addColumn('subjects', [
            'pair_subject_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'is_laboratory',
            ],
        ]);

        $this->forge->addForeignKey('pair_subject_id', 'subjects', 'subject_id', '', 'SET NULL');
        $this->forge->processIndexes('subjects');
    }

    public function down()
    {
        $this->forge->dropForeignKey('subjects', 'subjects_pair_subject_id_foreign');
        $this->forge->dropColumn('subjects', 'pair_subject_id');
    }
}
