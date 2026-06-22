<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubjectPrerequisites extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'subject_prerequisite_id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],

            'subject_id' => [
                'type' => 'INT'
            ],

            'prerequisite_subject_id' => [
                'type' => 'INT'
            ],

            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ]
        ]);

        $this->forge->addKey('subject_prerequisite_id', true);

        $this->forge->addUniqueKey(
            ['subject_id', 'prerequisite_subject_id'],
            'uq_subject_prerequisite'
        );


        $this->forge->addForeignKey(
            'subject_id',
            'subjects',
            'subject_id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'prerequisite_subject_id',
            'subjects',
            'subject_id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->createTable('subject_prerequisites');
    }

    public function down()
    {
        $this->forge->dropTable('subject_prerequisites');
    }
}
