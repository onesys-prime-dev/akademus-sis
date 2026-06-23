<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = auth()->getProvider();

        // ADMIN
        $admin = $users->save([
            'username' => 'admin1',
            'active'   => 1,
        ]);

        $adminUser = $users->findById($users->getInsertID());

        $adminUser->createEmailIdentity([
            'email'    => 'admin@test.com',
            'password' => 'Admin@123',
        ]);

        $adminUser->addGroup('admin');

        // FACULTY
        $users->save([
            'username' => 'faculty1',
            'active'   => 1,
        ]);

        $facultyUser = $users->findById($users->getInsertID());

        $facultyUser->createEmailIdentity([
            'email'    => 'faculty@test.com',
            'password' => 'test123!',
        ]);

        $facultyUser->addGroup('faculty');

        // STUDENT
        $users->save([
            'username' => 'student1',
            'active'   => 1,
        ]);

        $studentUser = $users->findById($users->getInsertID());

        $studentUser->createEmailIdentity([
            'email'    => 'student@test.com',
            'password' => 'Test123!',
        ]);

        $studentUser->addGroup('student');
    }
}
