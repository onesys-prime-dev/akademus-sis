<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A profile photo, following the same "bolted onto users directly" precedent
 * as first_name/middle_name/last_name/contact_number
 * (2026-09-16-010000_AddNameFieldsToUsers.php). Stores only the randomized
 * filename (OWASP File Upload Cheat Sheet — never the original), same
 * convention PaymentProofModel already uses; the file itself lives under
 * writable/uploads/avatars/{user_id}/, served only through a controller
 * action, never a public path.
 */
class AddPhotoFilenameToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'photo_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'contact_number',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'photo_filename');
    }
}
