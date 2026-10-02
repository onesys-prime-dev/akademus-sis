<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Lets a plotted_schedules row belong to one specific offering COMPONENT
 * (e.g. "Laboratory Group A") instead of the whole offering — fully
 * additive: nullable, existing offering-linked and legacy rows are
 * completely untouched and keep offering_component_id = NULL.
 *
 * RESTRICT (default), not SET NULL, for the same reason Step 5 chose
 * RESTRICT on offering_id: a schedule row losing its component identity
 * out from under it on delete would become ambiguous rather than cleanly
 * gone. Deactivating a component (is_active = 0) is the supported way to
 * retire it without orphaning its schedule rows.
 */
class AddOfferingComponentIdToPlottedSchedules extends Migration
{
    public function up()
    {
        $this->forge->addColumn('plotted_schedules', [
            'offering_component_id' => [
                'type'  => 'INT',
                'null'  => true,
                'after' => 'offering_id',
            ],
        ]);

        $this->forge->addForeignKey('offering_component_id', 'offering_components', 'id', '', '');
        $this->forge->processIndexes('plotted_schedules');
    }

    public function down()
    {
        $this->forge->dropForeignKey('plotted_schedules', 'plotted_schedules_offering_component_id_foreign');
        $this->forge->dropColumn('plotted_schedules', 'offering_component_id');
    }
}
