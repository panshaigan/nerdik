<?php

declare(strict_types=1);

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

final class PrefixedPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    private function getBasePath(Media $media): string
    {
        $prefix = trim((string) config('media.storage_path_prefix', 'media'), '/');

        if ($prefix === '') {
            return (string) $media->getKey();
        }

        return $prefix.'/'.$media->getKey();
    }
}
