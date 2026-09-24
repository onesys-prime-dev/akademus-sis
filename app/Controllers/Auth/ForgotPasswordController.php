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

        $emailService = service('email');

        $emailService->setTo($email);
        $emailService->setSubject('Reset Your Password');

        $emailService->setMessage("
            <h2>Password Reset</h2>
            <p>Click the link below to reset your password:</p>
            <p>
                <a href='{$link}'>
                    Reset Password
                </a>
            </p>
            <p>This link expires in 1 hour.</p>
        ");

        if (! $emailService->send(false)) {
            // Log the SMTP detail server-side; never show it to the visitor.
            log_message('error', '[Password reset] email to user ' . $user->id . ' failed: ' . strip_tags($emailService->printDebugger(['headers'])));

            return redirect()
                ->back()
                ->with('error', 'We could not send the email right now. Please try again in a few minutes.');
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
            return redirect()->to('/login')
                ->with('error', 'This password reset link is invalid, expired, or has already been used.');
        }

        if (strtotime($resetToken['expires_at']) < time()) {
            return redirect()->to('/login')
                ->with('error', 'This password reset link has expired.');
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
            return redirect()->to('/login')
                ->with('error', 'Invalid reset token.');
        }

        if (strtotime($resetToken['expires_at']) < time()) {
            return redirect()->to('/login')
                ->with('error', 'Reset token has expired.');
        }

        $user = auth()->getProvider()
            ->findById($resetToken['user_id']);

        $user->password = $password;

        auth()->getProvider()->save($user);

        $model->delete($resetToken['id']);

        return redirect()->to('/login')
            ->with('message', 'Password successfully reset.');
    }
}
