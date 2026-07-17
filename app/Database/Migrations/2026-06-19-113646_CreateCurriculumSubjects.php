<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCurriculumSubjects extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'curriculum_subject_id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],

            'curriculum_id' => [
                'type' => 'INT',
            ],

            'subject_id' => [
                'type' => 'INT',
            ],

            'year_level' => [
                'type'       => 'INT',
                'constraint' => 1,
            ],

            'semester' => [
                'type'       => 'INT',
                'constraint' => 1,
            ],

            'is_required' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],

            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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

        $this->forge->addKey('curriculum_subject_id', true);

        $this->forge->addForeignKey(
            'curriculum_id',
            'curriculum',
            'curriculum_id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'subject_id',
            'subjects',
            'subject_id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->createTable('curriculum_subjects');
    }

    public function down()
    {
        $this->forge->dropTable('curriculum_subjects');
    }
}
