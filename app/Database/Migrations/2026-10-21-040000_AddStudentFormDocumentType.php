<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Widens the document_type ENUM on both generated_documents and
 * document_requests to add 'student_form' — an instantly-generated,
 * balance-exempt document (like 'cor'/'certificate_enrollment') printed
 * directly from the student enlistment "Enrolled" screen, with no
 * request/approval queue involved (see StudentEnlistment::printForm()).
 */
class AddStudentFormDocumentType extends Migration
{
    private const TYPES = ['tor', 'certificate_enrollment', 'certificate_graduation', 'good_moral', 'certificate_candidacy', 'cor', 'student_form'];

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
        $original = ['tor', 'certificate_enrollment', 'certificate_graduation', 'good_moral', 'certificate_candidacy', 'cor'];

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
