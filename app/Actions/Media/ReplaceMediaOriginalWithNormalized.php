<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\Media;
use App\Support\Media\SimpleMediaFileNamer;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImageGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

final class ReplaceMediaOriginalWithNormalized
{
    public function __construct(
        private readonly NormalizeMediaOriginal $normalizeMediaOriginal,
    ) {}

    /**
     * @return array{
     *     status: 'normalized'|'unchanged'|'failed',
     *     reason: ?string,
     *     bytes_before: int,
     *     bytes_after: int,
     * }
     */
    public function __invoke(Media $media, bool $dryRun = false, bool $regenerateResponsives = true): array
    {
        $originalPath = $media->getPath();

        if (! is_string($originalPath) || $originalPath === '' || ! File::isFile($originalPath)) {
            return [
                'status' => 'failed',
                'reason' => 'missing_original',
                'bytes_before' => 0,
                'bytes_after' => 0,
            ];
        }

        $bytesBefore = File::size($originalPath);

        try {
            $normalized = ($this->normalizeMediaOriginal)($originalPath);
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'reason' => $exception->getMessage(),
                'bytes_before' => $bytesBefore,
                'bytes_after' => 0,
            ];
        }

        if (! $normalized['changed']) {
            $this->ensureOriginalFileName($media, $dryRun);

            return [
                'status' => 'unchanged',
                'reason' => null,
                'bytes_before' => $bytesBefore,
                'bytes_after' => $bytesBefore,
            ];
        }

        $bytesAfter = File::size($normalized['path']);

        if ($dryRun) {
            if ($normalized['temporary']) {
                File::delete($normalized['path']);
            }

            return [
                'status' => 'normalized',
                'reason' => null,
                'bytes_before' => $bytesBefore,
                'bytes_after' => $bytesAfter,
            ];
        }

        $directory = dirname($originalPath);
        $newFileName = SimpleMediaFileNamer::ORIGINAL_FILE_NAME;
        $targetPath = $directory.DIRECTORY_SEPARATOR.$newFileName;

        File::move($normalized['path'], $targetPath);

        if ($originalPath !== $targetPath && File::isFile($originalPath)) {
            File::delete($originalPath);
        }

        $media->file_name = $newFileName;
        $media->mime_type = 'image/webp';
        $media->size = $bytesAfter;
        $media->setCustomProperty('width', $normalized['width']);
        $media->setCustomProperty('height', $normalized['height']);
        $media->saveQuietly();

        if ($regenerateResponsives) {
            $this->regenerateOriginalResponsives($media);
        }

        return [
            'status' => 'normalized',
            'reason' => null,
            'bytes_before' => $bytesBefore,
            'bytes_after' => $bytesAfter,
        ];
    }

    public function regenerateOriginalResponsives(Media $media): void
    {
        $pathGenerator = PathGeneratorFactory::create($media);
        $responsiveDir = $pathGenerator->getPathForResponsiveImages($media);

        // Clear legacy conversion-keyed responsive metadata so originals regenerate cleanly.
        $responsive = $media->responsive_images;
        if (is_array($responsive)) {
            unset($responsive['webp'], $responsive['avif']);
            $media->responsive_images = $responsive;
            $media->saveQuietly();
        }

        app(ResponsiveImageGenerator::class)->generateResponsiveImages($media);
        unset($responsiveDir);
    }

    private function ensureOriginalFileName(Media $media, bool $dryRun): void
    {
        if ($media->file_name === SimpleMediaFileNamer::ORIGINAL_FILE_NAME) {
            return;
        }

        $originalPath = $media->getPath();

        if (! is_string($originalPath) || ! File::isFile($originalPath)) {
            return;
        }

        if ($dryRun) {
            return;
        }

        $targetPath = dirname($originalPath).DIRECTORY_SEPARATOR.SimpleMediaFileNamer::ORIGINAL_FILE_NAME;

        if ($originalPath !== $targetPath && File::isFile($targetPath)) {
            File::delete($targetPath);
        }

        if ($originalPath !== $targetPath) {
            File::move($originalPath, $targetPath);
        }

        $media->file_name = SimpleMediaFileNamer::ORIGINAL_FILE_NAME;
        $media->mime_type = 'image/webp';
        $media->size = File::size($targetPath);
        $media->saveQuietly();
    }
}
