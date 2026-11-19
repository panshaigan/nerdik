<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Media\SimpleRegisteredResponsiveImages;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;
use Spatie\MediaLibrary\ResponsiveImages\RegisteredResponsiveImages;

class Media extends BaseMedia
{
    public function responsiveImages(string $conversionName = ''): RegisteredResponsiveImages
    {
        return new SimpleRegisteredResponsiveImages($this, $conversionName);
    }
}
