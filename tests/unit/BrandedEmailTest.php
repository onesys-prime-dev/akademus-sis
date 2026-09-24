<?php

use App\Libraries\BrandedEmail;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The shared email layout: everything user-supplied is escaped, the header
 * image and button are present, and a plain-text twin exists.
 *
 * @internal
 */
final class BrandedEmailTest extends CIUnitTestCase
{
    private function content(array $override = []): array
    {
        return array_merge([
            'preheader'  => 'Preview line',
            'eyebrow'    => 'Welcome aboard',
            'title'      => 'Welcome, Ana',
            'paragraphs' => ['First paragraph.'],
            'details'    => ['Student number' => 'STU-2026-00042', 'Sign-in email' => 'ana@example.com'],
            'button'     => ['label' => 'Set my password', 'url' => 'http://localhost/reset-password/abc123'],
            'footnote'   => ['Works for 7 days.'],
        ], $override);
    }

    public function testRendersEverySection(): void
    {
        $html = (new BrandedEmail())->render($this->content(), 'cid:hdr');

        $this->assertStringContainsString('src="cid:hdr"', $html);
        $this->assertStringContainsString('Welcome, Ana', $html);
        $this->assertStringContainsString('STU-2026-00042', $html);
        $this->assertStringContainsString('href="http://localhost/reset-password/abc123"', $html);
        $this->assertStringContainsString('Set my password', $html);
        $this->assertStringContainsString('Preview line', $html);
        $this->assertStringContainsString('Works for 7 days.', $html);
    }

    public function testEveryValueIsEscaped(): void
    {
        $html = (new BrandedEmail())->render($this->content([
            'title'      => '<script>alert(1)</script>',
            'paragraphs' => ['<img src=x onerror=alert(2)>'],
            'details'    => ['<b>label</b>' => '"><script>alert(3)</script>'],
            'button'     => ['label' => '<i>go</i>', 'url' => 'http://x/y"onmouseover="alert(4)'],
        ]), 'cid:hdr');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>label</b>', $html);
        $this->assertStringNotContainsString('"onmouseover=', $html, 'a quote in the URL must not break out of the href');
    }

    public function testSectionsAreOptional(): void
    {
        $html = (new BrandedEmail())->render(['title' => 'Just a title'], 'cid:hdr');

        $this->assertStringContainsString('Just a title', $html);
        $this->assertStringNotContainsString('Button not working', $html);
    }

    public function testPlainTextTwinCarriesTheSameFacts(): void
    {
        $text = (new BrandedEmail())->renderText($this->content());

        $this->assertStringContainsString('Welcome, Ana', $text);
        $this->assertStringContainsString('Student number: STU-2026-00042', $text);
        $this->assertStringContainsString('Set my password: http://localhost/reset-password/abc123', $text);
        $this->assertStringNotContainsString('<', $text);
    }

    public function testTheHeaderImageShipsInTheRepoAndStaysEmailSized(): void
    {
        $path = FCPATH . BrandedEmail::HEADER_IMAGE;

        $this->assertFileExists($path);
        $this->assertLessThan(150 * 1024, filesize($path), 'a header over ~150 KB slows every open of the email');
    }

    /**
     * Regression: passing a MIME type to Email::attach() makes it embed the
     * first argument's TEXT as the file, so the header arrived as a broken
     * image. Assemble the real message and decode the attached part.
     */
    public function testTheInlineHeaderIsARealImageTheHtmlPointsTo(): void
    {
        $mailer = new \CodeIgniter\Email\Email(new \Config\Email());
        (new BrandedEmail())->prepare($mailer, 'to@example.com', 'Subject', $this->content());
        $mailer->setFrom('from@example.com', 'Akademus'); // send() applies the configured sender; we bypass send()

        foreach (['buildHeaders', 'buildMessage'] as $method) {
            $ref = new \ReflectionMethod($mailer, $method);
            $ref->setAccessible(true);
            $ref->invoke($mailer);
        }
        $prop = new \ReflectionProperty($mailer, 'finalBody');
        $prop->setAccessible(true);
        $mime = (string) $prop->getValue($mailer);

        // The HTML part (quoted-printable) references the CID...
        $this->assertSame(1, preg_match('/Content-ID: <([^>]+)>/', $mime, $cid), 'no inline part with a Content-ID');
        $this->assertStringContainsString('Content-Type: multipart/related', $mime);
        $this->assertStringContainsString('cid:' . str_replace('=', '=3D', $cid[1]), str_replace(["=\r\n", "=\n"], '', $mime));

        // ...and that part decodes to the actual 1200x400 JPEG, not text.
        $afterHeaders = substr($mime, strpos($mime, 'Content-ID: <' . $cid[1] . '>'));
        $body         = preg_split('/\r?\n\r?\n/', $afterHeaders, 2)[1] ?? '';
        $base64       = preg_split('/\r?\n--/', $body, 2)[0];
        $bytes        = base64_decode(preg_replace('/\s+/', '', $base64), true);

        $this->assertNotFalse($bytes);
        $info = getimagesizefromstring($bytes);
        $this->assertNotFalse($info, 'the attachment is not an image');
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertSame(1200, $info[0]);
    }
}
