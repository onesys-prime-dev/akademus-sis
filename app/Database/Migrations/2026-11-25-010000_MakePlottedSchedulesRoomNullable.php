<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * plotted_schedules.room was NOT NULL with no default, but the app has
 * always treated an unassigned room as a valid state (the chairperson's
 * auto-plot placeholder inserts room = null, and student/schedule.php
 * already falls back to "Room to be assigned" when it's empty) — the
 * column just never matched that.
 */
class MakePlottedSchedulesRoomNullable extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('plotted_schedules', [
            'room' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->db->query("UPDATE plotted_schedules SET room = '' WHERE room IS NULL");

        $this->forge->modifyColumn('plotted_schedules', [
            'room' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
        ]);
    }
}
