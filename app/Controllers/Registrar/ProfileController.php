<?php

namespace App\Controllers\Registrar;

use App\Controllers\BaseController;
use App\Libraries\RegistrarPortal;

class ProfileController extends BaseController
{
    public function edit()
    {
        $account = auth()->user();

        return view('registrar/profile', [
            'pageTitle' => 'Profile',
            'account'   => [
                'username' => $account->username ?? '',
                'email'    => $account->getEmail() ?? '',
            ],
        ] + RegistrarPortal::shell('profile'));
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
