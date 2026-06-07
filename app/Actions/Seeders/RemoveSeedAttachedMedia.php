<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\ActivityType;
use App\Models\Tag;
use App\Support\Ui\EventListingImageResolver;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class RemoveSeedAttachedMedia
{
    public function forTagLibrary(): void
    {
        Tag::query()
            ->each(function (Tag $tag): void {
                $this->deleteSeedMedia($tag, 'images');
            });
    }

    public function forActivityListingDefaults(): void
    {
        ActivityType::query()
            ->each(function (ActivityType $activityType): void {
                $this->deleteSeedMedia($activityType, 'images');
            });
    }

    public function forEventListingDefaults(): void
    {
        $rpgType = ActivityType::findBySlug(ActivityType::SLUG_RPG);

        if ($rpgType === null) {
            return;
        }

        $rpgType->getMedia(EventListingImageResolver::EVENT_LISTING_COLLECTION)
            ->each(fn (Media $media) => $media->delete());
    }

    private function deleteSeedMedia(HasMedia&Model $model, string $collection): void
    {
        $model->media()
            ->where('collection_name', $collection)
            ->whereNotNull('custom_properties->seed_source')
            ->get()
            ->each(fn (Media $media) => $media->delete());
    }
}
