<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

final class NormalizeMediaOriginal
{
    /**
     * @return array{
     *     path: string,
     *     width: int|null,
     *     height: int|null,
     *     temporary: bool,
     *     changed: bool,
     * }
     */
    public function __invoke(string $absolutePath, ?int $maxWidth = null, ?int $maxHeight = null): array
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException("Media original is not readable [{$absolutePath}].");
        }

        $maxWidth ??= (int) config('media.max_edge', 1536);
        $maxHeight ??= (int) config('media.max_edge', 1536);
        $quality = (int) config('media.conversion_qualities.webp', 92);

        $imageSize = @getimagesize($absolutePath);
        $width = is_array($imageSize) ? (int) $imageSize[0] : null;
        $height = is_array($imageSize) ? (int) $imageSize[1] : null;

        $needsResize = ($width !== null && $width > $maxWidth)
            || ($height !== null && $height > $maxHeight);
        $isWebp = $this->isWebp($absolutePath);

        if (! $needsResize && $isWebp) {
            return [
                'path' => $absolutePath,
                'width' => $width,
                'height' => $height,
                'temporary' => false,
                'changed' => false,
            ];
        }

        $tempPath = dirname($absolutePath)
            .DIRECTORY_SEPARATOR
            .'.normalize-'.uniqid('', true).'.webp';

        try {
            Image::load($absolutePath)
                ->fit(Fit::Max, $maxWidth, $maxHeight)
                ->format('webp')
                ->quality($quality)
                ->save($tempPath);

            if (! File::isFile($tempPath)) {
                throw new RuntimeException('Normalized WebP output was not created.');
            }

            $encodedSize = File::size($tempPath);
            $sourceSize = File::size($absolutePath);

            if ($isWebp && ! $needsResize && $encodedSize >= $sourceSize) {
                File::delete($tempPath);

                return [
                    'path' => $absolutePath,
                    'width' => $width,
                    'height' => $height,
                    'temporary' => false,
                    'changed' => false,
                ];
            }

            $normalizedSize = @getimagesize($tempPath);

            return [
                'path' => $tempPath,
                'width' => is_array($normalizedSize) ? (int) $normalizedSize[0] : $width,
                'height' => is_array($normalizedSize) ? (int) $normalizedSize[1] : $height,
                'temporary' => true,
                'changed' => true,
            ];
        } catch (\Throwable $exception) {
            if (File::isFile($tempPath)) {
                File::delete($tempPath);
            }

            throw $exception;
        }
    }

    private function isWebp(string $absolutePath): bool
    {
        $mime = @mime_content_type($absolutePath);

        if (is_string($mime) && strcasecmp($mime, 'image/webp') === 0) {
            return true;
        }

        return str_ends_with(strtolower($absolutePath), '.webp');
    }
}
