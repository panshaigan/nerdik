<?php

declare(strict_types=1);

namespace App\Support\Media;

use InvalidArgumentException;
use RuntimeException;

final class VisibleImageBounds
{
    /**
     * Bounding box around the bright logo core, expanded so the dark stroke
     * (visible on light backgrounds) is not clipped.
     *
     * Soft ambient glow beyond the stroke is intentionally excluded — that glow
     * is nearly invisible on dark UI but reads as empty layout space.
     *
     * @return array{x: int, y: int, width: int, height: int}
     */
    public static function forPath(
        string $absolutePath,
        int $minLuminance = 40,
        int $maxGdAlpha = 100,
        int $padding = 96,
    ): array {
        if ($minLuminance < 0 || $minLuminance > 255) {
            throw new InvalidArgumentException("Invalid min luminance [{$minLuminance}].");
        }

        if ($maxGdAlpha < 0 || $maxGdAlpha > 127) {
            throw new InvalidArgumentException("Invalid max GD alpha [{$maxGdAlpha}].");
        }

        if ($padding < 0) {
            throw new InvalidArgumentException("Invalid padding [{$padding}].");
        }

        $image = @imagecreatefromwebp($absolutePath);

        if ($image === false) {
            $image = @imagecreatefromstring((string) file_get_contents($absolutePath));
        }

        if ($image === false) {
            throw new RuntimeException("Could not read image for visible bounds at [{$absolutePath}].");
        }

        try {
            $canvasWidth = imagesx($image);
            $canvasHeight = imagesy($image);
            $minX = $canvasWidth;
            $minY = $canvasHeight;
            $maxX = -1;
            $maxY = -1;

            for ($y = 0; $y < $canvasHeight; $y++) {
                for ($x = 0; $x < $canvasWidth; $x++) {
                    $color = imagecolorat($image, $x, $y);
                    $alpha = ($color & 0x7F000000) >> 24;

                    if ($alpha > $maxGdAlpha) {
                        continue;
                    }

                    $red = ($color >> 16) & 0xFF;
                    $green = ($color >> 8) & 0xFF;
                    $blue = $color & 0xFF;
                    $luminance = (int) round(($red + $green + $blue) / 3);

                    if ($luminance < $minLuminance) {
                        continue;
                    }

                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }

            if ($maxX < 0) {
                throw new RuntimeException("No visible pixels found in [{$absolutePath}].");
            }

            $minX = max(0, $minX - $padding);
            $minY = max(0, $minY - $padding);
            $maxX = min($canvasWidth - 1, $maxX + $padding);
            $maxY = min($canvasHeight - 1, $maxY + $padding);

            return [
                'x' => $minX,
                'y' => $minY,
                'width' => $maxX - $minX + 1,
                'height' => $maxY - $minY + 1,
            ];
        } finally {
            unset($image);
        }
    }
}
