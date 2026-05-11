<?php

declare(strict_types=1);

namespace App\Support\Browse;

use App\Models\Activity;
use App\Models\Event;
use App\Support\BrowseTagFilter;
use Illuminate\Database\Eloquent\Builder;

final class BrowseListingQuery
{
    /**
     * @return Builder<Event>
     */
    public static function baseEventQuery(BrowseListingFilterBag $filters): Builder
    {
        $query = Event::query()
            ->where('is_public', true)
            ->whereNull('events.cancelled_at');

        if (! $filters->includePastEvents) {
            $query->whereRaw('COALESCE(events.ends_at, events.starts_at) >= ?', [now()]);
        }

        if ($filters->q !== '') {
            $term = '%'.mb_strtolower($filters->q).'%';
            $query->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(events.name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(events.description) LIKE ?', [$term]));
        }

        BrowseTagFilter::apply($query, $filters->tagIds, $filters->tagsMatchAll, 'slots.activity.tags');

        if ($filters->hasBBox()) {
            [$minLat, $maxLat, $minLng, $maxLng] = $filters->normalizedBBox();
            $query->whereHas('places', function (Builder $q) use ($minLat, $maxLat, $minLng, $maxLng): void {
                $q->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->whereBetween('latitude', [$minLat, $maxLat])
                    ->whereBetween('longitude', [$minLng, $maxLng]);
            });
        }

        return $query;
    }

    /**
     * @return Builder<Activity>
     */
    public static function baseActivityQuery(BrowseListingFilterBag $filters): Builder
    {
        $query = Activity::query()->attachedToPublicEvent(! $filters->includePastEvents);

        if ($filters->q !== '') {
            $term = '%'.mb_strtolower($filters->q).'%';
            $query->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(activities.name) LIKE ?', [$term])
                ->orWhereRaw('LOWER(activities.description) LIKE ?', [$term]));
        }

        BrowseTagFilter::apply($query, $filters->tagIds, $filters->tagsMatchAll, 'tags');

        if ($filters->hasBBox()) {
            [$minLat, $maxLat, $minLng, $maxLng] = $filters->normalizedBBox();
            $query->where(function (Builder $outer) use ($minLat, $maxLat, $minLng, $maxLng): void {
                $outer->where(function (Builder $selfHosted) use ($minLat, $maxLat, $minLng, $maxLng): void {
                    $selfHosted->where('activities.hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
                        ->whereHas('place', function (Builder $q) use ($minLat, $maxLat, $minLng, $maxLng): void {
                            $q->whereNotNull('latitude')
                                ->whereNotNull('longitude')
                                ->whereBetween('latitude', [$minLat, $maxLat])
                                ->whereBetween('longitude', [$minLng, $maxLng]);
                        });
                })->orWhere(function (Builder $scheduled) use ($minLat, $maxLat, $minLng, $maxLng): void {
                    $scheduled->where('activities.hosting_mode', Activity::HOSTING_MODE_SCHEDULED_ON_EVENT)
                        ->whereHas('slot.place', function (Builder $q) use ($minLat, $maxLat, $minLng, $maxLng): void {
                            $q->whereNotNull('latitude')
                                ->whereNotNull('longitude')
                                ->whereBetween('latitude', [$minLat, $maxLat])
                                ->whereBetween('longitude', [$minLng, $maxLng]);
                        });
                });
            });
        }

        return $query;
    }
}
