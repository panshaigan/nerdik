<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Enums\EventLogoSource;
use App\Models\Event;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class EventListingImageResolver
{
    public const LISTING_ROLE = 'event_listing_default';

    public function resolve(?Event $event = null, string $preset = 'listing_card'): ListingCardPicture
    {
        if ($event !== null) {
            $userChosen = $this->resolveUserChosen($event, $preset);
            if ($userChosen !== null) {
                return $userChosen;
            }
        }

        return $this->resolveGlobalDefault($preset);
    }

    private function resolveUserChosen(Event $event, string $preset): ?ListingCardPicture
    {
        $source = $event->logo_source;

        if ($source === EventLogoSource::Default && $event->listing_media_id !== null) {
            $media = $this->resolveListingMediaRelation($event);

            if ($media !== null) {
                return ListingCardPicture::fromMedia($media, (string) $event->name, $preset);
            }
        }

        if ($source === EventLogoSource::Upload) {
            $media = $this->resolveUploadedLogoMedia($event);

            if ($media !== null) {
                return ListingCardPicture::fromMedia($media, (string) $event->name, $preset);
            }
        }

        return null;
    }

    private function resolveUploadedLogoMedia(Event $event): ?Media
    {
        if ($event->relationLoaded('media')) {
            $logoMedia = $event->getMedia('logo')->first();

            if ($logoMedia !== null) {
                return $logoMedia;
            }
        }

        return $event->getFirstMedia('logo');
    }

    private function resolveListingMediaRelation(Event $event): ?Media
    {
        if ($event->relationLoaded('listingMedia')) {
            return $event->listingMedia;
        }

        return $event->listingMedia()->first();
    }

    private function resolveGlobalDefault(string $preset): ListingCardPicture
    {
        $media = Media::query()
            ->where('collection_name', 'images')
            ->where('custom_properties->listing_role', self::LISTING_ROLE)
            ->orderBy('id')
            ->first();

        if ($media !== null) {
            return ListingCardPicture::fromMedia($media, preset: $preset);
        }

        return ListingCardPicture::empty();
    }
}
