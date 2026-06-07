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
        $this->addMediaCollection('images')
            ->useDisk('public');

        $this->addMediaCollection('event_listing')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerOptimizedConversionsForCollections(['images', 'event_listing']);
    }
}
