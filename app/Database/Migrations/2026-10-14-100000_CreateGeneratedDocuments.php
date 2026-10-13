<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The immutable generated PDF artifact + its QR verification code.
 * verification_code is looked up by the public verify/{code} route — a
 * third party with the QR has no SIS account, so that route stays outside
 * every group filter and this table is the only thing it's allowed to
 * read from.
 */
class CreateGeneratedDocuments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'request_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'student_id' => [
                'type' => 'INT',
            ],
            'document_type' => [
                'type'       => 'ENUM',
                'constraint' => ['tor', 'certificate_enrollment', 'certificate_graduation', 'good_moral', 'certificate_candidacy'],
            ],
            'stored_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'verification_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'generated_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'generated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('verification_code');
        $this->forge->addKey('student_id');
        $this->forge->addForeignKey('request_id', 'document_requests', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('generated_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('generated_documents');
    }

    public function down()
    {
        $this->forge->dropTable('generated_documents');
    }
}
