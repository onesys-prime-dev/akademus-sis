<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Streams a user's uploaded profile photo straight out of the database
 * (users.photo_blob/photo_mime — see 2026-10-23-010000_StorePhotosAsBlob.php),
 * never a file on disk. Low-sensitivity, so unlike StudentController::
 * viewProof() (ownership-checked) this only requires being logged in — the
 * same visibility a username or initial already has everywhere in the app
 * (sidebar, headers, admin lists).
 */
class AvatarController extends BaseController
{
    public function show(int $userId)
    {
        if (! auth()->loggedIn()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $row = db_connect()->table('users')
            ->select('photo_blob, photo_mime')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null || empty($row['photo_mime']) || $row['photo_blob'] === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', $row['photo_mime'])
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody($row['photo_blob']);
    }
}
