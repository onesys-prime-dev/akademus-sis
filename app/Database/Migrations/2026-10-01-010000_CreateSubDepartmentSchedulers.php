<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Additive delegation: lets a user schedule for a sub-department other than
 * their own (e.g. a second authorized scheduler for a shared-subject unit),
 * without changing what any existing chairperson can already do. Empty by
 * default — SchedulingAuthorization::authorizedSubDeptIds() only grants
 * extra access when a row here explicitly says so.
 */
class CreateSubDepartmentSchedulers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'sub_dept_id' => [
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
        $this->forge->addUniqueKey(['sub_dept_id', 'user_id']);
        $this->forge->addForeignKey('sub_dept_id', 'sub_departments', 'sub_dept_id', '', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('sub_department_schedulers');
    }

    public function down()
    {
        $this->forge->dropTable('sub_department_schedulers');
    }
}
