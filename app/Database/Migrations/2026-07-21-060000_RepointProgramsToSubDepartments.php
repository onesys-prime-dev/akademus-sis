<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Programs belong to a Department (chairperson-headed), not directly to a
 * College (dean-headed). This creates a default Department per existing
 * College, re-links existing programs to it, and drops the old FK.
 */
class RepointProgramsToSubDepartments extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('sub_department_id', 'programs')) {
            $this->forge->addColumn('programs', [
                'sub_department_id' => [
                    'type'  => 'INT',
                    'null'  => true,
                    'after' => 'department_id',
                ],
            ]);
        }

        $colleges = $this->db->table('departments')->get()->getResultArray();

        foreach ($colleges as $college) {
            $existing = $this->db->table('sub_departments')->where('college_id', $college['dept_id'])->get()->getRowArray();

            if ($existing === null) {
                $this->db->table('sub_departments')->insert([
                    'college_id' => $college['dept_id'],
                    'code'       => $college['code'],
                    'name'       => 'Department of ' . $college['name'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $subDeptId = $this->db->insertID();
            } else {
                $subDeptId = $existing['sub_dept_id'];
            }

            $this->db->table('programs')
                ->where('department_id', $college['dept_id'])
                ->update(['sub_department_id' => $subDeptId]);

            // Chairpersons/faculty already on this college move to its default department.
            $this->db->table('users')
                ->where('department_id', $college['dept_id'])
                ->where('sub_department_id IS NULL', null, false)
                ->update(['sub_department_id' => $subDeptId]);
        }

        $this->forge->dropColumn('programs', 'department_id');
    }

    public function down()
    {
        $this->forge->addColumn('programs', [
            'department_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'program_id',
            ],
        ]);

        $rows = $this->db->table('programs p')
            ->select('p.program_id, s.college_id')
            ->join('sub_departments s', 's.sub_dept_id = p.sub_department_id', 'left')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $this->db->table('programs')->where('program_id', $row['program_id'])->update(['department_id' => $row['college_id']]);
        }

        $this->forge->dropColumn('programs', 'sub_department_id');
    }
}
