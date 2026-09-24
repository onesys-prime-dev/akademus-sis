<?php

namespace App\Libraries;

use App\Models\PasswordResetTokenModel;
use Throwable;

/**
 * First-login email for a student created by the Admissions handoff.
 *
 * The SIS creates the account with a random password nobody knows, so the
 * email carries a set-password link built on the existing reset-password
 * flow (same token table, same page). The link outlives the 1-hour "forgot
 * password" window because a new student may not check their inbox for days.
 */
class StudentWelcomeMailer
{
    public const LINK_LIFETIME_DAYS = 7;

    /**
     * Issue a set-password token for the user and return the full link.
     * Any earlier token for the same user is replaced.
     */
    public function issueSetPasswordLink(int $userId): string
    {
        $token  = bin2hex(random_bytes(32));
        $tokens = new PasswordResetTokenModel();

        $tokens->where('user_id', $userId)->delete();
        $tokens->insert([
            'user_id'    => $userId,
            'token'      => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::LINK_LIFETIME_DAYS . ' days')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return site_url('reset-password/' . $token);
    }

    /** The words of the email (plain text; BrandedEmail escapes and lays them out). */
    public function content(string $firstName, string $studentNumber, string $email, string $link): array
    {
        return [
            'preheader'  => "Your student number is {$studentNumber}. Set your password to get started.",
            'eyebrow'    => 'Welcome aboard',
            'title'      => "Welcome to Akademus, {$firstName}",
            'paragraphs' => [
                'Your reservation is confirmed and your student account is ready. Here are your details — keep them handy.',
            ],
            'details'    => [
                'Student number' => $studentNumber,
                'Sign-in email'  => $email,
            ],
            'button'     => ['label' => 'Set my password', 'url' => $link],
            'footnote'   => [
                'This link works for ' . self::LINK_LIFETIME_DAYS . ' days. If it expires, choose "Forgot password?" on the sign-in page and we will send you a new one.',
            ],
        ];
    }

    public function buildMessage(string $firstName, string $studentNumber, string $email, string $link): string
    {
        return (new BrandedEmail())->render($this->content($firstName, $studentNumber, $email, $link), 'cid:akademus-header');
    }

    /**
     * Issue the link and send the email. Never throws — a mail problem must
     * not undo a student who was already created; the student can always use
     * "Forgot password?" instead.
     */
    public function send(int $userId, string $email, string $firstName, string $studentNumber): bool
    {
        try {
            $link = $this->issueSetPasswordLink($userId);

            return (new BrandedEmail())->send(
                $email,
                'Welcome to Akademus — set your password',
                $this->content($firstName, $studentNumber, $email, $link)
            );
        } catch (Throwable $e) {
            log_message('error', '[Welcome email] failed for user ' . $userId . ': ' . $e->getMessage());
        }

        return false;
    }
}
