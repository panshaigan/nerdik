<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait InteractsWithOptimizedImages
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $queued = (bool) config('media.queue_conversions', true);

        $this->registerOptimizedImageConversion('avif', 'avif', $queued);
        $this->registerOptimizedImageConversion('webp', 'webp', $queued);
        $this->registerOptimizedImageConversion('jpeg', 'jpg', $queued);
    }

    protected function registerOptimizedImageConversion(string $name, string $format, bool $queued): void
    {
        $conversion = $this->addMediaConversion($name)
            ->format($format)
            ->quality((int) config("media.conversion_qualities.{$name}", 85))
            ->fit(Fit::Max, 1536, 1536)
            ->withResponsiveImages()
            ->performOnCollections('images');

        if ($queued) {
            $conversion->queued();
        } else {
            $conversion->nonQueued();
        }
    }
}
