<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The grant record: a student has been awarded a discount_types entry,
 * optionally scoped to one academic_year (null = every term until
 * revoked) and optionally overriding the catalog's default_value for this
 * specific grant. TuitionAssessmentService::recompute() reads active
 * grants for the student's current term and ensures exactly one
 * ledger_adjustments credit row exists per grant per term (idempotent via
 * ledger_adjustments.discount_grant_id) — revoking here does not reverse
 * a term that's already been charged; it only stops future recomputes
 * from reapplying it.
 */
class CreateStudentDiscounts extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'student_id' => [
                'type' => 'INT',
            ],
            'discount_type_id' => [
                'type' => 'INT',
            ],
            'academic_year' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'override_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'revoked'],
                'default'    => 'active',
            ],
            'granted_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'granted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'remarks' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('student_id');
        $this->forge->addKey('discount_type_id');

        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('discount_type_id', 'discount_types', 'id', '', 'CASCADE');
        $this->forge->addForeignKey('granted_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('revoked_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('student_discounts');
    }

    public function down()
    {
        $this->forge->dropTable('student_discounts');
    }
}
