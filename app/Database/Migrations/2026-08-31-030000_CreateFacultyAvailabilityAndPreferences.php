<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pre-semester faculty availability and subject preferences, scoped per
 * academic term (ay_id). This is the first real FK usage of academic_years
 * anywhere in the app — deliberately scoped to just these new tables rather
 * than retrofitting the string-keyed (academic_year, program, year_level,
 * section) tuple the existing plotting engine uses everywhere else.
 */
class CreateFacultyAvailabilityAndPreferences extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'availability_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'ay_id' => [
                'type' => 'INT',
            ],
            // 0 = Monday .. 5 = Saturday, matching the plotting grid's days.
            'day_of_week' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
            ],
            // Null start/end = the whole day is marked unavailable.
            'start_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'end_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'is_available' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('availability_id', true);
        $this->forge->addKey(['user_id', 'ay_id']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('ay_id', 'academic_years', 'ay_id', '', 'CASCADE');
        $this->forge->createTable('faculty_availability');

        $this->forge->addField([
            'preference_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'ay_id' => [
                'type' => 'INT',
            ],
            'subject_id' => [
                'type' => 'INT',
            ],
            'preference_level' => [
                'type'       => 'ENUM',
                'constraint' => ['preferred', 'willing', 'qualified'],
                'default'    => 'willing',
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('preference_id', true);
        $this->forge->addUniqueKey(['user_id', 'ay_id', 'subject_id']);
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('ay_id', 'academic_years', 'ay_id', '', 'CASCADE');
        $this->forge->addForeignKey('subject_id', 'subjects', 'subject_id', '', 'CASCADE');
        $this->forge->createTable('faculty_subject_preferences');
    }

    public function down()
    {
        $this->forge->dropTable('faculty_subject_preferences');
        $this->forge->dropTable('faculty_availability');
    }
}
