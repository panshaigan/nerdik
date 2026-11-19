<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImageGenerator;

final class SimpleResponsiveImageGenerator extends ResponsiveImageGenerator
{
    protected function addPropertiesToFileName(string $fileName, string $conversionName, int $width, int $height, string $extension): string
    {
        unset($fileName, $conversionName, $height);

        return SimpleMediaFileNamer::RESPONSIVE_BASENAME."_{$width}.{$extension}";
    }
}
