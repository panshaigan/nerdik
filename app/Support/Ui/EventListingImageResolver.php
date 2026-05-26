<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Enums\EventLogoSource;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class EventListingImageResolver
{
    public const LISTING_ROLE = 'event_listing_default';

    public function resolve(?Event $event = null): ListingCardPicture
    {
        if ($event !== null) {
            $userChosen = $this->resolveUserChosen($event);
            if ($userChosen !== null) {
                return $userChosen;
            }
        }

        return $this->resolveGlobalDefault();
    }

    private function resolveUserChosen(Event $event): ?ListingCardPicture
    {
        $source = $event->logo_source;

        if ($source === EventLogoSource::Default && $event->listing_media_id !== null) {
            $media = $this->resolveListingMediaRelation($event);

            if ($media !== null) {
                return ListingCardPicture::fromMedia($media, (string) $event->name);
            }
        }

        if ($source === EventLogoSource::Upload && filled($event->logo_path)) {
            $path = (string) $event->logo_path;
            if (Storage::disk('public')->exists($path)) {
                return new ListingCardPicture(staticUrl: Storage::disk('public')->url($path));
            }
        }

        return null;
    }

    private function resolveListingMediaRelation(Event $event): ?Media
    {
        if ($event->relationLoaded('listingMedia')) {
            return $event->listingMedia;
        }

        return $event->listingMedia()->first();
    }

    private function resolveGlobalDefault(): ListingCardPicture
    {
        $media = Media::query()
            ->where('collection_name', 'images')
            ->where('custom_properties->listing_role', self::LISTING_ROLE)
            ->orderBy('id')
            ->first();

        if ($media !== null) {
            return ListingCardPicture::fromMedia($media);
        }

        return ListingCardPicture::globalFallback();
    }
}
