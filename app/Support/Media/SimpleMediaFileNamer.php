<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Support\FileNamer\FileNamer;

final class SimpleMediaFileNamer extends FileNamer
{
    public const string ORIGINAL_BASENAME = 'original';

    public const string ORIGINAL_FILE_NAME = 'original.webp';

    public const string RESPONSIVE_BASENAME = 'responsive';

    public function originalFileName(string $fileName): string
    {
        return self::ORIGINAL_BASENAME;
    }

    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        return $conversion->getName();
    }

    public function responsiveFileName(string $fileName): string
    {
        return self::RESPONSIVE_BASENAME;
    }
}
