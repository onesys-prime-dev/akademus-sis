<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase B: backfill subject_id on existing course_offerings from their
 * subject_code. subjects.subject_code is uniquely constrained, so a match is
 * always exactly-one-or-none — never ambiguous by construction — but this
 * still counts matches explicitly rather than assuming, in case that
 * constraint is ever relaxed later. A row whose subject_code matches no
 * subjects row is left with subject_id = null and reported, never guessed;
 * this migration never fails because of an unmatched legacy row.
 */
class BackfillCourseOfferingsSubjectId extends Migration
{
    public function up()
    {
        $offerings = $this->db->table('course_offerings')
            ->select('offering_id, subject_code')
            ->get()
            ->getResultArray();

        $unmatched = [];

        foreach ($offerings as $offering) {
            $matches = $this->db->table('subjects')
                ->select('subject_id')
                ->where('subject_code', $offering['subject_code'])
                ->get()
                ->getResultArray();

            if (count($matches) !== 1) {
                $unmatched[] = $offering['offering_id'] . ':' . $offering['subject_code'];

                continue;
            }

            $this->db->table('course_offerings')
                ->where('offering_id', $offering['offering_id'])
                ->update(['subject_id' => $matches[0]['subject_id']]);
        }

        if ($unmatched !== []) {
            log_message(
                'info',
                'BackfillCourseOfferingsSubjectId: left subject_id null for unmatched offering:subject_code pairs — {pairs}',
                ['pairs' => implode(', ', $unmatched)]
            );
        }
    }

    public function down()
    {
        $this->db->table('course_offerings')->where('offering_id >', 0)->update(['subject_id' => null]);
    }
}
