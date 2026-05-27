<?php

declare(strict_types=1);

namespace App\Support\Browse;

use App\Models\Activity;
use App\Models\Event;
use App\Support\BrowseTagFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class BrowseListingQuery
{
    /**
     * @return Builder<Event>
     */
    public static function baseEventQuery(BrowseListingFilterBag $filters, ?int $userId = null): Builder
    {
        $query = Event::query()->whereNull('events.cancelled_at');

        if ($filters->onlyMine && $userId !== null) {
            $query->where(function (Builder $outer) use ($userId): void {
                $outer->where('events.created_by', $userId)
                    ->orWhere(function (Builder $publicParticipation) use ($userId): void {
                        $publicParticipation
                            ->where('events.is_public', true)
                            ->whereExists(self::eventParticipationSubquery($userId));
                    });
            });
        } else {
            $query->where('events.is_public', true);
        }

        if (! $filters->includePastEvents) {
            $query->whereRaw('COALESCE(events.ends_at, events.starts_at) >= ?', [now()]);
        }

        BrowseFullTextSearch::applyEventHybrid($query, $filters->q);

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
    public static function baseActivityQuery(BrowseListingFilterBag $filters, ?int $userId = null): Builder
    {
        if ($filters->onlyMine && $userId !== null) {
            $query = Activity::query()
                ->whereNull('activities.cancelled_at')
                ->where(function (Builder $outer) use ($filters, $userId): void {
                    $outer->where('activities.created_by', $userId)
                        ->orWhere(function (Builder $browseable) use ($filters, $userId): void {
                            $browseable
                                ->attachedToPublicEvent(! $filters->includePastEvents)
                                ->whereHas('participants', self::activeParticipantConstraint($userId));
                        });
                });
        } else {
            $query = Activity::query()->attachedToPublicEvent(! $filters->includePastEvents);
        }

        BrowseFullTextSearch::applyActivityHybrid($query, $filters->q);

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

    /**
     * @return \Closure(QueryBuilder): void
     */
    private static function eventParticipationSubquery(int $userId): \Closure
    {
        return function (QueryBuilder $query) use ($userId): void {
            $query->select(DB::raw(1))
                ->from('activity_user')
                ->join('slots', 'slots.activity_id', '=', 'activity_user.activity_id')
                ->whereColumn('slots.event_id', 'events.id')
                ->whereNotNull('slots.event_id')
                ->where('activity_user.user_id', $userId)
                ->where('activity_user.is_absent', false);
        };
    }

    /**
     * @return \Closure(Builder): void
     */
    private static function activeParticipantConstraint(int $userId): \Closure
    {
        return function (Builder $query) use ($userId): void {
            $query->where('user_id', $userId)->where('is_absent', false);
        };
    }
}
