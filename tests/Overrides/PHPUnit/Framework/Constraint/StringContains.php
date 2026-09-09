<?php

declare(strict_types=1);

/*
 * Project override of PHPUnit's StringContains constraint.
 *
 * Loaded via composer autoload-dev classmap + exclude-from-classmap so
 * assertSee / assertStringContainsString failures truncate long HTML haystacks.
 */

namespace PHPUnit\Framework\Constraint;

use Tests\PHPUnit\AssertionHaystackTruncation;

use function is_string;
use function mb_detect_encoding;
use function mb_stripos;
use function mb_strtolower;
use function sprintf;
use function str_contains;
use function strlen;
use function strtr;

/**
 * @no-named-arguments Parameter names are not covered by the backward compatibility promise for PHPUnit
 */
final class StringContains extends Constraint
{
    private readonly string $needle;

    private readonly bool $ignoreCase;

    private readonly bool $ignoreLineEndings;

    public function __construct(string $needle, bool $ignoreCase = false, bool $ignoreLineEndings = false)
    {
        if ($ignoreLineEndings) {
            $needle = $this->normalizeLineEndings($needle);
        }

        $this->needle = $needle;
        $this->ignoreCase = $ignoreCase;
        $this->ignoreLineEndings = $ignoreLineEndings;
    }

    /**
     * Returns a string representation of the constraint.
     */
    public function toString(): string
    {
        $needle = $this->needle;

        if ($this->ignoreCase) {
            $needle = mb_strtolower($this->needle, 'UTF-8');
        }

        return sprintf(
            'contains "%s" [%s](length: %s)',
            $needle,
            $this->detectedEncoding($needle),
            strlen($needle),
        );
    }

    public function failureDescription(mixed $other): string
    {
        $stringifiedHaystack = AssertionHaystackTruncation::export($other);
        $haystackEncoding = $this->detectedEncoding($other);
        $haystackLength = $this->haystackLength($other);

        $haystackInformation = sprintf(
            '%s [%s](length: %s) ',
            $stringifiedHaystack,
            $haystackEncoding,
            $haystackLength,
        );

        return $haystackInformation.$this->toString();
    }

    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     */
    protected function matches(mixed $other): bool
    {
        $haystack = $other;

        if ($this->needle === '') {
            return true;
        }

        if (! is_string($haystack)) {
            return false;
        }

        if ($this->ignoreLineEndings) {
            $haystack = $this->normalizeLineEndings($haystack);
        }

        if ($this->ignoreCase) {
            return mb_stripos($haystack, $this->needle, 0, 'UTF-8') !== false;
        }

        return str_contains($haystack, $this->needle);
    }

    private function detectedEncoding(mixed $other): string
    {
        if ($this->ignoreCase) {
            return 'Encoding ignored';
        }

        if (! is_string($other)) {
            return 'Encoding detection failed';
        }

        $detectedEncoding = mb_detect_encoding($other, null, true);

        if ($detectedEncoding === false) {
            return 'Encoding detection failed';
        }

        return $detectedEncoding;
    }

    private function haystackLength(mixed $haystack): int
    {
        if (! is_string($haystack)) {
            return 0;
        }

        if ($this->ignoreLineEndings) {
            $haystack = $this->normalizeLineEndings($haystack);
        }

        return strlen($haystack);
    }

    private function normalizeLineEndings(string $string): string
    {
        return strtr(
            $string,
            [
                "\r\n" => "\n",
                "\r" => "\n",
            ],
        );
    }
}
