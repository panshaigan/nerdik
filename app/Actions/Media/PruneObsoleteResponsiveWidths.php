<?php

declare(strict_types=1);

namespace App\Actions\Media;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory;

final class PruneObsoleteResponsiveWidths
{
    /**
     * @return array{
     *     files_removed: int,
     *     urls_pruned: int,
     * }
     */
    public function __invoke(Media $media, bool $dryRun = false): array
    {
        $allowedWidths = $this->allowedWidths();
        $filesystem = app(Filesystem::class);
        $directory = PathGeneratorFactory::create($media)->getPathForResponsiveImages($media);

        $filesRemoved = 0;
        $urlsPruned = 0;
        $responsiveImages = $media->responsive_images;

        if (is_array($responsiveImages) && $responsiveImages !== []) {
            $updated = false;

            foreach ($responsiveImages as $conversionName => $conversionData) {
                if (! is_array($conversionData)) {
                    continue;
                }

                $urls = $conversionData['urls'] ?? [];

                if (! is_array($urls) || $urls === []) {
                    continue;
                }

                $keptUrls = [];
                $keptBase64 = $conversionData['base64svg'] ?? null;

                foreach ($urls as $fileName) {
                    if (! is_string($fileName) || $fileName === '') {
                        continue;
                    }

                    $width = $this->responsiveFileWidth($fileName);

                    if ($width !== null && ! in_array($width, $allowedWidths, true)) {
                        $urlsPruned++;

                        if (! $dryRun) {
                            $filesystem->removeFile($media, $directory.$fileName);
                            $filesRemoved++;
                        }

                        continue;
                    }

                    $keptUrls[] = $fileName;
                }

                if (count($keptUrls) !== count($urls)) {
                    $updated = true;

                    if (! $dryRun) {
                        if ($keptUrls === []) {
                            unset($responsiveImages[$conversionName]);
                        } else {
                            $responsiveImages[$conversionName] = [
                                'urls' => $keptUrls,
                            ];

                            if (is_string($keptBase64) && $keptBase64 !== '') {
                                $responsiveImages[$conversionName]['base64svg'] = $keptBase64;
                            }
                        }
                    }
                }
            }

            if ($updated && ! $dryRun) {
                $media->responsive_images = $responsiveImages;
                $media->save();
            }
        }

        $diskOrphans = $this->pruneOrphanResponsiveFilesOnDisk(
            $media,
            $allowedWidths,
            $filesystem,
            $directory,
            $dryRun,
        );

        return [
            'files_removed' => $filesRemoved + $diskOrphans,
            'urls_pruned' => $urlsPruned,
        ];
    }

    private function pruneOrphanResponsiveFilesOnDisk(
        Media $media,
        array $allowedWidths,
        Filesystem $filesystem,
        string $directory,
        bool $dryRun,
    ): int {
        $disk = Storage::disk($media->disk);
        $filesRemoved = 0;

        if (! $disk->exists(rtrim($directory, '/'))) {
            return 0;
        }

        $indexedUrls = $this->indexedResponsiveUrls($media);

        foreach ($disk->files($directory) as $relativePath) {
            $fileName = basename($relativePath);
            $width = $this->responsiveFileWidth($fileName);

            if ($width === null) {
                continue;
            }

            if (in_array($width, $allowedWidths, true)) {
                continue;
            }

            if (isset($indexedUrls[$fileName])) {
                continue;
            }

            if (! $dryRun) {
                $filesystem->removeFile($media, $directory.$fileName);
            }

            $filesRemoved++;
        }

        return $filesRemoved;
    }

    /**
     * @return array<string, true>
     */
    private function indexedResponsiveUrls(Media $media): array
    {
        $indexed = [];
        $responsiveImages = $media->responsive_images;

        if (! is_array($responsiveImages)) {
            return $indexed;
        }

        foreach ($responsiveImages as $conversionData) {
            if (! is_array($conversionData)) {
                continue;
            }

            foreach ($conversionData['urls'] ?? [] as $fileName) {
                if (is_string($fileName) && $fileName !== '') {
                    $indexed[$fileName] = true;
                }
            }
        }

        return $indexed;
    }

    /**
     * @return list<int>
     */
    private function allowedWidths(): array
    {
        /** @var list<int|string> $widths */
        $widths = config('media.responsive_widths', [128, 384, 768, 1536]);

        return array_values(array_map(intval(...), $widths));
    }

    private function responsiveFileWidth(string $fileName): ?int
    {
        if (! str_contains($fileName, '___')) {
            return null;
        }

        if (! preg_match('/___[^_]+_(\d+)_\d+\./', $fileName, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
