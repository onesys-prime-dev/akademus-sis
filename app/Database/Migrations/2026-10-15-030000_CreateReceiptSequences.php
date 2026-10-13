<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * One row per OR-number year, locked (SELECT ... FOR UPDATE) and
 * incremented inside the same transaction as the official_receipts insert
 * — the standard safe pattern for a gapless, race-free sequence, since a
 * naive SELECT MAX(or_number)+1 is not safe under concurrent cashiers.
 */
class CreateReceiptSequences extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'year' => [
                'type'       => 'VARCHAR',
                'constraint' => 4,
            ],
            'next_number' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 1,
            ],
        ]);

        $this->forge->addKey('year', true);
        $this->forge->createTable('receipt_sequences');
    }

    public function down()
    {
        $this->forge->dropTable('receipt_sequences');
    }
}
