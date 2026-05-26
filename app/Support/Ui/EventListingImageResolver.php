<?php

declare(strict_types=1);

namespace App\Support\Ui;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class EventListingImageResolver
{
    public const LISTING_ROLE = 'event_listing_default';

    public function resolve(): ListingCardPicture
    {
        $media = Media::query()
            ->where('collection_name', 'images')
            ->where('custom_properties->listing_role', self::LISTING_ROLE)
            ->first();

        if ($media !== null) {
            return ListingCardPicture::fromMedia($media);
        }

        return ListingCardPicture::globalFallback();
    }
}
