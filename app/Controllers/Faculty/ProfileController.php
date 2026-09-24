<?php

namespace App\Controllers\Faculty;

use App\Controllers\BaseController;
use App\Models\DepartmentModel;

class ProfileController extends BaseController
{
    public function edit()
    {
        $account = auth()->user();
        $dept    = (new DepartmentModel())->codeOfUser((int) auth()->id());

        return view('faculty/profile/edit', [
            'user' => [
                'name' => $account->username ?? 'Faculty',
                'role' => ($dept !== null ? $dept . ' Department · ' : '') . 'Faculty',
            ],
            'account' => [
                'username' => $account->username ?? '',
                'email'    => $account->getEmail() ?? '',
            ],
            'deptName' => $dept,
        ]);
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
