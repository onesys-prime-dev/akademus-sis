<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AcademicYearsSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        $years = [
            ['label' => '2025-2026', 'semester' => '1st', 'is_active' => 1],
            ['label' => '2024-2025', 'semester' => '1st', 'is_active' => 0],
        ];

        foreach ($years as $ay) {
            if ($db->table('academic_years')->where('label', $ay['label'])->countAllResults() === 0) {
                $ay['created_at'] = date('Y-m-d H:i:s');
                $ay['updated_at'] = date('Y-m-d H:i:s');
                $db->table('academic_years')->insert($ay);
                echo "Created academic year {$ay['label']} ({$ay['semester']})\n";
            }
        }
    }
}
