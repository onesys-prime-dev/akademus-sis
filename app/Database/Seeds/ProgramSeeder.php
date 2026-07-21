<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ProgramSeeder extends Seeder
{
    public function run()
    {
        $deptIds = array_column($this->db->table('departments')->get()->getResultArray(), 'dept_id', 'code');

        $data = [
            [
                'program_code'  => 'BSCS',
                'program_name'  => 'Bachelor of Science in Computer Science',
                'department_id' => $deptIds['CS'] ?? null,
            ],
            [
                'program_code'  => 'BSIT',
                'program_name'  => 'Bachelor of Science in Information Technology',
                'department_id' => $deptIds['IT'] ?? null,
            ],
        ];

        foreach ($data as $program) {
            if ($this->db->table('programs')->where('program_code', $program['program_code'])->countAllResults() === 0) {
                $this->db->table('programs')->insert($program);
                echo "Created program {$program['program_code']}\n";
            }
        }
    }
}
