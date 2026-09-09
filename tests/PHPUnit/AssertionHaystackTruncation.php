<?php

declare(strict_types=1);

namespace Tests\PHPUnit;

use PHPUnit\Util\Exporter;

/**
 * Configurable truncation for PHPUnit string-contains failure haystacks.
 */
final class AssertionHaystackTruncation
{
    private const int DEFAULT_MAX_LENGTH = 800;

    private static int $maxLength = self::DEFAULT_MAX_LENGTH;

    public static function configure(int $maxLength): void
    {
        self::$maxLength = max(40, $maxLength);
    }

    public static function maxLength(): int
    {
        return self::$maxLength;
    }

    public static function export(mixed $haystack): string
    {
        if (is_string($haystack) && mb_strlen($haystack) > self::$maxLength) {
            return Exporter::shortenedExport($haystack, self::$maxLength);
        }

        return Exporter::export($haystack);
    }
}
