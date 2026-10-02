<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase A of linking course_offerings to a canonical subject record instead
 * of trusting the denormalized subject_code string alone. Nullable and
 * additive: subject_code is untouched and stays the value every existing
 * read path uses. See the companion backfill migration for Phase B, and
 * CourseOfferingModel::createMerge() for how NEW offerings populate this
 * going forward.
 */
class AddSubjectIdToCourseOfferings extends Migration
{
    public function up()
    {
        $this->forge->addColumn('course_offerings', [
            'subject_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'subject_code',
            ],
        ]);

        $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', '', 'SET NULL');
        $this->forge->processIndexes('course_offerings');
    }

    public function down()
    {
        $this->forge->dropForeignKey('course_offerings', 'course_offerings_subject_id_foreign');
        $this->forge->dropColumn('course_offerings', 'subject_id');
    }
}
