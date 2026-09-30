<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mirrors Admissions' applicants.temp_password flag (see
 * AuthController::register() in the Admission Portal): a promoted student's
 * Shield identity is created with a random temp password emailed in
 * plaintext (StudentWelcomeMailer), and this flag forces a "set your
 * password" prompt on their first login until they change it.
 */
class AddMustChangePasswordToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'must_change_password' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'active',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'must_change_password');
    }
}
