<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-program enlistment windows, used only in Program-Scoped mode (see
 * docs/superpowers/specs/2026-11-19-enlistment-window-scoping-design.md).
 * One row per (ay_id, program_id), upserted in place — the Registrar's
 * academic_years.enlistment_opens_at/enlistment_closes_at stays the outer
 * bound a program's own window can only narrow, never widen.
 */
class CreateProgramEnlistmentWindows extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'ay_id' => [
                'type' => 'INT',
            ],
            'program_id' => [
                'type' => 'INT',
            ],
            'enlistment_opens_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'enlistment_closes_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_by' => [
                'type' => 'INT',
                'null' => true,
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

        $this->forge->addKey('id', true);
        $this->forge->addKey(['ay_id', 'program_id'], false, true);

        $this->forge->addForeignKey('ay_id', 'academic_years', 'ay_id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('program_id', 'programs', 'program_id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('program_enlistment_windows');
    }

    public function down()
    {
        $this->forge->dropTable('program_enlistment_windows');
    }
}
