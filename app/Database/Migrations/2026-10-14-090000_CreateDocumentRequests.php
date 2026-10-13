<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Request/queue header for official student documents (TOR, certificates)
 * — mirrors payment_proofs' request-then-staff-action shape. A document
 * can also be generated ad hoc by the registrar with no request behind it
 * (generated_documents.request_id is nullable), e.g. printing a walk-in
 * certificate of enrollment on the spot.
 */
class CreateDocumentRequests extends Migration
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
            'document_type' => [
                'type'       => 'ENUM',
                'constraint' => ['tor', 'certificate_enrollment', 'certificate_graduation', 'good_moral', 'certificate_candidacy'],
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'processing', 'released', 'denied'],
                'default'    => 'pending',
            ],
            'requested_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'purpose' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'released_at' => ['type' => 'DATETIME', 'null' => true],
            'released_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('student_id');
        $this->forge->addForeignKey('student_id', 'students', 'student_id', '', 'CASCADE');
        $this->forge->addForeignKey('requested_by', 'users', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('released_by', 'users', 'id', '', 'SET NULL');
        $this->forge->createTable('document_requests');
    }

    public function down()
    {
        $this->forge->dropTable('document_requests');
    }
}
