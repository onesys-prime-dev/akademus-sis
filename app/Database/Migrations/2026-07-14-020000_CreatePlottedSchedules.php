<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlottedSchedules extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'schedule_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            // Plotting context: one draft per AY + program + year level + section.
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'program' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],

            'year_level' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'section' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            // Placement. day: 0 = Monday .. 5 = Saturday.
            'day' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
            ],

            'start_time' => [
                'type' => 'TIME',
            ],

            'end_time' => [
                'type' => 'TIME',
            ],

            'time_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],

            // Class details (denormalized for now; curriculum tables can be
            // joined in later).
            'subject_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],

            'subject_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],

            'prof' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],

            'room' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],

            'theme' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'blue',
            ],

            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'published'],
                'default'    => 'draft',
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

        $this->forge->addKey('schedule_id', true);
        $this->forge->addKey(['academic_year', 'program', 'year_level', 'section']);
        $this->forge->addKey(['academic_year', 'day', 'room']);

        $this->forge->createTable('plotted_schedules');
    }

    public function down()
    {
        $this->forge->dropTable('plotted_schedules');
    }
}
