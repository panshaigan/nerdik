<?php

declare(strict_types=1);

namespace App\Support\Media;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

final class BrandLogoSources
{
    /**
     * Wordmark font-size as a fraction of logo display height.
     * Slightly above half so the name reads as a lockup, not a caption.
     */
    private const WORDMARK_FONT_SIZE_RATIO = 0.7;

    /**
     * @param  array<string, list<array{width: int, path: string, bytes: int}>>  $variants
     */
    private function __construct(
        private readonly array $variants,
        private readonly int $intrinsicWidth,
        private readonly int $intrinsicHeight,
    ) {}

    public static function fromManifest(): self
    {
        $manifestPath = public_path('images/app/brand/manifest.json');

        if (! File::isFile($manifestPath)) {
            throw new RuntimeException('Brand logo manifest not found. Run: php artisan app:generate-brand-logo');
        }

        $manifest = json_decode(File::get($manifestPath), true);

        if (! is_array($manifest) || ! isset($manifest['variants']) || ! is_array($manifest['variants'])) {
            throw new RuntimeException('Brand logo manifest is invalid.');
        }

        /** @var array<string, list<array{width: int, path: string, bytes: int}>> $variants */
        $variants = $manifest['variants'];

        return new self(
            variants: $variants,
            intrinsicWidth: (int) ($manifest['width'] ?? 0),
            intrinsicHeight: (int) ($manifest['height'] ?? 0),
        );
    }

    /**
     * @return array{src: string, srcset: string, width: int, height: int, wordmark_font_size: int}
     */
    public function forPreset(string $preset): array
    {
        /** @var array<string, array{display_width: int, variant_width: int, retina_variant_width: int}> $presets */
        $presets = config('media.brand_logo.presets', []);

        if (! isset($presets[$preset])) {
            throw new InvalidArgumentException("Unknown brand logo preset [{$preset}].");
        }

        $presetConfig = $presets[$preset];
        $displayWidth = (int) $presetConfig['display_width'];
        $displayHeight = $this->displayHeightForWidth($displayWidth);
        $variantWidth = (int) $presetConfig['variant_width'];
        $retinaVariantWidth = (int) $presetConfig['retina_variant_width'];

        $src = $this->urlForWidth($variantWidth);
        $retinaSrc = $this->urlForWidth($retinaVariantWidth);

        return [
            'src' => $src,
            'srcset' => "{$src} 1x, {$retinaSrc} 2x",
            'width' => $displayWidth,
            'height' => $displayHeight,
            'wordmark_font_size' => self::wordmarkFontSizeForHeight($displayHeight),
        ];
    }

    public static function wordmarkFontSizeForHeight(int $displayHeight): int
    {
        $halfHeight = (int) ceil($displayHeight / 2);
        $preferredSize = (int) round($displayHeight * self::WORDMARK_FONT_SIZE_RATIO);

        return max(1, $halfHeight, $preferredSize);
    }

    public function intrinsicWidth(): int
    {
        return $this->intrinsicWidth;
    }

    public function intrinsicHeight(): int
    {
        return $this->intrinsicHeight;
    }

    private function urlForWidth(int $width): string
    {
        $entries = $this->variants['webp'] ?? [];

        foreach ($entries as $entry) {
            if ($entry['width'] === $width) {
                return asset($entry['path']);
            }
        }

        $closest = collect($entries)
            ->sortBy(fn (array $entry): int => abs($entry['width'] - $width))
            ->first();

        if (! is_array($closest)) {
            throw new RuntimeException("No brand logo variant found for width [{$width}].");
        }

        return asset($closest['path']);
    }

    private function displayHeightForWidth(int $displayWidth): int
    {
        if ($this->intrinsicWidth <= 0) {
            return $displayWidth;
        }

        return (int) round($displayWidth * $this->intrinsicHeight / $this->intrinsicWidth);
    }
}
