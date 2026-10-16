<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A subject that must be taken CONCURRENTLY with another — unlike
 * subject_prerequisites, a mutual A<->B pair is a normal, expected case here
 * (e.g. a lecture and its lab), so no cycle-prevention is enforced at the
 * model layer the way SubjectPrerequisiteModel::reaches() does.
 */
class CreateSubjectCorequisites extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'subject_corequisite_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'subject_id' => [
                'type' => 'INT',
            ],

            'corequisite_subject_id' => [
                'type' => 'INT',
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

        $this->forge->addKey('subject_corequisite_id', true);

        $this->forge->addUniqueKey(
            ['subject_id', 'corequisite_subject_id'],
            'uq_subject_corequisite'
        );

        $this->forge->addForeignKey(
            'subject_id',
            'subjects',
            'subject_id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'corequisite_subject_id',
            'subjects',
            'subject_id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->createTable('subject_corequisites');
    }

    public function down()
    {
        $this->forge->dropTable('subject_corequisites');
    }
}
