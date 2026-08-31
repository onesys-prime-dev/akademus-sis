<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddHourFieldsToSubjects extends Migration
{
    public function up()
    {
        $this->forge->addColumn('subjects', [
            // Optional split of `units` into lecture vs laboratory credit.
            // When both are null, `units` is treated as one type per `is_laboratory`.
            'lecture_units' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,1',
                'null'       => true,
                'after'      => 'is_laboratory',
            ],
            'laboratory_units' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,1',
                'null'       => true,
                'after'      => 'lecture_units',
            ],

            // Explicit meetings/week target. Null = not configured (no
            // fixed target, chairperson plots freely up to the global cap).
            'meetings_per_week' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'null'       => true,
                'after'      => 'laboratory_units',
            ],

            // Derived weekly contact-hours target for this subject, recomputed
            // by AcademicHourResolver on every subject save. Never hand-edited.
            'weekly_hours' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,1',
                'null'       => true,
                'after'      => 'meetings_per_week',
            ],
        ]);

        // Backfill every existing row so weekly_hours is populated immediately.
        $resolver = new \App\Libraries\AcademicHourResolver();
        $subjects = $this->db->table('subjects')->select('subject_id, units, contact_hours, is_laboratory, lecture_units, laboratory_units, meetings_per_week')->get()->getResultArray();

        foreach ($subjects as $subject) {
            $weeklyHours = $resolver->weeklyHoursFor($subject);

            $this->db->table('subjects')
                ->where('subject_id', $subject['subject_id'])
                ->update(['weekly_hours' => $weeklyHours]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('subjects', [
            'lecture_units',
            'laboratory_units',
            'meetings_per_week',
            'weekly_hours',
        ]);
    }
}
