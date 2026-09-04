<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Media\ImageFormatCapabilities;
use PHPUnit\Framework\Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait AssertsResponsiveMedia
{
    protected function assertMediaHasResponsiveConversions(Media $media): void
    {
        if (ImageFormatCapabilities::supportsAvif()) {
            Assert::assertTrue($media->hasGeneratedConversion('avif'), 'Expected avif conversion for media '.$media->id);
        }

        Assert::assertTrue($media->hasGeneratedConversion('webp'), 'Expected webp conversion for media '.$media->id);
        Assert::assertNotEmpty($media->responsive_images, 'Expected responsive images for media '.$media->id);
    }

    protected function assertAvatarMediaIsReady(Media $media): void
    {
        foreach (['avatar_32', 'avatar_118', 'avatar_512'] as $conversionName) {
            Assert::assertTrue(
                $media->hasGeneratedConversion($conversionName),
                'Expected '.$conversionName.' conversion for avatar media '.$media->id,
            );
        }
    }
}
