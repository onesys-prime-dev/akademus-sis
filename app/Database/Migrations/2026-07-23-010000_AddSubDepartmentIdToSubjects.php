<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSubDepartmentIdToSubjects extends Migration
{
    public function up()
    {
        $this->forge->addColumn('subjects', [
            'sub_department_id' => [
                'type' => 'INT',
                'null' => true,
                'after' => 'subject_id',
            ],
        ]);

        $this->forge->addForeignKey('sub_department_id', 'sub_departments', 'sub_dept_id', '', 'SET NULL');
        $this->forge->processIndexes('subjects');

        // Existing rows predate this column and were entered under the
        // IT department (sub_dept_id 1) — the only chairperson using the
        // catalog at the time.
        $this->db->table('subjects')->where('sub_department_id', null)->update(['sub_department_id' => 1]);
    }

    public function down()
    {
        $this->forge->dropForeignKey('subjects', 'subjects_sub_department_id_foreign');
        $this->forge->dropColumn('subjects', 'sub_department_id');
    }
}
