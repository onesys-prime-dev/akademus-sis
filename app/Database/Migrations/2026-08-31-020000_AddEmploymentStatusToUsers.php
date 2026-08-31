<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Full-time/part-time status for faculty — used for display in the
 * chairperson faculty panel and as an optional input to a part-time
 * workload cap (Academic.partTimeMaxUnits), if the institution sets one.
 * Null for non-faculty accounts, where it doesn't apply.
 */
class AddEmploymentStatusToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'employment_status' => [
                'type'       => 'ENUM',
                'constraint' => ['full_time', 'part_time'],
                'null'       => true,
                'after'      => 'active',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'employment_status');
    }
}
