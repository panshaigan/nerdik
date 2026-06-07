<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Enums\ActivityLogoSource;
use App\Models\Activity;
use App\Models\Tag;
use App\Models\TagCategory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ActivityListingImageResolver
{
    public function resolve(Activity $activity, string $preset = 'listing_card'): ListingCardPicture
    {
        $userChosen = $this->resolveUserChosen($activity, $preset);
        if ($userChosen !== null) {
            return $userChosen;
        }

        foreach ([TagCategory::KEY_GAME, TagCategory::KEY_SETTING, TagCategory::KEY_GENRE] as $categoryKey) {
            $tagMedia = $this->firstMediaFromTagsByCategory($activity, $categoryKey);
            if ($tagMedia !== null) {
                return ListingCardPicture::fromMedia($tagMedia, (string) $activity->name, $preset);
            }
        }

        $activityTypeMedia = $this->firstActivityTypeMedia($activity);
        if ($activityTypeMedia !== null) {
            return ListingCardPicture::fromMedia($activityTypeMedia, (string) $activity->name, $preset);
        }

        return ListingCardPicture::empty();
    }

    private function resolveUserChosen(Activity $activity, string $preset): ?ListingCardPicture
    {
        $source = $activity->logo_source;

        if ($source === ActivityLogoSource::Tag && $activity->tag_media_id !== null) {
            $media = $this->resolveTagMediaRelation($activity);

            if ($media !== null) {
                return ListingCardPicture::fromMedia($media, (string) $activity->name, $preset);
            }
        }

        if ($source === ActivityLogoSource::Upload) {
            $media = $this->resolveUploadedLogoMedia($activity);

            if ($media !== null) {
                return ListingCardPicture::fromMedia($media, (string) $activity->name, $preset);
            }
        }

        return null;
    }

    private function resolveUploadedLogoMedia(Activity $activity): ?Media
    {
        if ($activity->relationLoaded('media')) {
            $logoMedia = $activity->getMedia('logo')->first();

            if ($logoMedia !== null) {
                return $logoMedia;
            }
        }

        return $activity->getFirstMedia('logo');
    }

    private function resolveTagMediaRelation(Activity $activity): ?Media
    {
        if ($activity->relationLoaded('tagMedia')) {
            return $activity->tagMedia;
        }

        return $activity->tagMedia()->first();
    }

    private function firstMediaFromTagsByCategory(Activity $activity, string $categoryKey): ?Media
    {
        foreach ($this->tagsOrderedByPivot($activity) as $tag) {
            if ($tag->category !== $categoryKey) {
                continue;
            }

            $media = $this->firstTagImageMedia($tag);
            if ($media !== null) {
                return $media;
            }
        }

        return null;
    }

    private function tagsOrderedByPivot(Activity $activity)
    {
        if (! $activity->relationLoaded('tags')) {
            $activity->load([
                'tags' => fn ($query) => $query->with(['tagCategory', 'media'])->orderBy('taggables.id'),
            ]);
        }

        return $activity->tags;
    }

    private function firstTagImageMedia(Tag $tag): ?Media
    {
        return $tag->getMedia('images')->first();
    }

    private function firstActivityTypeMedia(Activity $activity): ?Media
    {
        $activityType = $activity->relationLoaded('activityType')
            ? $activity->activityType
            : $activity->activityType()->with('media')->first();

        if ($activityType === null) {
            return null;
        }

        return $activityType->getMedia('images')->first();
    }
}
