<?php

declare(strict_types=1);

namespace Tests\Unit\PHPUnit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Tests\PHPUnit\AssertionHaystackTruncation;

final class TruncateLongStringExportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        AssertionHaystackTruncation::configure(120);
    }

    #[Test]
    public function haystack_export_shortens_long_strings(): void
    {
        $haystack = str_repeat('x', 500).'NEEDLE_TAIL';

        $exported = AssertionHaystackTruncation::export($haystack);

        $this->assertLessThan(strlen($haystack), strlen($exported));
        $this->assertLessThanOrEqual(140, mb_strlen($exported));
        $this->assertStringContainsString('...', $exported);
    }

    #[Test]
    public function haystack_export_keeps_short_strings_intact(): void
    {
        $this->assertSame(
            "'short-html-snippet'",
            AssertionHaystackTruncation::export('short-html-snippet'),
        );
    }

    #[Test]
    public function string_contains_failure_message_does_not_dump_entire_haystack(): void
    {
        $needle = 'missing-marker-xyz';
        $haystack = '<!DOCTYPE html><html><body>'.str_repeat('page-body-', 200).'</body></html>';

        try {
            $this->assertStringContainsString($needle, $haystack);
            $this->fail('Expected assertion to fail.');
        } catch (ExpectationFailedException $exception) {
            $message = $exception->getMessage();

            $this->assertStringContainsString($needle, $message);
            $this->assertStringContainsString('(length: '.strlen($haystack).')', $message);
            $this->assertLessThan(strlen($haystack), strlen($message));
            $this->assertLessThan(400, strlen($message));
        }
    }
}
