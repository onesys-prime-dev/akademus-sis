<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    public function run()
    {
        $users = auth()->getProvider();

        $accounts = [
            ['username' => 'admin2',       'email' => 'admin2@test.com',      'password' => 'Admin123!',   'group' => 'admin'],
            ['username' => 'faculty2',     'email' => 'faculty2@test.com',    'password' => 'Faculty123!', 'group' => 'faculty'],
            ['username' => 'chairperson1', 'email' => 'chairperson@test.com', 'password' => 'Chair123!',   'group' => 'chairperson'],
            ['username' => 'student2',     'email' => 'student2@test.com',    'password' => 'Student123!', 'group' => 'student'],
        ];

        foreach ($accounts as $account) {
            if ($users->findByCredentials(['email' => $account['email']]) !== null) {
                echo "Skipping {$account['email']} (already exists)\n";
                continue;
            }

            $users->save([
                'username' => $account['username'],
                'active'   => 1,
            ]);

            $user = $users->findById($users->getInsertID());

            $user->createEmailIdentity([
                'email'    => $account['email'],
                'password' => $account['password'],
            ]);

            $user->addGroup($account['group']);

            echo "Created {$account['email']} ({$account['group']})\n";
        }

        // Reset the existing admin account's password to Admin123
        $admin = $users->findByCredentials(['email' => 'testakademusmentora@gmail.com']);

        if ($admin !== null) {
            $admin->fill(['password' => 'Admin123']);
            $users->save($admin);
            echo "Password reset for testakademusmentora@gmail.com\n";
        }
    }
}
