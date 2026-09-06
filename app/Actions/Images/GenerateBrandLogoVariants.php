<?php

declare(strict_types=1);

namespace App\Actions\Images;

use App\Support\Media\VisibleImageBounds;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Image\Image;

final class GenerateBrandLogoVariants
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $config = config('media.brand_logo');

        if (! is_array($config)) {
            throw new RuntimeException('media.brand_logo config is missing.');
        }

        $sourcePath = (string) ($config['source'] ?? '');
        $absoluteSource = base_path($sourcePath);

        if (! File::isFile($absoluteSource)) {
            throw new RuntimeException("Brand logo source not found at [{$sourcePath}].");
        }

        $trimConfig = is_array($config['trim'] ?? null) ? $config['trim'] : [];
        $trimEnabled = (bool) ($trimConfig['enabled'] ?? true);
        $workingSource = $absoluteSource;
        $temporaryTrimmedPath = null;

        try {
            if ($trimEnabled) {
                $bounds = VisibleImageBounds::forPath(
                    absolutePath: $absoluteSource,
                    minLuminance: (int) ($trimConfig['min_luminance'] ?? 40),
                    maxGdAlpha: (int) ($trimConfig['max_gd_alpha'] ?? 100),
                    padding: (int) ($trimConfig['padding'] ?? 96),
                );

                $temporaryTrimmedPath = storage_path('app/brand-logo-trimmed-'.getmypid().'.webp');
                $quality = (int) ($config['quality'] ?? config('media.conversion_qualities.webp', 85));

                Image::load($absoluteSource)
                    ->manualCrop($bounds['width'], $bounds['height'], $bounds['x'], $bounds['y'])
                    ->format('webp')
                    ->quality($quality)
                    ->save($temporaryTrimmedPath);

                $workingSource = $temporaryTrimmedPath;
            }

            $size = getimagesize($workingSource);

            if ($size === false) {
                throw new RuntimeException('Could not read dimensions for brand logo working source.');
            }

            [$sourceWidth, $sourceHeight] = $size;
            $outputDir = (string) ($config['output_dir'] ?? 'images/app/brand');
            $absoluteOutputDir = public_path($outputDir);

            if (File::isDirectory($absoluteOutputDir)) {
                File::cleanDirectory($absoluteOutputDir);
            } else {
                File::ensureDirectoryExists($absoluteOutputDir);
            }

            /** @var list<int> $widths */
            $widths = $config['widths'] ?? [];
            $minWidth = (int) config('media.min_responsive_width', 20);
            $quality = (int) ($config['quality'] ?? config('media.conversion_qualities.webp', 85));
            $variants = ['webp' => []];

            foreach ($widths as $width) {
                $width = (int) $width;

                if ($width < $minWidth || $width > $sourceWidth) {
                    continue;
                }

                $relativePath = "{$outputDir}/{$width}w.webp";
                $absolutePath = public_path($relativePath);

                Image::load($workingSource)
                    ->width($width)
                    ->format('webp')
                    ->quality($quality)
                    ->save($absolutePath);

                $variants['webp'][] = [
                    'width' => $width,
                    'path' => $relativePath,
                    'bytes' => File::size($absolutePath),
                ];
            }

            if ($variants['webp'] === []) {
                throw new RuntimeException('No brand logo variants were generated.');
            }

            usort($variants['webp'], fn (array $a, array $b): int => $a['width'] <=> $b['width']);

            $manifest = [
                'width' => $sourceWidth,
                'height' => $sourceHeight,
                'trimmed' => $trimEnabled,
                'variants' => $variants,
            ];

            File::put(
                public_path("{$outputDir}/manifest.json"),
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
            );

            return $manifest;
        } finally {
            if (is_string($temporaryTrimmedPath) && File::isFile($temporaryTrimmedPath)) {
                File::delete($temporaryTrimmedPath);
            }
        }
    }
}
