<?php

declare(strict_types=1);

namespace App\Actions\Images;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\Image\Image;

final class GenerateAppShellBackgroundVariants
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $config = config('media.app_shell_background');

        if (! is_array($config)) {
            throw new RuntimeException('media.app_shell_background config is missing.');
        }

        /** @var array<string, string> $sources */
        $sources = $config['sources'] ?? [];
        $outputDir = (string) ($config['output_dir'] ?? 'images/app/backgrounds');
        $manifest = [];

        foreach ($sources as $theme => $sourcePath) {
            $absoluteSource = public_path($sourcePath);

            if (! File::isFile($absoluteSource)) {
                throw new RuntimeException("App shell background source not found at [{$sourcePath}].");
            }

            $size = getimagesize($absoluteSource);

            if ($size === false) {
                throw new RuntimeException("Could not read dimensions for [{$sourcePath}].");
            }

            [$sourceWidth, $sourceHeight] = $size;
            $themeOutputDir = public_path("{$outputDir}/{$theme}");

            if (File::isDirectory($themeOutputDir)) {
                File::cleanDirectory($themeOutputDir);
            } else {
                File::ensureDirectoryExists($themeOutputDir);
            }

            $widths = $this->targetWidths($sourceWidth);
            $variants = ['webp' => []];

            foreach ($widths as $width) {
                $relativePath = "{$outputDir}/{$theme}/{$width}w.webp";
                $absolutePath = public_path($relativePath);

                Image::load($absoluteSource)
                    ->width($width)
                    ->format('webp')
                    ->quality($this->qualityFor($width))
                    ->save($absolutePath);

                $variants['webp'][] = [
                    'width' => $width,
                    'path' => $relativePath,
                    'bytes' => File::size($absolutePath),
                ];
            }

            $manifest[$theme] = [
                'sizes' => (string) ($config['sizes'] ?? '100vw'),
                'width' => $sourceWidth,
                'height' => $sourceHeight,
                'variants' => $variants,
            ];
        }

        $manifestPath = public_path("{$outputDir}/manifest.json");
        File::put(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        return $manifest;
    }

    /**
     * @return list<int>
     */
    private function targetWidths(int $sourceWidth): array
    {
        $config = config('media.app_shell_background', []);
        /** @var list<int> $mobileWidths */
        $mobileWidths = $config['mobile_widths'] ?? [];
        /** @var list<int> $desktopWidths */
        $desktopWidths = $config['desktop_widths'] ?? [];
        $minWidth = (int) config('media.min_responsive_width', 20);

        return collect([...$mobileWidths, ...$desktopWidths])
            ->map(fn ($width): int => (int) $width)
            ->filter(fn (int $width): bool => $width >= $minWidth && $width <= $sourceWidth)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function qualityFor(int $width): int
    {
        $mobileMaxWidth = (int) config('media.app_shell_background.mobile_max_width', 640);
        $largeMinWidth = (int) config('media.app_shell_background.large_min_width', 1536);

        if ($width >= $largeMinWidth) {
            $tier = 'large';
        } elseif ($width <= $mobileMaxWidth) {
            $tier = 'mobile';
        } else {
            $tier = 'desktop';
        }

        $configured = config("media.app_shell_background.qualities.{$tier}.webp");

        if (is_numeric($configured)) {
            return (int) $configured;
        }

        return (int) config('media.conversion_qualities.webp', 85);
    }
}
