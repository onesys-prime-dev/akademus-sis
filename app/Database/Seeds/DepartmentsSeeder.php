<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DepartmentsSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        // Departments
        foreach ([['code' => 'IT', 'name' => 'IT Department'], ['code' => 'CS', 'name' => 'CS Department']] as $dept) {
            if ($db->table('departments')->where('code', $dept['code'])->countAllResults() === 0) {
                $dept['created_at'] = date('Y-m-d H:i:s');
                $dept['updated_at'] = date('Y-m-d H:i:s');
                $db->table('departments')->insert($dept);
                echo "Created {$dept['code']} department\n";
            }
        }

        $deptIds = array_column($db->table('departments')->get()->getResultArray(), 'dept_id', 'code');

        // Existing test users belong to IT.
        $db->table('users')->whereIn('username', ['chairperson1', 'faculty1', 'faculty2', 'faculty3'])
            ->update(['department_id' => $deptIds['IT']]);
        echo "Assigned chairperson1, faculty1-3 to IT\n";

        // CS-side accounts for cross-department testing.
        $users = auth()->getProvider();

        $accounts = [
            ['username' => 'chairperson2', 'email' => 'chairperson2@test.com', 'password' => 'Chair123!',   'group' => 'chairperson'],
            ['username' => 'faculty4',     'email' => 'faculty4@test.com',     'password' => 'Faculty123!', 'group' => 'faculty'],
        ];

        foreach ($accounts as $account) {
            if ($users->findByCredentials(['email' => $account['email']]) !== null) {
                echo "Skipping {$account['email']} (already exists)\n";
                continue;
            }

            $users->save(['username' => $account['username'], 'active' => 1]);
            $user = $users->findById($users->getInsertID());
            $user->createEmailIdentity(['email' => $account['email'], 'password' => $account['password']]);
            $user->addGroup($account['group']);

            $db->table('users')->where('id', $user->id)->update(['department_id' => $deptIds['CS']]);
            echo "Created {$account['email']} (CS)\n";
        }
    }
}
