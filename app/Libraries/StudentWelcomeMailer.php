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

    public function buildMessage(string $firstName, string $studentNumber, string $email, string $link): string
    {
        $name   = esc($firstName);
        $number = esc($studentNumber);
        $login  = esc($email);
        $link   = esc($link);
        $loginUrl = esc(site_url('login'));
        $days   = self::LINK_LIFETIME_DAYS;

        return <<<HTML
        <div style="background:#f3f4f6;padding:30px 0;font-family:Arial,sans-serif;">
          <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;padding:30px;">
            <tr><td style="font-size:22px;font-weight:bold;color:#111827;padding-bottom:10px;">Welcome to Akademus, {$name}!</td></tr>
            <tr><td style="font-size:14px;color:#4b5563;padding-bottom:20px;">
              Your reservation is confirmed and your student account is ready.
            </td></tr>
            <tr><td style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:15px;font-size:14px;color:#111827;">
              <div><strong>Student number:</strong> {$number}</div>
              <div style="margin-top:6px;"><strong>Login email:</strong> {$login}</div>
            </td></tr>
            <tr><td style="padding:24px 0 8px;font-size:14px;color:#4b5563;">
              Set your password to sign in for the first time:
            </td></tr>
            <tr><td>
              <a href="{$link}" style="display:inline-block;background:#0037b0;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:14px;font-weight:bold;">Set my password</a>
            </td></tr>
            <tr><td style="padding-top:16px;font-size:12px;color:#6b7280;">
              This link works for {$days} days. If it expires, choose "Forgot password?" on the
              <a href="{$loginUrl}">sign-in page</a> to get a new one.
            </td></tr>
          </table>
        </div>
        HTML;
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

            $mailer = service('email');
            $mailer->clear();
            $mailer->setTo($email);
            $mailer->setSubject('Welcome to Akademus — set your password');
            $mailer->setMessage($this->buildMessage($firstName, $studentNumber, $email, $link));

            if ($mailer->send(false)) {
                return true;
            }

            log_message('error', '[Welcome email] not sent to user ' . $userId . ': ' . strip_tags($mailer->printDebugger(['headers'])));
        } catch (Throwable $e) {
            log_message('error', '[Welcome email] failed for user ' . $userId . ': ' . $e->getMessage());
        }

        return false;
    }
}
