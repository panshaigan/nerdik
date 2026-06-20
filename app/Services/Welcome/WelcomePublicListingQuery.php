<?php

declare(strict_types=1);

namespace App\Services\Welcome;

use App\Models\Activity;
use App\Models\Event;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class WelcomePublicListingQuery
{
    public function mergedListingsQuery(): Builder
    {
        $eventQuery = Event::query()
            ->where('events.is_public', true)
            ->whereNull('events.cancelled_at')
            ->selectRaw("'event' as listing_kind")
            ->selectRaw('events.id as listing_id')
            ->selectRaw('events.starts_at as starts_at')
            ->selectRaw('COALESCE(events.ends_at, events.starts_at) as ends_at');

        $activityQuery = Activity::query()
            ->attachedToPublicEvent(false)
            ->selectRaw("'activity' as listing_kind")
            ->selectRaw('activities.id as listing_id')
            ->selectRaw('COALESCE((SELECT slots.starts_at FROM slots WHERE slots.activity_id = activities.id AND slots.event_id IS NOT NULL ORDER BY slots.id ASC LIMIT 1), activities.starts_at) as starts_at')
            ->selectRaw('COALESCE((SELECT COALESCE(slots.ends_at, slots.starts_at) FROM slots WHERE slots.activity_id = activities.id AND slots.event_id IS NOT NULL ORDER BY slots.id ASC LIMIT 1), COALESCE(activities.ends_at, activities.starts_at)) as ends_at');

        return DB::query()
            ->fromSub($eventQuery->toBase()->unionAll($activityQuery->toBase()), 'merged');
    }

    public function upcomingCount(): int
    {
        return (int) $this->mergedListingsQuery()
            ->where('starts_at', '>', now())
            ->where('ends_at', '>=', now())
            ->count();
    }

    public function ongoingCount(): int
    {
        return (int) $this->mergedListingsQuery()
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->count();
    }
}
