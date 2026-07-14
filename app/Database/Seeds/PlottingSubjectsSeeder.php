<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class PlottingSubjectsSeeder extends Seeder
{
    /**
     * Seeds the plotting subject catalog into the subjects table.
     * Skips codes that already exist, so it is safe to run repeatedly.
     */
    public function run()
    {
        $subjects = [
            ['subject_code' => 'IT101',    'subject_name' => 'Introduction to Computing',   'units' => 3.0, 'contact_hours' => 1.5, 'is_laboratory' => 0],
            ['subject_code' => 'IT102',    'subject_name' => 'Computer Programming 1',      'units' => 3.0, 'contact_hours' => 1.5, 'is_laboratory' => 0],
            ['subject_code' => 'IT103',    'subject_name' => 'Web Systems & Technology',    'units' => 3.0, 'contact_hours' => 1.5, 'is_laboratory' => 0],
            ['subject_code' => 'IT104',    'subject_name' => 'Data Structures',             'units' => 3.0, 'contact_hours' => 3.0, 'is_laboratory' => 0],
            ['subject_code' => 'IT105',    'subject_name' => 'Networking 1',                'units' => 3.0, 'contact_hours' => 1.5, 'is_laboratory' => 0],
            ['subject_code' => 'GE01',     'subject_name' => 'Math in Modern World',        'units' => 3.0, 'contact_hours' => 1.5, 'is_laboratory' => 0],
            ['subject_code' => 'PATHFIT1', 'subject_name' => 'Physical Education',          'units' => 2.0, 'contact_hours' => 1.5, 'is_laboratory' => 0],
            ['subject_code' => 'OOP1',     'subject_name' => 'Object Oriented Programming', 'units' => 3.0, 'contact_hours' => 1.5, 'is_laboratory' => 0],
            ['subject_code' => 'CSLAB',    'subject_name' => 'Programming Laboratory',      'units' => 2.0, 'contact_hours' => 3.0, 'is_laboratory' => 1],
        ];

        $db = $this->db;

        foreach ($subjects as $subject) {
            $exists = $db->table('subjects')->where('subject_code', $subject['subject_code'])->countAllResults() > 0;

            if ($exists) {
                echo "Skipping {$subject['subject_code']} (already exists)\n";
                continue;
            }

            $subject['is_active']  = 1;
            $subject['created_at'] = date('Y-m-d H:i:s');
            $subject['updated_at'] = date('Y-m-d H:i:s');

            $db->table('subjects')->insert($subject);
            echo "Added {$subject['subject_code']}\n";
        }
    }
}
