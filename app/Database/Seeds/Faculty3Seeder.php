<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class Faculty3Seeder extends Seeder
{
    public function run()
    {
        $users = auth()->getProvider();

        if ($users->findByCredentials(['email' => 'faculty3@test.com']) === null) {
            $users->save(['username' => 'faculty3', 'active' => 1]);
            $user = $users->findById($users->getInsertID());
            $user->createEmailIdentity(['email' => 'faculty3@test.com', 'password' => 'Faculty123!']);
            $user->addGroup('faculty');
            echo "Created faculty3@test.com\n";
        } else {
            echo "faculty3@test.com already exists\n";
        }

        // Give faculty1 a known password so it is usable for testing too.
        $faculty1 = $users->findByCredentials(['email' => 'faculty@test.com']);
        if ($faculty1 !== null) {
            $faculty1->fill(['password' => 'Faculty123!']);
            $users->save($faculty1);
            echo "Password reset for faculty@test.com\n";
        }
    }
}
