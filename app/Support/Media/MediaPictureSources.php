<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaPictureSources
{
    public function __construct(
        private readonly Media $media,
        private readonly string $sizes,
        private readonly string $alt,
        private readonly ?int $maxSrcsetWidth = null,
        private readonly ?int $displayWidth = null,
    ) {}

    public static function fromMedia(
        Media $media,
        string $sizes = '100vw',
        ?string $alt = null,
        ?int $maxSrcsetWidth = null,
        ?int $displayWidth = null,
    ): self {
        $resolvedAlt = $alt ?? (string) ($media->getCustomProperty('alt') ?? $media->name ?? '');

        return new self($media, $sizes, $resolvedAlt, $maxSrcsetWidth, $displayWidth);
    }

    public static function fromMediaWithPreset(Media $media, string $preset, ?string $alt = null): self
    {
        $presetConfig = config("media.presets.{$preset}");

        if (is_array($presetConfig)) {
            $sizes = (string) ($presetConfig['sizes'] ?? '100vw');
            $maxWidth = isset($presetConfig['max_srcset_width'])
                ? (int) $presetConfig['max_srcset_width']
                : null;
            $displayWidth = isset($presetConfig['display_width'])
                ? (int) $presetConfig['display_width']
                : null;
        } else {
            $sizes = (string) config("media.sizes.{$preset}", '100vw');
            $maxWidth = null;
            $displayWidth = null;
        }

        return self::fromMedia($media, $sizes, $alt, $maxWidth, $displayWidth);
    }

    public function avifSrcset(): string
    {
        return $this->srcsetForConversion('avif');
    }

    public function webpSrcset(): string
    {
        return $this->srcsetForConversion('webp');
    }

    public function jpegSrcset(): string
    {
        return $this->srcsetForConversion('jpeg');
    }

    public function jpegSrc(): string
    {
        if ($this->media->hasGeneratedConversion('jpeg') && $this->media->hasResponsiveImages('jpeg')) {
            $cappedSrcset = $this->srcsetForConversion('jpeg');
            $url = $this->largestWidthUrlFromSrcset($cappedSrcset);

            if ($url !== null) {
                return $url;
            }
        }

        if ($this->media->hasGeneratedConversion('jpeg')) {
            return $this->media->getUrl('jpeg');
        }

        if ($this->media->hasResponsiveImages('jpeg')) {
            $urls = $this->media->getResponsiveImageUrls('jpeg');

            return $urls[0] ?? $this->media->getUrl();
        }

        return $this->media->getUrl();
    }

    public function displaySrc(): string
    {
        foreach (['jpeg', 'webp'] as $conversion) {
            if (! $this->media->hasGeneratedConversion($conversion) || ! $this->media->hasResponsiveImages($conversion)) {
                continue;
            }

            $cappedSrcset = $this->srcsetForConversion($conversion);
            $url = $this->smallestFittingWidthUrlFromSrcset($cappedSrcset, $this->targetDisplayPixelWidth());

            if ($url !== null) {
                return $url;
            }
        }

        if ($this->media->hasGeneratedConversion('jpeg')) {
            return $this->media->getUrl('jpeg');
        }

        foreach (['jpeg', 'webp'] as $conversion) {
            if (! $this->media->hasResponsiveImages($conversion)) {
                continue;
            }

            $urls = $this->media->getResponsiveImageUrls($conversion);

            if ($urls !== []) {
                return $urls[0];
            }
        }

        return $this->media->getUrl();
    }

    public function sizes(): string
    {
        return $this->sizes;
    }

    public function alt(): string
    {
        return $this->alt;
    }

    public function width(): ?int
    {
        $width = $this->media->getCustomProperty('width');

        return is_numeric($width) ? (int) $width : null;
    }

    public function height(): ?int
    {
        $height = $this->media->getCustomProperty('height');

        return is_numeric($height) ? (int) $height : null;
    }

    private function srcsetForConversion(string $conversion): string
    {
        if (! $this->media->hasGeneratedConversion($conversion)) {
            return '';
        }

        if ($this->media->hasResponsiveImages($conversion)) {
            return $this->filterSrcsetByMaxWidth(
                $this->sanitizeSrcset($this->media->getSrcset($conversion)),
            );
        }

        $url = $this->media->getUrl($conversion);

        return $url !== '' ? "{$url} 1x" : '';
    }

    private function sanitizeSrcset(string $srcset): string
    {
        $entries = $this->parseSrcsetEntries($srcset);

        if ($entries === []) {
            return '';
        }

        return implode(', ', array_column($entries, 'entry'));
    }

    private function filterSrcsetByMaxWidth(string $srcset): string
    {
        $entries = $this->parseSrcsetEntries($srcset);

        if ($entries === []) {
            return '';
        }

        if ($this->maxSrcsetWidth === null) {
            return implode(', ', array_column($entries, 'entry'));
        }

        $filtered = array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['width'] === null || $entry['width'] <= $this->maxSrcsetWidth,
        ));

        if ($filtered !== []) {
            return implode(', ', array_column($filtered, 'entry'));
        }

        $aboveCap = array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['width'] !== null && $entry['width'] > $this->maxSrcsetWidth,
        ));

        if ($aboveCap === []) {
            return $entries[0]['entry'];
        }

        usort($aboveCap, fn (array $a, array $b): int => ($a['width'] ?? 0) <=> ($b['width'] ?? 0));

        return $aboveCap[0]['entry'];
    }

    /**
     * @return list<array{entry: string, url: string, width: ?int, descriptor: string}>
     */
    private function parseSrcsetEntries(string $srcset): array
    {
        $srcset = trim($this->stripTinyPlaceholderFromSrcset($srcset));

        if ($srcset === '') {
            return [];
        }

        preg_match_all(
            '/((?:https?:\/\/|\/)?\S+)\s+(\d+w|\d+(?:\.\d+)?x)/',
            $srcset,
            $matches,
            PREG_SET_ORDER,
        );

        $entries = [];

        foreach ($matches as $match) {
            $descriptor = $match[2];
            $width = str_ends_with($descriptor, 'w')
                ? (int) rtrim($descriptor, 'w')
                : null;

            if (str_starts_with($match[1], 'data:')) {
                continue;
            }

            $entries[] = [
                'entry' => trim($match[0]),
                'url' => $match[1],
                'width' => $width,
                'descriptor' => $descriptor,
            ];
        }

        return $entries;
    }

    private function stripTinyPlaceholderFromSrcset(string $srcset): string
    {
        $stripped = preg_replace(
            '/,\s*data:image\/svg\+xml;base64,\S+\s+\d+w/',
            '',
            $srcset,
        );

        return is_string($stripped) ? $stripped : $srcset;
    }

    private function largestWidthUrlFromSrcset(string $srcset): ?string
    {
        $entries = $this->parseSrcsetEntries($srcset);

        if ($entries === []) {
            return null;
        }

        $bestUrl = null;
        $bestWidth = -1;

        foreach ($entries as $entry) {
            if ($entry['width'] === null) {
                if ($bestUrl === null) {
                    $bestUrl = $entry['url'];
                }

                continue;
            }

            if ($entry['width'] >= $bestWidth) {
                $bestWidth = $entry['width'];
                $bestUrl = $entry['url'];
            }
        }

        return $bestUrl;
    }

    private function targetDisplayPixelWidth(): int
    {
        $displayWidth = $this->displayWidth ?? $this->largestPixelWidthFromSizes($this->sizes);

        if ($displayWidth === null) {
            $displayWidth = $this->maxSrcsetWidth !== null
                ? (int) ceil($this->maxSrcsetWidth / 2)
                : 640;
        }

        $target = $displayWidth * 2;

        if ($this->maxSrcsetWidth !== null) {
            $target = min($target, $this->maxSrcsetWidth);
        }

        return max(1, $target);
    }

    private function largestPixelWidthFromSizes(string $sizes): ?int
    {
        preg_match_all('/(\d+)px/', $sizes, $matches);

        if ($matches[1] === []) {
            return null;
        }

        return max(array_map(intval(...), $matches[1]));
    }

    private function smallestFittingWidthUrlFromSrcset(string $srcset, int $targetWidth): ?string
    {
        $entries = $this->parseSrcsetEntries($srcset);

        if ($entries === []) {
            return null;
        }

        $widthEntries = array_values(array_filter(
            $entries,
            fn (array $entry): bool => $entry['width'] !== null,
        ));

        if ($widthEntries === []) {
            return $entries[0]['url'];
        }

        usort($widthEntries, fn (array $a, array $b): int => ($a['width'] ?? 0) <=> ($b['width'] ?? 0));

        foreach ($widthEntries as $entry) {
            if (($entry['width'] ?? 0) >= $targetWidth) {
                return $entry['url'];
            }
        }

        return $widthEntries[array_key_last($widthEntries)]['url'];
    }
}
