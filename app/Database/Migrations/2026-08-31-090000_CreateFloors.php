<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Floors previously only existed implicitly (whatever distinct `floor`
 * values happened to be on `rooms`), so an admin could never add an empty
 * floor before it had at least one room. This table makes a floor a real,
 * standalone record — "Add Floor" inserts here directly, no room required.
 */
class CreateFloors extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'floor_id'   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('floor_id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('floors');

        // Backfill from whatever floors already exist via rooms, so nothing
        // already in use disappears from the list once floors becomes the
        // source of truth for "which floors exist".
        $db = db_connect();
        $existing = $db->table('rooms')->select('floor')->distinct()->get()->getResultArray();
        foreach ($existing as $row) {
            $db->table('floors')->insert(['name' => $row['floor']]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('floors');
    }
}
