<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * No role (student, faculty, admin) has ever had a real name field — every
 * account is displayed by `username` alone (see ChairpersonController::sections()
 * roster query, TestUsersSeeder). Bolted onto `users` directly, following the
 * existing precedent (sub_department_id, employment_status, section_id were
 * all added the same way) rather than introducing a separate profile table.
 */
class AddNameFieldsToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'first_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'username',
            ],
            'middle_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'first_name',
            ],
            'last_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'middle_name',
            ],
            'contact_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'last_name',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['first_name', 'middle_name', 'last_name', 'contact_number']);
    }
}
