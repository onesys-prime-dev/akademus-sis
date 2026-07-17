<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDepartments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'dept_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],

            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
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

        $this->forge->addKey('dept_id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('departments');

        // Chairpersons and faculty belong to a department.
        $this->forge->addColumn('users', [
            'department_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'username',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'department_id');
        $this->forge->dropTable('departments');
    }
}
