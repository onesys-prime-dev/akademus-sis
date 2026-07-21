<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubDepartments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'sub_dept_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'college_id' => [
                'type' => 'INT',
            ],

            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],

            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('sub_dept_id', true);
        $this->forge->addForeignKey('college_id', 'departments', 'dept_id', '', 'CASCADE');
        $this->forge->createTable('sub_departments');

        // Chairpersons and faculty belong to a specific department (under a college).
        $this->forge->addColumn('users', [
            'sub_department_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'department_id',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'sub_department_id');
        $this->forge->dropTable('sub_departments');
    }
}
