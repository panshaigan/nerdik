<?php

declare(strict_types=1);

namespace App\Services\Welcome;

use App\Models\Activity;
use App\Models\Event;
use App\Support\Ui\BrowseListingCardPresenter;
use App\Support\Ui\BrowseListingCardViewData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class WelcomeUpcomingQueryService
{
    public function __construct(
        private BrowseListingCardPresenter $listingCardPresenter,
    ) {}

    /**
     * @return Collection<int, BrowseListingCardViewData>
     */
    public function nearestPublicListings(int $limit = 6): Collection
    {
        $eventQuery = Event::query()
            ->where('events.is_public', true)
            ->whereNull('events.cancelled_at')
            ->whereRaw('COALESCE(events.ends_at, events.starts_at) >= ?', [now()])
            ->selectRaw("'event' as listing_kind")
            ->selectRaw('events.id as listing_id')
            ->selectRaw('COALESCE(events.ends_at, events.starts_at) as sort_at');

        $activityQuery = Activity::query()
            ->attachedToPublicEvent(true)
            ->selectRaw("'activity' as listing_kind")
            ->selectRaw('activities.id as listing_id')
            ->selectRaw('COALESCE((SELECT COALESCE(slots.ends_at, slots.starts_at) FROM slots WHERE slots.activity_id = activities.id AND slots.event_id IS NOT NULL ORDER BY slots.id ASC LIMIT 1), COALESCE(activities.ends_at, activities.starts_at)) as sort_at');

        $rows = DB::query()
            ->fromSub($eventQuery->toBase()->unionAll($activityQuery->toBase()), 'merged')
            ->orderBy('sort_at')
            ->orderBy('listing_kind')
            ->orderBy('listing_id')
            ->limit($limit)
            ->get();

        $eventIds = $rows
            ->where('listing_kind', 'event')
            ->pluck('listing_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $activityIds = $rows
            ->where('listing_kind', 'activity')
            ->pluck('listing_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $events = $eventIds === []
            ? collect()
            : Event::query()
                ->with([
                    'organization',
                    'creator',
                    'listingMedia',
                    'places.country.translations',
                    'places.city.translations',
                    'slots.activity.activityType',
                    'slots.activityTypes',
                ])
                ->whereIn('id', $eventIds)
                ->get()
                ->keyBy('id');

        $activities = $activityIds === []
            ? collect()
            : Activity::query()
                ->with(Activity::listingCardEagerLoad())
                ->withCount(['participants as participants_count' => fn ($query) => $query->where('is_absent', false)])
                ->whereIn('id', $activityIds)
                ->get()
                ->keyBy('id');

        return $rows
            ->map(function (object $row) use ($events, $activities): ?BrowseListingCardViewData {
                $listingId = (int) $row->listing_id;
                if ($row->listing_kind === 'event') {
                    $event = $events->get($listingId);

                    return $event ? $this->listingCardPresenter->fromEvent($event, []) : null;
                }

                $activity = $activities->get($listingId);

                return $activity ? $this->listingCardPresenter->fromActivity($activity, []) : null;
            })
            ->filter()
            ->values();
    }
}
