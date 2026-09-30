<?php

namespace App\Controllers;

/**
 * Clears users.must_change_password after a forced first-login password
 * change (temp password emailed by StudentWelcomeMailer). Deliberately
 * role-agnostic — the blocking modal in layouts/admin_console.php shows for
 * ANY signed-in user with the flag set, not just students, even though only
 * StudentModel::promoteFromApplicant() sets it today.
 */
class AccountController extends BaseController
{
    public function setTempPassword()
    {
        if (! auth()->loggedIn()) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Please sign in again.',
            ]);
        }

        $newPassword     = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        if ($newPassword === '' || $confirmPassword === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Both fields are required.']);
        }

        if ($newPassword !== $confirmPassword) {
            return $this->response->setJSON(['success' => false, 'message' => 'Passwords do not match.']);
        }

        $user  = auth()->user();
        $check = service('passwords')->check($newPassword, $user);

        if (! $check->isOK()) {
            return $this->response->setJSON(['success' => false, 'message' => $check->reason()]);
        }

        $user->password = $newPassword;
        auth()->getProvider()->save($user);

        db_connect()->table('users')->where('id', $user->id)->update(['must_change_password' => 0]);

        return $this->response->setJSON(['success' => true]);
    }
}
