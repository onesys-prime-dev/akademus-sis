<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProgramIdToSubjects extends Migration
{
    public function up()
    {
        $this->forge->addColumn('subjects', [
            'program_id' => [
                'type' => 'INT',
                'null' => true,
                'after' => 'sub_department_id',
            ],
        ]);

        $this->forge->addForeignKey('program_id', 'programs', 'program_id', '', 'SET NULL');
        $this->forge->processIndexes('subjects');

        // Existing subjects predate this column. Each department currently
        // offers exactly one program, so backfill deterministically from
        // that department's sole program.
        $programs = $this->db->table('programs')->get()->getResultArray();
        $bySubDept = [];
        foreach ($programs as $p) {
            $bySubDept[$p['sub_department_id']] = $p['program_id'];
        }

        foreach ($bySubDept as $subDeptId => $programId) {
            $this->db->table('subjects')
                ->where('sub_department_id', $subDeptId)
                ->where('program_id', null)
                ->update(['program_id' => $programId]);
        }
    }

    public function down()
    {
        $this->forge->dropForeignKey('subjects', 'subjects_program_id_foreign');
        $this->forge->dropColumn('subjects', 'program_id');
    }
}
