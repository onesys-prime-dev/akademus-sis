<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call('ProgramSeeder');
        $this->call('SubjectSeeder');
        $this->call('UserSeeder');
        $this->call('CurriculumSeeder');
    }
}