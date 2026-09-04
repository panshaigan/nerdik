<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

final class ConvertMediaOriginalToWebp
{
    /**
     * @return array{
     *     status: 'converted'|'skipped'|'failed',
     *     reason: ?string,
     *     source: ?string,
     *     bytes_before: int,
     *     bytes_after: int,
     *     orphans_removed: int,
     * }
     */
    public function __invoke(Media $media, bool $dryRun = false): array
    {
        $conversionPath = $this->resolveWebpConversionPath($media);
        $sourcePath = $this->resolveOriginalPath($media, $conversionPath);

        if ($sourcePath === null && $conversionPath === null) {
            $expected = $media->getPath();

            return $this->result(
                'failed',
                'missing_file:'.$expected,
                null,
                0,
                0,
                0,
            );
        }

        // Original missing but conversion exists: restore/shrink from conversion alone.
        if ($sourcePath === null && is_string($conversionPath)) {
            return $this->replaceOriginalFromConversion($media, $conversionPath, $dryRun, bytesBefore: 0);
        }

        if (! is_readable($sourcePath)) {
            return $this->result('failed', 'unreadable', null, File::size($sourcePath), 0, 0);
        }

        $bytesBefore = File::size($sourcePath);
        $useConversion = $this->shouldUseConversion($media, $sourcePath, $conversionPath);

        if (! $useConversion && ! $this->isPngOriginal($media)) {
            return $this->result(
                'skipped',
                $conversionPath === null ? 'no_smaller_conversion' : 'already_shrunk_or_conversion_not_smaller',
                null,
                $bytesBefore,
                0,
                0,
            );
        }

        if ($useConversion && is_string($conversionPath)) {
            return $this->replaceOriginalFromConversion($media, $conversionPath, $dryRun, $bytesBefore, $sourcePath);
        }

        return $this->encodeOriginalToWebp($media, $sourcePath, $dryRun, $bytesBefore);
    }

    public function isPngOriginal(Media $media): bool
    {
        if (strcasecmp((string) $media->mime_type, 'image/png') === 0) {
            return true;
        }

        return str_ends_with(strtolower((string) $media->file_name), '.png');
    }

    public function hasWebpConversionFlag(Media $media): bool
    {
        return $media->hasGeneratedConversion('webp');
    }

    /**
     * @return array{
     *     status: 'converted'|'skipped'|'failed',
     *     reason: ?string,
     *     source: ?string,
     *     bytes_before: int,
     *     bytes_after: int,
     *     orphans_removed: int,
     * }
     */
    private function replaceOriginalFromConversion(
        Media $media,
        string $conversionPath,
        bool $dryRun,
        int $bytesBefore,
        ?string $existingOriginalPath = null,
    ): array {
        $directory = dirname($media->getPath());
        $webpFileName = $this->originalFileNameFromConversion($conversionPath, $media);
        $targetPath = $directory.DIRECTORY_SEPARATOR.$webpFileName;

        if ($dryRun) {
            return $this->result(
                'converted',
                null,
                'conversion',
                $bytesBefore,
                File::size($conversionPath),
                $this->countOrphanResponsiveOriginals($media),
            );
        }

        $tempPath = $directory.DIRECTORY_SEPARATOR.'.'.$webpFileName.'.'.uniqid('', true).'.tmp';

        try {
            File::ensureDirectoryExists($directory);
            File::copy($conversionPath, $tempPath);
            File::move($tempPath, $targetPath);

            $bytesAfter = File::size($targetPath);

            // Delete previous original when it is a different path. Spatie's MediaObserver
            // renames the previous file onto the new name on save, which would overwrite
            // the WebP we just wrote — so remove it before saveQuietly.
            if (is_string($existingOriginalPath)
                && $existingOriginalPath !== $targetPath
                && File::isFile($existingOriginalPath)
            ) {
                File::delete($existingOriginalPath);
            }

            $registeredPath = $media->getPath();
            if ($registeredPath !== $targetPath && File::isFile($registeredPath)) {
                File::delete($registeredPath);
            }

            $media->file_name = $webpFileName;
            $media->mime_type = 'image/webp';
            $media->size = $bytesAfter;
            $this->syncDimensions($media, $targetPath);
            $media->saveQuietly();

            $orphansRemoved = $this->deleteOrphanResponsiveOriginals($media);

            return $this->result('converted', null, 'conversion', $bytesBefore, $bytesAfter, $orphansRemoved);
        } catch (\Throwable $exception) {
            if (File::isFile($tempPath)) {
                File::delete($tempPath);
            }

            return $this->result('failed', $exception->getMessage(), 'conversion', $bytesBefore, 0, 0);
        }
    }

    /**
     * @return array{
     *     status: 'converted'|'skipped'|'failed',
     *     reason: ?string,
     *     source: ?string,
     *     bytes_before: int,
     *     bytes_after: int,
     *     orphans_removed: int,
     * }
     */
    private function encodeOriginalToWebp(
        Media $media,
        string $sourcePath,
        bool $dryRun,
        int $bytesBefore,
    ): array {
        $directory = dirname($sourcePath);
        $webpFileName = pathinfo((string) $media->file_name, PATHINFO_FILENAME).'.webp';
        $targetPath = $directory.DIRECTORY_SEPARATOR.$webpFileName;

        if ($dryRun) {
            return $this->result(
                'converted',
                null,
                'encode',
                $bytesBefore,
                0,
                $this->countOrphanResponsiveOriginals($media),
            );
        }

        $tempPath = $directory.DIRECTORY_SEPARATOR.'.'.$webpFileName.'.'.uniqid('', true).'.tmp';

        try {
            Image::load($sourcePath)
                ->format('webp')
                ->quality((int) config('media.conversion_qualities.webp', 85))
                ->save($tempPath);

            if (! File::isFile($tempPath)) {
                throw new RuntimeException('WebP output was not created.');
            }

            File::move($tempPath, $targetPath);
            $bytesAfter = File::size($targetPath);

            if ($sourcePath !== $targetPath && File::isFile($sourcePath)) {
                File::delete($sourcePath);
            }

            $media->file_name = $webpFileName;
            $media->mime_type = 'image/webp';
            $media->size = $bytesAfter;
            $this->syncDimensions($media, $targetPath);
            $media->saveQuietly();

            $orphansRemoved = $this->deleteOrphanResponsiveOriginals($media);

            return $this->result('converted', null, 'encode', $bytesBefore, $bytesAfter, $orphansRemoved);
        } catch (\Throwable $exception) {
            if (File::isFile($tempPath)) {
                File::delete($tempPath);
            }

            return $this->result('failed', $exception->getMessage(), 'encode', $bytesBefore, 0, 0);
        }
    }

    private function shouldUseConversion(Media $media, string $sourcePath, ?string $conversionPath): bool
    {
        if ($conversionPath === null || ! File::isFile($conversionPath) || ! is_readable($conversionPath)) {
            return false;
        }

        if (realpath($conversionPath) === realpath($sourcePath)) {
            return false;
        }

        $conversionSize = File::size($conversionPath);
        $originalSize = File::size($sourcePath);

        if ($conversionSize < $originalSize) {
            return true;
        }

        return $this->isPngOriginal($media);
    }

    private function resolveOriginalPath(Media $media, ?string $conversionPath): ?string
    {
        $registered = $media->getPath();

        if (is_string($registered) && $registered !== '' && File::isFile($registered)) {
            return $registered;
        }

        $directory = dirname($registered);
        $stems = array_values(array_unique(array_filter([
            pathinfo((string) $media->file_name, PATHINFO_FILENAME),
            (string) $media->name,
            is_string($conversionPath)
                ? $this->stemFromConversionFileName(basename($conversionPath))
                : null,
        ])));

        foreach ($stems as $stem) {
            foreach (['webp', 'png', 'jpg', 'jpeg'] as $extension) {
                $candidate = $directory.DIRECTORY_SEPARATOR.$stem.'.'.$extension;

                if (File::isFile($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function resolveWebpConversionPath(Media $media): ?string
    {
        if ($media->hasGeneratedConversion('webp')) {
            try {
                $path = $media->getPath('webp');
            } catch (\Throwable) {
                $path = null;
            }

            if (is_string($path) && $path !== '' && File::isFile($path)) {
                return $path;
            }
        }

        $directory = dirname($media->getPath()).DIRECTORY_SEPARATOR.'conversions';

        if (! File::isDirectory($directory)) {
            return null;
        }

        $stems = array_values(array_unique(array_filter([
            pathinfo((string) $media->file_name, PATHINFO_FILENAME),
            (string) $media->name,
        ])));

        foreach ($stems as $stem) {
            $candidate = $directory.DIRECTORY_SEPARATOR.$stem.'-webp.webp';

            if (File::isFile($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function originalFileNameFromConversion(string $conversionPath, Media $media): string
    {
        $stem = $this->stemFromConversionFileName(basename($conversionPath));

        if ($stem !== '') {
            return $stem.'.webp';
        }

        return pathinfo((string) $media->file_name, PATHINFO_FILENAME).'.webp';
    }

    private function stemFromConversionFileName(string $conversionFileName): string
    {
        if (str_ends_with($conversionFileName, '-webp.webp')) {
            return substr($conversionFileName, 0, -strlen('-webp.webp'));
        }

        return pathinfo($conversionFileName, PATHINFO_FILENAME);
    }

    private function syncDimensions(Media $media, string $absolutePath): void
    {
        $imageSize = @getimagesize($absolutePath);

        if ($imageSize === false) {
            return;
        }

        $media->setCustomProperty('width', $imageSize[0]);
        $media->setCustomProperty('height', $imageSize[1]);
    }

    private function countOrphanResponsiveOriginals(Media $media): int
    {
        return count($this->orphanResponsiveOriginalPaths($media));
    }

    private function deleteOrphanResponsiveOriginals(Media $media): int
    {
        $removed = 0;

        foreach ($this->orphanResponsiveOriginalPaths($media) as $path) {
            if (File::delete($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return list<string>
     */
    private function orphanResponsiveOriginalPaths(Media $media): array
    {
        $responsiveRelative = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);
        $diskName = $media->conversions_disk ?: $media->disk;
        $diskRoot = rtrim(Storage::disk($diskName)->path(''), DIRECTORY_SEPARATOR);
        $responsiveAbsolute = $diskRoot.DIRECTORY_SEPARATOR.trim(str_replace('/', DIRECTORY_SEPARATOR, $responsiveRelative), DIRECTORY_SEPARATOR);

        if (! File::isDirectory($responsiveAbsolute)) {
            return [];
        }

        $paths = [];

        foreach (File::files($responsiveAbsolute) as $file) {
            if (str_contains($file->getFilename(), '___media_library_original_')) {
                $paths[] = $file->getPathname();
            }
        }

        return $paths;
    }

    /**
     * @return array{
     *     status: 'converted'|'skipped'|'failed',
     *     reason: ?string,
     *     source: ?string,
     *     bytes_before: int,
     *     bytes_after: int,
     *     orphans_removed: int,
     * }
     */
    private function result(
        string $status,
        ?string $reason,
        ?string $source,
        int $bytesBefore,
        int $bytesAfter,
        int $orphansRemoved,
    ): array {
        return [
            'status' => $status,
            'reason' => $reason,
            'source' => $source,
            'bytes_before' => $bytesBefore,
            'bytes_after' => $bytesAfter,
            'orphans_removed' => $orphansRemoved,
        ];
    }
}
