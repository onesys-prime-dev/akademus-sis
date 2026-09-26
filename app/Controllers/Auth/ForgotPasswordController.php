<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\PasswordResetTokenModel;

class ForgotPasswordController extends BaseController
{
    public function showForgotForm()
    {
        return view('auth/forgot_password');
    }

    public function sendResetLink()
    {
        $email = $this->request->getPost('email');
        $users = auth()->getProvider();

        $user = $users->findByCredentials([
            'email' => $email
        ]);

        if (! $user) {
            return redirect()
                ->back()
                ->with('message', 'If the email exists, a password reset link has been sent.');
        }

        $token = bin2hex(random_bytes(32));

        $model = new PasswordResetTokenModel();

        $model->where('user_id', $user->id)->delete();

        $model->insert([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $token),
            'expires_at' => date(
                'Y-m-d H:i:s',
                strtotime('+1 hour') // testing (must be +1 hour, for production)
            ),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $link = site_url('reset-password/' . $token);

        $sent = (new \App\Libraries\BrandedEmail())->send($email, 'Reset your Akademus password', [
            // This header already says "Forgot your password?", so no headline of our own.
            'header'     => 'forgot-password-header.jpg',
            'preheader'  => 'Use this link to choose a new password. It expires in 1 hour.',
            'eyebrow'    => 'Password reset',
            'paragraphs' => [
                'We received a request to reset the password for your Akademus account. Choose a new one with the button below.',
            ],
            'button'     => ['label' => 'Reset my password', 'url' => $link],
            'footnote'   => [
                'This link expires in 1 hour and can be used once.',
                "Didn't ask for this? You can safely ignore this email — your password won't change.",
            ],
        ]);

        if (! $sent) {
            // SMTP detail is logged inside BrandedEmail. Deliberately answer
            // exactly as for an unknown address: a visible failure only for
            // real accounts would reveal which emails are registered.
            log_message('error', 'Password reset email could not be sent for user {id}.', ['id' => $user->id]);
        }

        return redirect()
            ->back()
            ->with('message', 'If the email exists, a password reset link has been sent.');
    }

    public function showResetForm($token)
    {
        $model = new PasswordResetTokenModel();

        $resetToken = $model
            ->where('token', hash('sha256', $token))
            ->first();

        if (! $resetToken) {
            return $this->linkNoLongerValid();
        }

        if (strtotime($resetToken['expires_at']) < time()) {
            return $this->linkNoLongerValid();
        }

        return view('auth/reset_password', [
            'token' => $token
        ]);
    }

    public function resetPassword()
    {
        $token = $this->request->getPost('token');

        $password = $this->request->getPost('password');
        $passwordConfirm = $this->request->getPost('password_confirm');

        if ($password !== $passwordConfirm) {
            return redirect()->back()
                ->with('error', 'Passwords do not match.');
        }

        $model = new PasswordResetTokenModel();

        $hashedToken = hash('sha256', $token);

        $resetToken = $model
            ->where('token', $hashedToken)
            ->first();

        if (! $resetToken) {
            return $this->linkNoLongerValid();
        }

        if (strtotime($resetToken['expires_at']) < time()) {
            return $this->linkNoLongerValid();
        }

        $user = auth()->getProvider()
            ->findById($resetToken['user_id']);

        if ($user === null) {
            return $this->linkNoLongerValid();
        }

        // Same rules as registration (minimum length, composition, nothing
        // personal, dictionary) — an empty or trivial password is refused.
        $check = service('passwords')->check((string) $password, $user);

        if (! $check->isOK()) {
            return redirect()->back()
                ->with('error', $check->reason());
        }

        $user->password = $password;

        auth()->getProvider()->save($user);

        // The link is single-use: drop every outstanding reset token for this
        // account, and revoke "remember me" logins so a stolen device can't
        // stay signed in after the owner changes the password.
        $model->where('user_id', $resetToken['user_id'])->delete();

        db_connect()->table(setting('Auth.tables')['remember_tokens'])
            ->where('user_id', $resetToken['user_id'])
            ->delete();

        return redirect()->to('/login')
            ->with('message', 'Password successfully reset.');
    }

    /**
     * Old, used or expired link: say so where they can act on it (asking for
     * a new one) instead of dropping them on the login page.
     */
    private function linkNoLongerValid()
    {
        return redirect()->to('/forgot-password')
            ->with('error', 'This reset link is no longer valid. It may have expired, already been used, or been replaced by a newer request. Enter your email to get a new one.');
    }
}
