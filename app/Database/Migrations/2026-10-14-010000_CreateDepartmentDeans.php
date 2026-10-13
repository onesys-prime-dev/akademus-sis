<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Dean's equivalent of sub_department_schedulers: additive delegation that
 * lets a user be dean of a college other than the one their primary slot
 * (users.department_id) points to, without displacing that primary slot.
 * Empty by default — SchedulingAuthorization::authorizedDepartmentIds()
 * only grants extra access when a row here explicitly says so. Mirrors
 * CreateSubDepartmentSchedulers exactly, one level up the hierarchy
 * (college instead of department).
 */
class CreateDepartmentDeans extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'dept_id' => [
                'type' => 'INT',
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['dept_id', 'user_id']);
        $this->forge->addForeignKey('dept_id', 'departments', 'dept_id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('department_deans');
    }

    public function down()
    {
        $this->forge->dropTable('department_deans');
    }
}
