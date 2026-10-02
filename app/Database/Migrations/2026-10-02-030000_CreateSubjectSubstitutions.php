<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Explicit, auditable "this completed subject may satisfy that required
 * subject" relationships (Step 8). Directional by design — required/
 * substitute are not interchangeable, and true symmetric equivalency is
 * represented as two rows (A->B and B->A), not a second mechanism or a
 * relationship_type column (direction alone fully captures the behavioral
 * difference; a label would carry no logic).
 *
 * Soft-deactivated (is_active), never hard-deleted — matches the existing
 * convention across subjects/curriculum/curriculum_subjects/programs. The
 * unique key is scoped to the row's own direction only; the reverse
 * direction is a legitimately independent relationship, not a duplicate.
 */
class CreateSubjectSubstitutions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'required_subject_id' => [
                'type' => 'INT',
            ],
            'substitute_subject_id' => [
                'type' => 'INT',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
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
        $this->forge->addUniqueKey(['required_subject_id', 'substitute_subject_id']);
        $this->forge->addKey('required_subject_id');
        $this->forge->addKey('substitute_subject_id');

        $this->forge->addForeignKey('required_subject_id', 'subjects', 'subject_id', '', 'CASCADE');
        $this->forge->addForeignKey('substitute_subject_id', 'subjects', 'subject_id', '', 'CASCADE');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL');

        $this->forge->createTable('subject_substitutions');
    }

    public function down()
    {
        $this->forge->dropTable('subject_substitutions');
    }
}
