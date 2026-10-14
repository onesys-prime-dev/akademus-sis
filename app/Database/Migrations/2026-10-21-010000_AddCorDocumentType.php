<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Widens the document_type ENUM on both generated_documents and
 * document_requests to add 'cor' (Certificate of Registration) — a
 * looser-gated document type alongside the existing TOR/certificates
 * (see GeneratedDocumentModel::generate()'s type-dependent balance check).
 */
class AddCorDocumentType extends Migration
{
    private const TYPES = ['tor', 'certificate_enrollment', 'certificate_graduation', 'good_moral', 'certificate_candidacy', 'cor'];

    public function up()
    {
        $this->forge->modifyColumn('generated_documents', [
            'document_type' => [
                'name'       => 'document_type',
                'type'       => 'ENUM',
                'constraint' => self::TYPES,
            ],
        ]);

        $this->forge->modifyColumn('document_requests', [
            'document_type' => [
                'name'       => 'document_type',
                'type'       => 'ENUM',
                'constraint' => self::TYPES,
            ],
        ]);
    }

    public function down()
    {
        $original = ['tor', 'certificate_enrollment', 'certificate_graduation', 'good_moral', 'certificate_candidacy'];

        $this->forge->modifyColumn('generated_documents', [
            'document_type' => [
                'name'       => 'document_type',
                'type'       => 'ENUM',
                'constraint' => $original,
            ],
        ]);

        $this->forge->modifyColumn('document_requests', [
            'document_type' => [
                'name'       => 'document_type',
                'type'       => 'ENUM',
                'constraint' => $original,
            ],
        ]);
    }
}
