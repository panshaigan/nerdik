<?php

namespace Tests\Unit\Support;

use App\Support\RichText;
use Tests\TestCase;

final class RichTextTest extends TestCase
{
    public function test_sanitize_preserves_float_left_image_class(): void
    {
        $html = '<p><img src="https://example.com/photo.jpg" alt="Photo" class="float-left"></p>';

        $clean = RichText::sanitize($html);

        $this->assertNotNull($clean);
        $this->assertStringContainsString('class="float-left"', $clean);
        $this->assertStringContainsString('src="https://example.com/photo.jpg"', $clean);
    }

    public function test_sanitize_preserves_inline_float_styles(): void
    {
        $html = '<p><img src="https://example.com/photo.jpg" alt="Photo" style="float:right;margin:0 0 0.75rem 1rem;"></p>';

        $clean = RichText::sanitize($html);

        $this->assertNotNull($clean);
        $this->assertStringContainsString('float:right', $clean);
        $this->assertMatchesRegularExpression('/margin[^"]*0\.75rem/', $clean);
    }

    public function test_sanitize_preserves_tables(): void
    {
        $html = '<table border="1"><thead><tr><th>Name</th></tr></thead><tbody><tr><td>Alice</td></tr></tbody></table>';

        $clean = RichText::sanitize($html);

        $this->assertNotNull($clean);
        $this->assertStringContainsString('<table', $clean);
        $this->assertStringContainsString('border="1"', $clean);
        $this->assertStringContainsString('<th>Name</th>', $clean);
        $this->assertStringContainsString('<td>Alice</td>', $clean);
    }

    public function test_sanitize_strips_scripts_and_dangerous_attributes(): void
    {
        $html = '<p onclick="alert(1)">Safe</p><script>alert(1)</script><img src="https://example.com/x.jpg" onerror="alert(1)">';

        $clean = RichText::sanitize($html);

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('Safe', $clean);
    }

    public function test_html_re_purifies_stored_float_and_table_content(): void
    {
        $stored = '<p><img src="https://example.com/a.jpg" class="float-right" style="float:right;"></p>'
            .'<table><tr><td>Cell</td></tr></table>';

        $output = (string) RichText::html($stored);

        $this->assertStringContainsString('float-right', $output);
        $this->assertStringContainsString('float:right', $output);
        $this->assertStringContainsString('<td>Cell</td>', $output);
    }
}
