<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\ResponsiveImages\RegisteredResponsiveImages;

final class SimpleRegisteredResponsiveImages extends RegisteredResponsiveImages
{
    public function __construct(Media $media, string $conversionName = '')
    {
        $this->media = $media;
        $this->generatedFor = $conversionName === ''
            ? 'media_library_original'
            : $conversionName;

        $this->files = collect($media->responsive_images[$this->generatedFor]['urls'] ?? [])
            ->map(fn (string $fileName) => new SimpleResponsiveImage($fileName, $media))
            ->filter(fn (SimpleResponsiveImage $responsiveImage) => $responsiveImage->generatedFor() === $this->generatedFor);
    }
}
