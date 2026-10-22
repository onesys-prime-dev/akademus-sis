<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;

class ProfileController extends BaseController
{
    public function edit()
    {
        $account   = auth()->user();
        $photoMime = db_connect()->table('users')->select('photo_mime')->where('id', (int) auth()->id())->get()->getRowArray()['photo_mime'] ?? null;

        return view('registrar/profile', [
            'pageTitle' => 'Profile',
            'account'   => [
                'id'         => (int) auth()->id(),
                'username'   => $account->username ?? '',
                'email'      => $account->getEmail() ?? '',
                'photo_mime' => $photoMime,
            ],
        ] + RegistrarPortal::shell('profile'));
    }

    /** Same contract as StudentController::uploadPhoto() — generic users.photo_blob/photo_mime storage. */
    public function uploadPhoto()
    {
        $userId = (int) auth()->id();

        $rules = [
            'photo' => 'uploaded[photo]|max_size[photo,2048]|ext_in[photo,jpg,jpeg,png,webp]|mime_in[photo,image/jpeg,image/png,image/webp]|is_image[photo]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/registrar/profile')->with('profile_error', implode(' ', $this->validator->getErrors()));
        }

        $file = $this->request->getFile('photo');

        if ($file === null || ! $file->isValid()) {
            return redirect()->to('/registrar/profile')->with('profile_error', 'Upload failed. Please try again.');
        }

        db_connect()->table('users')->where('id', $userId)->update([
            'photo_blob' => file_get_contents($file->getTempName()),
            'photo_mime' => $file->getClientMimeType(),
        ]);

        return redirect()->to('/registrar/profile')->with('profile_message', 'Profile photo updated.');
    }

    /** Same contract as StudentController::removePhoto(). */
    public function removePhoto()
    {
        db_connect()->table('users')->where('id', (int) auth()->id())->update([
            'photo_blob' => null,
            'photo_mime' => null,
        ]);

        return redirect()->to('/registrar/profile')->with('profile_message', 'Profile photo removed.');
    }

    public function updateProfile()
    {
        $username = trim((string) $this->request->getPost('username'));

        if ($username === '' || mb_strlen($username) > 100) {
            return redirect()->back()->with('profile_error', 'Name is required (max 100 characters).');
        }

        // Names appear on other roles' pages; keep markup characters out at the source.
        if (preg_match('/[<>"`\\]/', $username) === 1) {
            return redirect()->back()->with('profile_error', 'Name cannot contain < > " ` or \ characters.');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->username = $username;
        $users->save($user);

        return redirect()->to('/registrar/profile')->with('profile_message', 'Profile updated.');
    }

    public function updatePassword()
    {
        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if ($current === '' || $new === '' || $confirm === '') {
            return redirect()->back()->with('profile_error', 'All password fields are required.');
        }

        if ($new !== $confirm) {
            return redirect()->back()->with('profile_error', 'New password and confirmation do not match.');
        }

        $account = auth()->user();

        if (! auth()->check(['email' => $account->getEmail(), 'password' => $current])->isOK()) {
            return redirect()->back()->with('profile_error', 'Current password is incorrect.');
        }

        // Shield's configured validators (length, composition, common-password
        // list) apply when the password is saved.
        $account->password = $new;

        try {
            auth()->getProvider()->save($account);
        } catch (\Throwable $e) {
            return redirect()->back()->with('profile_error', 'That password was rejected. Use at least 8 characters that are not easy to guess.');
        }

        return redirect()->to('/registrar/profile')->with('profile_message', 'Password changed.');
    }
}
