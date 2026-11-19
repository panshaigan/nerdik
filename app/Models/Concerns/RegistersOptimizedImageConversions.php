<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait RegistersOptimizedImageConversions
{
    /**
     * Display collections store a single normalized WebP original (≤ max edge)
     * plus Spatie responsives generated from that original — no full-size twin
     * conversion.
     */
    protected function registerOptimizedImageCollection(
        string $name,
        bool $singleFile = false,
    ): MediaCollection {
        $collection = $this->addMediaCollection($name)->useDisk('public');

        if ($singleFile) {
            $collection->singleFile();
        }

        if ($this->shouldGenerateResponsiveImages()) {
            $collection->withResponsiveImages();
        }

        return $collection;
    }

    /**
     * @param  list<string>  $collections
     *
     * @deprecated Kept for call-site compatibility; optimized collections no longer register format conversions.
     */
    protected function registerOptimizedConversionsForCollections(
        array $collections,
        int $maxWidth = 1536,
        int $maxHeight = 1536,
    ): void {
        unset($collections, $maxWidth, $maxHeight);
    }

    protected function shouldGenerateResponsiveImages(): bool
    {
        if (! app()->environment('testing')) {
            return true;
        }

        return (bool) config('media.testing.generate_responsive_images', true);
    }

    protected function shouldRunConversionsSynchronously(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        return in_array('tags:seed-images', $_SERVER['argv'] ?? [], true);
    }

    abstract public function registerMediaConversions(?Media $media = null): void;
}
