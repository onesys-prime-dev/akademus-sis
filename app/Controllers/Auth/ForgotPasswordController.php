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
                ->with('error', 'Email not found.');
        }

        $token = bin2hex(random_bytes(32));

        $model = new PasswordResetTokenModel();

        $model->insert([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $token),
            'expires_at' => date(
                'Y-m-d H:i:s',
                strtotime('+1 hour')
            ),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $link = site_url('reset-password/' . $token);

        return redirect()
            ->back()
            ->with('message', 'Password reset link sent.');
    }

    public function showResetForm($token)
    {
        return view('auth/reset_password', [
            'token' => $token
        ]);
    }

    public function resetPassword()
    {
        dd('resetPassword reached');
        $token = $this->request->getPost('token');

        $password = $this->request->getPost('password');
        $passwordConfirm = $this->request->getPost('password_confirm');

        if ($password !== $passwordConfirm) {
            return redirect()->back()
                ->with('error', 'Passwords do not match.');
        }

        $model = new PasswordResetTokenModel();

        $resetToken = $model
            ->where('token', hash('sha256', $token))
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

        dd($user);
        auth()->getProvider()->save($user);

        $model->delete($resetToken['id']);

        return redirect()->to('/login')
            ->with('message', 'Password successfully reset.');
    }
}
