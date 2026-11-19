<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\Media;
use App\Support\Media\SimpleMediaFileNamer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImageGenerator;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

final class RenameSimpleMediaFilenames
{
    public function __construct(
        private readonly ReplaceMediaOriginalWithNormalized $replaceMediaOriginalWithNormalized,
    ) {}

    /**
     * @return array{
     *     status: 'renamed'|'unchanged'|'failed',
     *     reason: ?string,
     * }
     */
    public function __invoke(Media $media, bool $dryRun = false, bool $regenerateResponsives = true): array
    {
        if (! $this->isOptimizedCollection($media->collection_name)) {
            return [
                'status' => 'unchanged',
                'reason' => 'skipped_collection',
            ];
        }

        $targetFileName = SimpleMediaFileNamer::ORIGINAL_FILE_NAME;
        $needsOriginalRename = $media->file_name !== $targetFileName;
        $hasLegacyResponsive = $this->hasLegacyResponsiveFiles($media);

        if (! $needsOriginalRename && ! $hasLegacyResponsive) {
            if (! $dryRun && $regenerateResponsives && ! $media->hasResponsiveImages()) {
                $this->replaceMediaOriginalWithNormalized->regenerateOriginalResponsives($media);
            }

            return [
                'status' => 'unchanged',
                'reason' => null,
            ];
        }

        if ($dryRun) {
            return [
                'status' => 'renamed',
                'reason' => null,
            ];
        }

        $originalPath = $media->getPath();

        if (! is_string($originalPath) || ! File::isFile($originalPath)) {
            return [
                'status' => 'failed',
                'reason' => 'missing_original',
            ];
        }

        $directory = dirname($originalPath);
        $targetPath = $directory.DIRECTORY_SEPARATOR.$targetFileName;

        if ($needsOriginalRename) {
            if ($originalPath !== $targetPath && File::isFile($targetPath)) {
                File::delete($targetPath);
            }

            if ($originalPath !== $targetPath) {
                File::move($originalPath, $targetPath);
            }

            $media->file_name = $targetFileName;
            $media->mime_type = 'image/webp';
            $media->size = File::size($targetPath);
            $media->saveQuietly();
        }

        $this->clearResponsiveArtifacts($media);

        if ($regenerateResponsives) {
            app(ResponsiveImageGenerator::class)->generateResponsiveImages($media->fresh() ?? $media);
        }

        return [
            'status' => 'renamed',
            'reason' => null,
        ];
    }

    private function isOptimizedCollection(string $collectionName): bool
    {
        return in_array($collectionName, ['images', 'logo', 'gallery', 'event_listing'], true);
    }

    private function hasLegacyResponsiveFiles(Media $media): bool
    {
        $responsive = $media->responsive_images;

        if (is_array($responsive)) {
            foreach ($responsive as $conversionData) {
                if (! is_array($conversionData)) {
                    continue;
                }

                foreach ($conversionData['urls'] ?? [] as $fileName) {
                    if (! is_string($fileName)) {
                        continue;
                    }

                    if (str_contains($fileName, '___') || ! str_starts_with($fileName, SimpleMediaFileNamer::RESPONSIVE_BASENAME.'_')) {
                        return true;
                    }
                }
            }
        }

        $directory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);
        $disk = Storage::disk($media->disk);

        if (! $disk->exists(rtrim($directory, '/'))) {
            return false;
        }

        foreach ($disk->files($directory) as $relativePath) {
            $fileName = basename($relativePath);

            if (str_contains($fileName, '___') || ! preg_match('/^'.preg_quote(SimpleMediaFileNamer::RESPONSIVE_BASENAME, '/').'_\d+\.webp$/', $fileName)) {
                return true;
            }
        }

        return false;
    }

    private function clearResponsiveArtifacts(Media $media): void
    {
        $pathGenerator = PathGeneratorFactory::create($media);
        $responsiveDir = $pathGenerator->getPathForResponsiveImages($media);
        $disk = Storage::disk($media->disk);

        if ($disk->exists(rtrim($responsiveDir, '/'))) {
            foreach ($disk->files($responsiveDir) as $relativePath) {
                $disk->delete($relativePath);
            }
        }

        $media->responsive_images = [];
        $media->saveQuietly();
    }
}
