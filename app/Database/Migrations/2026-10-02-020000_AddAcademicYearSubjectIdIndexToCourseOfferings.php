<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Supports the new offering-schedule lookup path: "find the offering for
 * subject X this term." Uses subject_id (the canonical identity established
 * in Step 2), not subject_code — course_offerings has no composite index on
 * either today. No (academic_year, subject_code) index is added alongside
 * it: nothing in the new offering-scheduling code looks up an offering by
 * subject_code, so that index isn't justified by an actual query.
 *
 * up()/down() check index existence before acting — see
 * AddDemandQueryIndexes for why (CI4's test harness regresses/re-latests
 * migrations around every test).
 */
class AddAcademicYearSubjectIdIndexToCourseOfferings extends Migration
{
    public function up()
    {
        if (! $this->indexExists('course_offerings', 'academic_year_subject_id')) {
            $this->db->query('ALTER TABLE course_offerings ADD INDEX academic_year_subject_id (academic_year, subject_id)');
        }
    }

    public function down()
    {
        if ($this->indexExists('course_offerings', 'academic_year_subject_id')) {
            $this->db->query('ALTER TABLE course_offerings DROP INDEX academic_year_subject_id');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return $this->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName])->getRowArray() !== null;
    }
}
