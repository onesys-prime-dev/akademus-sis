<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'subject_code' => 'CC101',
                'subject_name' => 'Introduction to Computing',
                'units' => 3,
            ],
            [
                'subject_code' => 'CC102',
                'subject_name' => 'Computer Programming 1',
                'units' => 3,
            ],
        ];

        $this->db
            ->table('subjects')
            ->insertBatch($data);
    }
}
