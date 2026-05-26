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
    ) {}

    public static function fromMedia(Media $media, string $sizes = '100vw', ?string $alt = null): self
    {
        $resolvedAlt = $alt ?? (string) ($media->getCustomProperty('alt') ?? $media->name ?? '');

        return new self($media, $sizes, $resolvedAlt);
    }

    public static function fromMediaWithPreset(Media $media, string $preset, ?string $alt = null): self
    {
        $sizes = (string) config("media.sizes.{$preset}", '100vw');

        return self::fromMedia($media, $sizes, $alt);
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
        if ($this->media->hasGeneratedConversion('jpeg')) {
            return $this->media->getUrl('jpeg');
        }

        if ($this->media->hasResponsiveImages('jpeg')) {
            $urls = $this->media->getResponsiveImageUrls('jpeg');

            return $urls[0] ?? $this->media->getUrl();
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
            return $this->media->getSrcset($conversion);
        }

        $url = $this->media->getUrl($conversion);

        return $url !== '' ? "{$url} 1x" : '';
    }
}
