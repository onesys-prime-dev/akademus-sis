<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A permanent snapshot of the school_year/semester the applicant recorded
 * on the Admission side at the time they were promoted — never overwritten
 * afterward. The actual term a student gets SECTIONED into is a separate,
 * later decision (chairperson/registrar), so this is provenance for
 * spotting a mismatch, not a live pointer to "their current term".
 */
class AddIntendedTermToStudents extends Migration
{
    public function up()
    {
        $this->forge->addColumn('students', [
            'intended_school_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'admission_date',
            ],
            'intended_semester' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'intended_school_year',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('students', ['intended_school_year', 'intended_semester']);
    }
}
