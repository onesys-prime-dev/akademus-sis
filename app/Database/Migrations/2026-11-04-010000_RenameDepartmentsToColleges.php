<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Renames the confusingly-named `departments` / `sub_departments` tables
 * (and their dependents) to their correct real-world meaning:
 *
 *   departments            -> colleges            (dept_id -> college_id)
 *   sub_departments        -> departments         (sub_dept_id -> dept_id)
 *   sub_department_schedulers -> department_schedulers (sub_dept_id -> department_id)
 *
 * and the FK columns that pointed at them on department_deans,
 * faculty_departments, offering_placement_requests, subjects and programs.
 *
 * This is a pure rename: no column types, nullability, defaults, or data
 * are changed. Column types below were copied verbatim from
 * `SHOW CREATE TABLE` output against the live akademus_sis database
 * (see task-1-report.md for the full output) before writing this file.
 *
 * MySQL DDL (RENAME TABLE / ALTER TABLE ... CHANGE COLUMN) causes an
 * implicit commit and cannot be wrapped in an explicit transaction, so no
 * transBegin()/transCommit() is used here. The down() migration, tested
 * against the real local DB, is the safety net.
 */
class RenameDepartmentsToColleges extends Migration
{
    public function up()
    {
        // 1. departments -> colleges (must happen before sub_departments -> departments,
        //    otherwise the second rename would collide with the still-existing old table)
        $this->db->query('RENAME TABLE departments TO colleges');
        $this->db->query('ALTER TABLE colleges CHANGE COLUMN dept_id college_id INT(11) NOT NULL AUTO_INCREMENT');

        // 2. sub_departments -> departments
        $this->db->query('RENAME TABLE sub_departments TO departments');
        $this->db->query('ALTER TABLE departments CHANGE COLUMN sub_dept_id dept_id INT(11) NOT NULL AUTO_INCREMENT');
        // departments.college_id keeps its name; its FK now points at colleges.college_id
        // (same column name, renamed target table)
        $this->db->query('ALTER TABLE departments DROP FOREIGN KEY sub_departments_college_id_foreign, ADD CONSTRAINT departments_college_id_foreign FOREIGN KEY (college_id) REFERENCES colleges (college_id) ON DELETE CASCADE');

        // 3. sub_department_schedulers -> department_schedulers
        $this->db->query('RENAME TABLE sub_department_schedulers TO department_schedulers');
        $this->db->query('ALTER TABLE department_schedulers CHANGE COLUMN sub_dept_id department_id INT(11) NOT NULL');
        $this->db->query('ALTER TABLE department_schedulers DROP FOREIGN KEY sub_department_schedulers_sub_dept_id_foreign, ADD CONSTRAINT department_schedulers_department_id_foreign FOREIGN KEY (department_id) REFERENCES departments (dept_id) ON DELETE CASCADE');

        // 4. FK column renames on dependent tables
        $this->db->query('ALTER TABLE department_deans CHANGE COLUMN dept_id college_id INT(11) NOT NULL');
        $this->db->query('ALTER TABLE department_deans DROP FOREIGN KEY department_deans_dept_id_foreign, ADD CONSTRAINT department_deans_college_id_foreign FOREIGN KEY (college_id) REFERENCES colleges (college_id) ON DELETE CASCADE');

        $this->db->query('ALTER TABLE faculty_departments CHANGE COLUMN sub_dept_id department_id INT(11) NOT NULL');
        $this->db->query('ALTER TABLE faculty_departments DROP FOREIGN KEY faculty_departments_sub_dept_id_foreign, ADD CONSTRAINT faculty_departments_department_id_foreign FOREIGN KEY (department_id) REFERENCES departments (dept_id) ON DELETE CASCADE');

        $this->db->query('ALTER TABLE offering_placement_requests CHANGE COLUMN requesting_sub_dept_id requesting_department_id INT(11) NOT NULL');
        $this->db->query('ALTER TABLE offering_placement_requests DROP FOREIGN KEY offering_placement_requests_requesting_sub_dept_id_foreign, ADD CONSTRAINT offering_placement_requests_requesting_department_id_foreign FOREIGN KEY (requesting_department_id) REFERENCES departments (dept_id) ON DELETE CASCADE');

        $this->db->query('ALTER TABLE subjects CHANGE COLUMN sub_department_id department_id INT(11) NULL DEFAULT NULL');
        $this->db->query('ALTER TABLE subjects DROP FOREIGN KEY subjects_sub_department_id_foreign, ADD CONSTRAINT subjects_department_id_foreign FOREIGN KEY (department_id) REFERENCES departments (dept_id) ON DELETE SET NULL');

        // programs.sub_department_id has no FK constraint in the live schema
        // (confirmed via SHOW CREATE TABLE / information_schema.KEY_COLUMN_USAGE) -
        // only the column itself needs renaming.
        $this->db->query('ALTER TABLE programs CHANGE COLUMN sub_department_id department_id INT(11) NULL DEFAULT NULL');
    }

    public function down()
    {
        // Exact reverse of up(), in exact reverse order.

        $this->db->query('ALTER TABLE programs CHANGE COLUMN department_id sub_department_id INT(11) NULL DEFAULT NULL');

        $this->db->query('ALTER TABLE subjects DROP FOREIGN KEY subjects_department_id_foreign, ADD CONSTRAINT subjects_sub_department_id_foreign FOREIGN KEY (department_id) REFERENCES departments (dept_id) ON DELETE SET NULL');
        $this->db->query('ALTER TABLE subjects CHANGE COLUMN department_id sub_department_id INT(11) NULL DEFAULT NULL');

        $this->db->query('ALTER TABLE offering_placement_requests DROP FOREIGN KEY offering_placement_requests_requesting_department_id_foreign, ADD CONSTRAINT offering_placement_requests_requesting_sub_dept_id_foreign FOREIGN KEY (requesting_department_id) REFERENCES departments (dept_id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE offering_placement_requests CHANGE COLUMN requesting_department_id requesting_sub_dept_id INT(11) NOT NULL');

        $this->db->query('ALTER TABLE faculty_departments DROP FOREIGN KEY faculty_departments_department_id_foreign, ADD CONSTRAINT faculty_departments_sub_dept_id_foreign FOREIGN KEY (department_id) REFERENCES departments (dept_id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE faculty_departments CHANGE COLUMN department_id sub_dept_id INT(11) NOT NULL');

        $this->db->query('ALTER TABLE department_deans DROP FOREIGN KEY department_deans_college_id_foreign, ADD CONSTRAINT department_deans_dept_id_foreign FOREIGN KEY (college_id) REFERENCES colleges (college_id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE department_deans CHANGE COLUMN college_id dept_id INT(11) NOT NULL');

        // 3 (reverse). department_schedulers -> sub_department_schedulers
        $this->db->query('ALTER TABLE department_schedulers DROP FOREIGN KEY department_schedulers_department_id_foreign, ADD CONSTRAINT sub_department_schedulers_sub_dept_id_foreign FOREIGN KEY (department_id) REFERENCES departments (dept_id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE department_schedulers CHANGE COLUMN department_id sub_dept_id INT(11) NOT NULL');
        $this->db->query('RENAME TABLE department_schedulers TO sub_department_schedulers');

        // 2 (reverse). departments -> sub_departments
        $this->db->query('ALTER TABLE departments DROP FOREIGN KEY departments_college_id_foreign, ADD CONSTRAINT sub_departments_college_id_foreign FOREIGN KEY (college_id) REFERENCES colleges (college_id) ON DELETE CASCADE');
        $this->db->query('ALTER TABLE departments CHANGE COLUMN dept_id sub_dept_id INT(11) NOT NULL AUTO_INCREMENT');
        $this->db->query('RENAME TABLE departments TO sub_departments');

        // 1 (reverse). colleges -> departments (last, since sub_departments FK must already point back at colleges' old name)
        $this->db->query('ALTER TABLE colleges CHANGE COLUMN college_id dept_id INT(11) NOT NULL AUTO_INCREMENT');
        $this->db->query('RENAME TABLE colleges TO departments');
    }
}
