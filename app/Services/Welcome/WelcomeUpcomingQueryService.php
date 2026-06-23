<?php

declare(strict_types=1);

namespace App\Services\Welcome;

use App\Models\Activity;
use App\Models\Event;
use App\Support\Ui\BrowseListingCardPresenter;
use App\Support\Ui\BrowseListingCardViewData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final readonly class WelcomeUpcomingQueryService
{
    private const CACHE_KEY_PREFIX = 'welcome.upcoming_listing_ids';

    private const CACHE_TTL_SECONDS = 120;

    public function __construct(
        private BrowseListingCardPresenter $listingCardPresenter,
    ) {}

    /**
     * @return Collection<int, BrowseListingCardViewData>
     */
    public function nearestPublicListings(int $limit = 6): Collection
    {
        $rows = $this->cachedListingRows($limit);

        $eventIds = collect($rows)
            ->where('listing_kind', 'event')
            ->pluck('listing_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $activityIds = collect($rows)
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

        return collect($rows)
            ->map(function (array $row) use ($events, $activities): ?BrowseListingCardViewData {
                $listingId = (int) $row['listing_id'];
                if ($row['listing_kind'] === 'event') {
                    $event = $events->get($listingId);

                    return $event ? $this->listingCardPresenter->fromEvent($event, []) : null;
                }

                $activity = $activities->get($listingId);

                return $activity ? $this->listingCardPresenter->fromActivity($activity, []) : null;
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<array{listing_kind: string, listing_id: int}>
     */
    private function cachedListingRows(int $limit): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX.'.'.$limit;

        /** @var list<array{listing_kind: string, listing_id: int}> $rows */
        $rows = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->queryListingRows($limit),
        );

        return $rows;
    }

    /**
     * @return list<array{listing_kind: string, listing_id: int}>
     */
    private function queryListingRows(int $limit): array
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

        return DB::query()
            ->fromSub($eventQuery->toBase()->unionAll($activityQuery->toBase()), 'merged')
            ->orderBy('sort_at')
            ->orderBy('listing_kind')
            ->orderBy('listing_id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'listing_kind' => (string) $row->listing_kind,
                'listing_id' => (int) $row->listing_id,
            ])
            ->values()
            ->all();
    }
}
