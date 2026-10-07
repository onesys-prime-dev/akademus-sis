<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Additive faculty <-> department many-to-many affiliation, replacing
 * users.sub_department_id as the SOLE source of truth for "which department
 * is this faculty member part of" — that column could only ever hold one
 * value, which was wrong the moment a faculty member needed to teach for a
 * second department (see the architecture audit: ChairpersonController::
 * sync() was hard-rejecting exactly that case with "Selected faculty is not
 * a member of your department.").
 *
 * users.sub_department_id is NOT removed or stopped being written — it
 * remains the faculty member's Primary/Home department, kept in sync with
 * whichever row here has is_primary = 1, so every pre-existing read site
 * that still trusts it (faculty-facing TeachingLoads, DepartmentModel::
 * subDeptOfUser(), etc.) keeps working unchanged. This table becomes the
 * authoritative source for the broader question "which departments is this
 * person affiliated with at all" (primary + secondary), which is what the
 * scheduler's faculty-eligibility check and faculty pickers now consult.
 *
 * This is affiliation, not authorization — it answers "can this person
 * teach here," never "can this person manage/schedule here." That remains
 * SchedulingAuthorization's job (sub_department_schedulers), untouched.
 */
class CreateFacultyDepartments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],

            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'sub_dept_id' => [
                'type' => 'INT',
            ],

            'is_primary' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],

            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'active',
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
        $this->forge->addUniqueKey(['user_id', 'sub_dept_id']);
        $this->forge->addKey('user_id');
        $this->forge->addKey('sub_dept_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('sub_dept_id', 'sub_departments', 'sub_dept_id', '', 'CASCADE');
        $this->forge->createTable('faculty_departments');

        // Backfill: one primary affiliation row per faculty user who already
        // has a sub_department_id — scoped to the 'faculty' Shield group
        // only (a chairperson's/dean's own sub_department_id/department_id
        // is a different concept and is left untouched). Idempotent by
        // construction: this only ever runs once as part of this migration,
        // and the (user_id, sub_dept_id) unique key would reject a true
        // duplicate if it were ever re-run by hand.
        $db = $this->db;
        $db->transStart();

        $db->query(
            "INSERT INTO faculty_departments (user_id, sub_dept_id, is_primary, status, created_at, updated_at)
             SELECT DISTINCT u.id, u.sub_department_id, 1, 'active', NOW(), NOW()
             FROM users u
             INNER JOIN auth_groups_users g ON g.user_id = u.id AND g.`group` = 'faculty'
             WHERE u.sub_department_id IS NOT NULL
               AND u.deleted_at IS NULL"
        );

        $db->transComplete();
    }

    public function down()
    {
        $this->forge->dropTable('faculty_departments');
    }
}
