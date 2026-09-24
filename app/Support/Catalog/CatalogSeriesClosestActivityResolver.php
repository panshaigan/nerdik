<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Models\Activity;
use App\Models\ActivitySeries;
use App\Support\Ui\ActivityListingImageResolver;
use App\Support\Ui\ListingCardPicture;
use Illuminate\Support\Collection;

final class CatalogSeriesClosestActivityResolver
{
    public function __construct(
        private ActivityListingImageResolver $activityListingImageResolver,
    ) {}

    /**
     * @param  Collection<int, ActivitySeries>  $seriesList
     * @return array<int, ListingCardPicture>
     */
    public function coverPicturesBySeriesId(Collection $seriesList): array
    {
        $seriesIds = $seriesList->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($seriesIds === []) {
            return [];
        }

        $startsAtSql = Activity::scheduleStartsAtSql();

        /** @var Collection<int, Collection<int, Activity>> $activitiesBySeriesId */
        $activitiesBySeriesId = Activity::query()
            ->whereIn('activity_series_id', $seriesIds)
            ->attachedToPublicEvent()
            ->with(['tagMedia', 'galleryMedia', 'activityType.media', 'media' => fn ($query) => $query->where('collection_name', 'logo'), 'slot'])
            ->orderByRaw("{$startsAtSql} ASC")
            ->orderBy('activities.id')
            ->get()
            ->groupBy(fn (Activity $activity): int => (int) $activity->activity_series_id);

        $now = now();
        $covers = [];

        foreach ($seriesIds as $seriesId) {
            /** @var Collection<int, Activity> $activities */
            $activities = $activitiesBySeriesId->get($seriesId, collect());
            $closest = $activities
                ->filter(function (Activity $activity) use ($now): bool {
                    $startsAt = $activity->scheduleStartsAt();

                    return $startsAt !== null && $startsAt->gte($now);
                })
                ->sortBy([
                    fn (Activity $activity) => $activity->scheduleStartsAt()?->timestamp ?? PHP_INT_MAX,
                    ['id', 'asc'],
                ])
                ->first();

            if ($closest === null) {
                $closest = $activities
                    ->sortBy([
                        fn (Activity $activity) => -($activity->scheduleStartsAt()?->timestamp ?? 0),
                        ['id', 'desc'],
                    ])
                    ->first();
            }

            if ($closest === null) {
                continue;
            }

            $picture = $this->activityListingImageResolver->resolve($closest);
            if ($picture->hasDisplayableImage()) {
                $covers[$seriesId] = $picture;
            }
        }

        return $covers;
    }
}
