<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Admin-managed, one active scale at a time — same "single active row"
 * shape as AcademicYearModel (see AcademicYearModel::setActive()). A grade
 * row (see CreateGrades) snapshots grading_scale_id at entry time, so
 * switching the active scale later never reinterprets a grade that was
 * already recorded under a different scale.
 */
class CreateGradingScales extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'scale_id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
            ],
            // numeric_gwa | percentage | letter
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            // numeric_gwa / percentage only.
            'min_value' => [
                'type'     => 'DECIMAL',
                'constraint' => '5,2',
                'null'     => true,
            ],
            'max_value' => [
                'type'     => 'DECIMAL',
                'constraint' => '5,2',
                'null'     => true,
            ],
            'passing_value' => [
                'type'     => 'DECIMAL',
                'constraint' => '5,2',
                'null'     => true,
            ],
            // GWA: 1.00 is best, so passing = value <= passing_value.
            // Percentage: higher is best, so passing = value >= passing_value.
            'is_lower_better' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            // letter only. Ordered best -> worst, e.g. "A,B+,B,C+,C,D,F".
            'letter_options' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            // letter only. Comma list treated as failing, e.g. "F".
            'failing_letters' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('scale_id', true);
        $this->forge->createTable('grading_scales');

        // Default scale so grade entry works immediately after migrating,
        // with nothing left unconfigured.
        $this->db->table('grading_scales')->insert([
            'name'            => 'GWA (1.00 - 5.00)',
            'type'            => 'numeric_gwa',
            'min_value'       => 1.00,
            'max_value'       => 5.00,
            'passing_value'   => 3.00,
            'is_lower_better' => 1,
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('grading_scales');
    }
}
