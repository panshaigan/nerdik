<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\WidthCalculator;

final class ConfiguredWidthCalculator implements WidthCalculator
{
    public function calculateWidthsFromFile(string $imagePath): Collection
    {
        $size = @getimagesize($imagePath);

        if ($size === false) {
            return collect();
        }

        return $this->calculateWidths(0, $size[0], $size[1]);
    }

    public function calculateWidths(int $fileSize, int $width, int $height): Collection
    {
        unset($fileSize, $height);

        $minWidth = (int) config('media.min_responsive_width', 20);
        $configured = config('media.responsive_widths', []);

        return collect($configured)
            ->map(fn ($w): int => (int) $w)
            ->filter(fn (int $w): bool => $w >= $minWidth && $w <= $width)
            ->unique()
            ->sort()
            ->values();
    }
}
