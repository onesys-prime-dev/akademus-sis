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
}
