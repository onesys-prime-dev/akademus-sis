<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = auth()->getProvider();

        $accounts = [
            ['username' => 'admin1',   'email' => 'admin@test.com',   'password' => 'Admin@123', 'group' => 'admin'],
            ['username' => 'faculty1', 'email' => 'faculty@test.com', 'password' => 'test123!',  'group' => 'faculty'],
            ['username' => 'student1', 'email' => 'student@test.com', 'password' => 'Test123!',  'group' => 'student'],
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
    }
}
