<?php

namespace App\Libraries;

use Throwable;

/**
 * First-login email for a student created by the Admissions handoff.
 *
 * Mirrors the Admission Portal's registration email (EmailService::
 * sendWelcomeEmail): the temp password is emailed in plaintext exactly once
 * and never stored anywhere — StudentModel::promoteFromApplicant() sets
 * users.must_change_password = 1, and the student is forced to replace it
 * with a real password on first login (see the change-password modal in
 * layouts/admin_console.php + AccountController::setTempPassword()).
 */
class StudentWelcomeMailer
{
    /** The words of the email (plain text; BrandedEmail escapes and lays them out). */
    public function content(string $firstName, string $studentNumber, string $email, string $tempPassword): array
    {
        return [
            'preheader'  => "Your student number is {$studentNumber}. Sign in with the temporary password below.",
            'eyebrow'    => 'Welcome aboard',
            'title'      => "Welcome to Akademus, {$firstName}",
            'paragraphs' => [
                'Your reservation is confirmed and your student account is ready. Here are your sign-in details — you will be asked to set a new password the first time you sign in.',
            ],
            'details'    => [
                'Student number'      => $studentNumber,
                'Sign-in email'       => $email,
                'Temporary password'  => $tempPassword,
            ],
            'button'     => ['label' => 'Sign in to Akademus', 'url' => site_url('login')],
            'footnote'   => [
                'For your security, please sign in and set a new password as soon as possible.',
            ],
        ];
    }

    public function buildMessage(string $firstName, string $studentNumber, string $email, string $tempPassword): string
    {
        return (new BrandedEmail())->render($this->content($firstName, $studentNumber, $email, $tempPassword), 'cid:akademus-header');
    }

    /**
     * Send the welcome email carrying the temp password. Never throws — a
     * mail problem must not undo a student who was already created; the
     * student can always use "Forgot password?" instead.
     */
    public function send(string $email, string $firstName, string $studentNumber, string $tempPassword): bool
    {
        try {
            return (new BrandedEmail())->send(
                $email,
                'Welcome to Akademus — your sign-in details',
                $this->content($firstName, $studentNumber, $email, $tempPassword)
            );
        } catch (Throwable $e) {
            log_message('error', '[Welcome email] failed for ' . $email . ': ' . $e->getMessage());
        }

        return false;
    }
}
