<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Switches profile photos from a file on disk (writable/uploads/avatars/)
 * to bytes stored directly in the database, per explicit request — simpler
 * mental model (one place, travels with a DB backup) at this app's scale.
 * photo_filename is dropped; photo_blob holds the image bytes and
 * photo_mime its content type (AvatarController needs this to set the
 * right Content-Type when streaming it back — a blob alone doesn't carry
 * that).
 */
class StorePhotosAsBlob extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'photo_blob' => [
                'type' => 'LONGBLOB',
                'null' => true,
                'after' => 'photo_filename',
            ],
            'photo_mime' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'photo_blob',
            ],
        ]);

        $this->forge->dropColumn('users', 'photo_filename');
    }

    public function down()
    {
        $this->forge->addColumn('users', [
            'photo_filename' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'contact_number',
            ],
        ]);

        $this->forge->dropColumn('users', ['photo_blob', 'photo_mime']);
    }
}
