<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * File evidence (exam papers, chair memos, etc.) attached to a grade change
 * request's faculty response. Follows the WRITEPATH . 'uploads/{domain}/{id}/'
 * storage convention already used by StudentController::uploadProof()
 * (payment_proofs) — metadata row here, actual file under
 * writable/uploads/grade_change_attachments/{request_id}/.
 */
class CreateGradeChangeAttachments extends Migration
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
            ],
            'uploaded_by' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'original_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'stored_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 127,
                'null'       => true,
            ],
            'size_bytes' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('request_id');
        $this->forge->addForeignKey('request_id', 'grade_change_requests', 'request_id', '', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', '', 'CASCADE');
        $this->forge->createTable('grade_change_attachments');
    }

    public function down()
    {
        $this->forge->dropTable('grade_change_attachments');
    }
}
