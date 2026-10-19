<?php

namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Streams a user's uploaded profile photo. Low-sensitivity, so unlike
 * StudentController::viewProof() (ownership-checked) this only requires
 * being logged in — the same visibility a username or initial already has
 * everywhere in the app (sidebar, headers, admin lists). Never served from
 * a public path; the randomized filename alone would be enough to guess if
 * it were.
 */
class AvatarController extends BaseController
{
    public function show(int $userId)
    {
        if (! auth()->loggedIn()) {
            throw PageNotFoundException::forPageNotFound();
        }

        $photo = db_connect()->table('users')->select('photo_filename')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray();

        if ($photo === null || empty($photo['photo_filename'])) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = WRITEPATH . 'uploads/avatars/' . $userId . '/' . $photo['photo_filename'];

        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $ext   = strtolower(pathinfo($photo['photo_filename'], PATHINFO_EXTENSION));
        $mime  = $mimes[$ext] ?? 'application/octet-stream';

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Cache-Control', 'private, max-age=3600')
            ->setBody(file_get_contents($path));
    }
}
