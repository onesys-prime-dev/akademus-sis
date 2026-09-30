<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-program, per-academic-year pricing for a fee_types catalog entry. A
 * fee type with no row here for a program simply isn't charged to that
 * program — different programs can carry entirely different fee sets.
 */
class CreateProgramFeeRates extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'program_fee_rate_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'program_id' => [
                'type' => 'INT',
            ],

            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],

            'fee_type_id' => [
                'type' => 'INT',
            ],

            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0,
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

        $this->forge->addKey('program_fee_rate_id', true);
        $this->forge->addUniqueKey(['program_id', 'academic_year', 'fee_type_id']);

        $this->forge->addForeignKey('program_id', 'programs', 'program_id', '', 'CASCADE');
        $this->forge->addForeignKey('fee_type_id', 'fee_types', 'fee_type_id', '', 'CASCADE');

        $this->forge->createTable('program_fee_rates');
    }

    public function down()
    {
        $this->forge->dropTable('program_fee_rates');
    }
}
