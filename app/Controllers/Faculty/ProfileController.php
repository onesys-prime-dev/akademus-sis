<?php

namespace App\Controllers\Faculty;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;

class ProfileController extends BaseController
{
    public function edit()
    {
        $account   = auth()->user();
        $dept      = (new DepartmentModel())->codeOfUser((int) auth()->id());
        $photoMime = db_connect()->table('users')->select('photo_mime')->where('id', (int) auth()->id())->get()->getRowArray()['photo_mime'] ?? null;

        return view('faculty/profile/edit', [
            'user' => [
                'name' => $account->username ?? 'Faculty',
                'role' => ($dept !== null ? $dept . ' Department · ' : '') . 'Faculty',
            ],
            'account' => [
                'id'         => (int) auth()->id(),
                'username'   => $account->username ?? '',
                'email'      => $account->getEmail() ?? '',
                'photo_mime' => $photoMime,
            ],
            'deptName' => $dept,
        ]);
    }

    /** Same contract as StudentController::uploadPhoto() — generic users.photo_blob/photo_mime storage. */
    public function uploadPhoto()
    {
        $userId = (int) auth()->id();

        $rules = [
            'photo' => 'uploaded[photo]|max_size[photo,2048]|ext_in[photo,jpg,jpeg,png,webp]|mime_in[photo,image/jpeg,image/png,image/webp]|is_image[photo]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/faculty/profile')->with('profile_error', implode(' ', $this->validator->getErrors()));
        }

        $file = $this->request->getFile('photo');

        if ($file === null || ! $file->isValid()) {
            return redirect()->to('/faculty/profile')->with('profile_error', 'Upload failed. Please try again.');
        }

        db_connect()->table('users')->where('id', $userId)->update([
            'photo_blob' => file_get_contents($file->getTempName()),
            'photo_mime' => $file->getClientMimeType(),
        ]);

        return redirect()->to('/faculty/profile')->with('profile_message', 'Profile photo updated.');
    }

    /** Same contract as StudentController::removePhoto(). */
    public function removePhoto()
    {
        db_connect()->table('users')->where('id', (int) auth()->id())->update([
            'photo_blob' => null,
            'photo_mime' => null,
        ]);

        return redirect()->to('/faculty/profile')->with('profile_message', 'Profile photo removed.');
    }

    public function updateProfile()
    {
        $username = trim((string) $this->request->getPost('username'));

        if ($username === '' || mb_strlen($username) > 100) {
            return redirect()->back()->with('profile_error', 'Name is required (max 100 characters).');
        }

        // Names are rendered in other roles' pages; keep markup characters out
        // at the source as well as escaping them on output.
        if (preg_match('/[<>"`\\\\]/', $username) === 1) {
            return redirect()->back()->with('profile_error', 'Name cannot contain < > " ` or \\ characters.');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->username = $username;
        $users->save($user);

        return redirect()->to('/faculty/profile')->with('profile_message', 'Profile updated.');
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
        $result  = auth()->check(['email' => $account->getEmail(), 'password' => $current]);

        if (! $result->isOK()) {
            return redirect()->back()->with('profile_error', 'Current password is incorrect.');
        }

        if (mb_strlen($new) < 8) {
            return redirect()->back()->with('profile_error', 'New password must be at least 8 characters.');
        }

        $users = auth()->getProvider();
        $user  = auth()->user();
        $user->password = $new;
        $users->save($user);

        return redirect()->to('/faculty/profile')->with('profile_message', 'Password changed.');
    }
}
