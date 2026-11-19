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
        $this->registerOptimizedImageCollection('logo', singleFile: true);

        $this->addMediaCollection('source')
            ->singleFile()
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Normalized WebP original + collection responsives; no full-size conversion.
    }

    public function cropSourceImageUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('source');

        return $url !== '' ? $url : null;
    }
}
