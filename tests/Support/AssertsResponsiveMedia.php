<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait AssertsResponsiveMedia
{
    protected function assertMediaHasResponsiveConversions(Media $media): void
    {
        Assert::assertTrue($media->hasGeneratedConversion('avif'), 'Expected avif conversion for media '.$media->id);
        Assert::assertTrue($media->hasGeneratedConversion('webp'), 'Expected webp conversion for media '.$media->id);
        Assert::assertTrue($media->hasGeneratedConversion('jpeg'), 'Expected jpeg conversion for media '.$media->id);
        Assert::assertNotEmpty($media->responsive_images, 'Expected responsive images for media '.$media->id);
    }
}
