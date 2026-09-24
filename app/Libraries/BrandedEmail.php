<?php

namespace App\Libraries;

use Throwable;

/**
 * The one look every SIS email shares: a white card under the Akademus
 * illustration header, a short headline, a few lines of copy, an optional
 * details box, one clear button and a quiet footer.
 *
 * Built for email clients rather than browsers — nested tables, inline
 * styles, a bulletproof button — and it ships a plain-text alternative too.
 * The header image is attached inline (cid:) instead of linked, because a
 * remote URL is blocked by default in many clients and unreachable from
 * localhost; the same message therefore looks right in dev and production.
 *
 * Content array (all values are plain text — everything is escaped here):
 *   preheader  string   inbox preview line
 *   eyebrow    string   small label above the title
 *   title      string
 *   paragraphs string[]
 *   details    array<string,string>  label => value, shown in a highlighted box
 *   button     array{label:string,url:string}
 *   footnote   string[] small print under the button
 */
class BrandedEmail
{
    public const HEADER_IMAGE = 'assets/images/email/email-header.jpg';

    private const BLUE   = '#0037b0';
    private const NAVY   = '#0f172a';
    private const TEXT   = '#334155';
    private const MUTED  = '#64748b';
    private const TINT   = '#f1f5fd';
    private const LINE   = '#e2e8f0';
    private const CANVAS = '#f4f6fb';

    private const FONT = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";

    public function render(array $c, string $headerSrc): string
    {
        $e = static fn (string $s): string => esc($s);

        $preheader = $e($c['preheader'] ?? '');
        $eyebrow   = isset($c['eyebrow']) ? $e($c['eyebrow']) : '';
        $title     = $e($c['title'] ?? '');

        $paragraphs = '';
        foreach ($c['paragraphs'] ?? [] as $p) {
            $paragraphs .= '<p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:' . self::TEXT . ';">' . $e($p) . '</p>';
        }

        $details = '';
        if (! empty($c['details'])) {
            $rows = '';
            $last = array_key_last($c['details']);
            foreach ($c['details'] as $label => $value) {
                $border = $label === $last ? '' : 'border-bottom:1px solid #dbe4f7;';
                $rows  .= '<tr><td style="padding:14px 20px;' . $border . '">'
                    . '<div style="font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:' . self::MUTED . ';">' . $e((string) $label) . '</div>'
                    . '<div style="margin-top:4px;font-size:18px;font-weight:600;color:' . self::NAVY . ';word-break:break-all;">' . $e((string) $value) . '</div>'
                    . '</td></tr>';
            }
            $details = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 28px;background:' . self::TINT . ';border-radius:12px;border-left:4px solid ' . self::BLUE . ';">' . $rows . '</table>';
        }

        $button = '';
        $fallback = '';
        if (! empty($c['button']['url'])) {
            $url    = $e($c['button']['url']);
            $label  = $e($c['button']['label'] ?? 'Continue');
            $button = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 8px;"><tr>'
                . '<td align="center" bgcolor="' . self::BLUE . '" style="border-radius:10px;">'
                . '<a class="btn" href="' . $url . '" style="display:inline-block;padding:15px 34px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">' . $label . '</a>'
                . '</td></tr></table>';
            $fallback = '<p style="margin:18px 0 0;font-size:12px;line-height:1.6;color:' . self::MUTED . ';">Button not working? Copy this link into your browser:<br>'
                . '<a href="' . $url . '" style="color:' . self::BLUE . ';word-break:break-all;">' . $url . '</a></p>';
        }

        $footnote = '';
        foreach ($c['footnote'] ?? [] as $line) {
            $footnote .= '<p style="margin:14px 0 0;font-size:12px;line-height:1.6;color:' . self::MUTED . ';">' . $e($line) . '</p>';
        }

        $eyebrowHtml = $eyebrow !== ''
            ? '<div style="margin:0 0 10px;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:' . self::BLUE . ';">' . $eyebrow . '</div>'
            : '';

        $year = date('Y');
        $font = self::FONT;

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width,initial-scale=1">
          <meta name="color-scheme" content="light">
          <meta name="supported-color-schemes" content="light">
          <title>{$title}</title>
          <style>
            @media only screen and (max-width:480px) {
              .px { padding-left:22px !important; padding-right:22px !important; }
              .h1 { font-size:23px !important; }
              .btn { display:block !important; text-align:center !important; }
            }
          </style>
        </head>
        <body style="margin:0;padding:0;background:{$this->canvas()};font-family:{$font};">
          <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{$preheader}</div>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{$this->canvas()};">
            <tr><td align="center" style="padding:32px 12px;">
              <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid {$this->line()};">
                <tr><td style="padding:0;line-height:0;font-size:0;">
                  <img src="{$headerSrc}" width="600" alt="Akademus" style="display:block;width:100%;max-width:600px;height:auto;border:0;">
                </td></tr>
                <tr><td class="px" style="padding:36px 40px 12px;font-family:{$font};">
                  {$eyebrowHtml}
                  <h1 class="h1" style="margin:0 0 18px;font-size:26px;line-height:1.25;font-weight:700;color:{$this->navy()};">{$title}</h1>
                  {$paragraphs}
                  {$details}
                  {$button}
                  {$fallback}
                  {$footnote}
                </td></tr>
                <tr><td class="px" style="padding:24px 40px 30px;">
                  <div style="border-top:1px solid {$this->line()};padding-top:18px;font-size:12px;line-height:1.6;color:{$this->muted()};">
                    <strong style="color:{$this->text()};">Akademus</strong> &middot; Student Information System<br>
                    This is an automated message, please don't reply to it.
                  </div>
                </td></tr>
              </table>
              <div style="max-width:600px;padding:16px 12px 0;font-size:11px;color:#94a3b8;font-family:{$font};">&copy; {$year} Akademus. All rights reserved.</div>
            </td></tr>
          </table>
        </body>
        </html>
        HTML;
    }

    /** Plain-text twin of the message (better deliverability, and for text-only readers). */
    public function renderText(array $c): string
    {
        $lines = [];
        if (! empty($c['eyebrow'])) {
            $lines[] = strtoupper($c['eyebrow']);
        }
        $lines[] = $c['title'] ?? '';
        $lines[] = '';
        foreach ($c['paragraphs'] ?? [] as $p) {
            $lines[] = $p;
            $lines[] = '';
        }
        foreach ($c['details'] ?? [] as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }
        if (! empty($c['details'])) {
            $lines[] = '';
        }
        if (! empty($c['button']['url'])) {
            $lines[] = ($c['button']['label'] ?? 'Continue') . ': ' . $c['button']['url'];
            $lines[] = '';
        }
        foreach ($c['footnote'] ?? [] as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
        $lines[] = '-- Akademus · Student Information System';

        return implode("\n", $lines);
    }

    /**
     * Build and send. Never throws; returns false (and logs) on any failure so
     * a mail problem can't break the request that triggered it.
     */
    public function send(string $to, string $subject, array $content): bool
    {
        try {
            $mailer = service('email');
            $this->prepare($mailer, $to, $subject, $content);

            if ($mailer->send(false)) {
                return true;
            }

            log_message('error', '[Email] "' . $subject . '" to ' . $to . ' not sent: ' . strip_tags($mailer->printDebugger(['headers'])));
        } catch (Throwable $e) {
            log_message('error', '[Email] "' . $subject . '" failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Fill a mailer with the recipient, subject, HTML + text bodies and the
     * inline header image. Split out from send() so the assembled message can
     * be inspected without touching SMTP.
     */
    public function prepare(\CodeIgniter\Email\Email $mailer, string $to, string $subject, array $content): void
    {
        $mailer->clear(true);

        $header = FCPATH . self::HEADER_IMAGE;
        $src    = '';

        if (is_file($header)) {
            // Do NOT pass a MIME type here: CodeIgniter then treats the first
            // argument as the file's raw *contents* instead of its path (and
            // would embed the path text as the "image"). Without it, the file
            // is read from disk and its type is taken from the extension.
            $mailer->attach($header, 'inline', 'akademus-header.jpg');
            $src = 'cid:' . $mailer->setAttachmentCID($header);
        }

        $mailer->setTo($to);
        $mailer->setSubject($subject);
        $mailer->setMessage($this->render($content, $src));
        $mailer->setAltMessage($this->renderText($content));
    }

    // Heredoc can't call constants directly.
    private function canvas(): string { return self::CANVAS; }
    private function line(): string   { return self::LINE; }
    private function navy(): string   { return self::NAVY; }
    private function muted(): string  { return self::MUTED; }
    private function text(): string   { return self::TEXT; }
}
