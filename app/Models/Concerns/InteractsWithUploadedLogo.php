<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait InteractsWithUploadedLogo
{
    use InteractsWithMedia;
    use RegistersOptimizedImageConversions;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->useDisk('public');

        $this->addMediaCollection('source')
            ->singleFile()
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerOptimizedConversionsForCollections(['logo']);
    }

    public function cropSourceImageUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('source');

        return $url !== '' ? $url : null;
    }
}
