<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait InteractsWithActivityTypeImages
{
    use InteractsWithMedia;
    use RegistersOptimizedImageConversions;

    public function registerMediaCollections(): void
    {
        $this->registerOptimizedImageCollection('images');
        $this->registerOptimizedImageCollection('event_listing');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Normalized WebP original + collection responsives; no full-size conversion.
    }
}
