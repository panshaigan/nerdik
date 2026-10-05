<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Activity;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class PlatformListingCountsQuery
{
    public function upcomingEventsCount(?Carbon $now = null): int
    {
        $now = $now ?? now();

        return Event::query()
            ->whereNull('cancelled_at')
            ->where('starts_at', '>', $now)
            ->where('ends_at', '>=', $now)
            ->count();
    }

    public function ongoingEventsCount(?Carbon $now = null): int
    {
        $now = $now ?? now();

        return Event::query()
            ->whereNull('cancelled_at')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->count();
    }

    public function upcomingActivitiesCount(?Carbon $now = null): int
    {
        $now = $now ?? now();

        return $this->activitiesWithEffectiveSchedule()
            ->whereNull('activities.cancelled_at')
            ->whereRaw($this->effectiveStartsAtExpression().' > ?', [$now])
            ->whereRaw($this->effectiveEndsAtExpression().' >= ?', [$now])
            ->count();
    }

    public function ongoingActivitiesCount(?Carbon $now = null): int
    {
        $now = $now ?? now();

        return $this->activitiesWithEffectiveSchedule()
            ->whereNull('activities.cancelled_at')
            ->whereRaw($this->effectiveStartsAtExpression().' <= ?', [$now])
            ->whereRaw($this->effectiveEndsAtExpression().' >= ?', [$now])
            ->count();
    }

    public function eventsCreatedThisMonth(?Carbon $now = null): int
    {
        $now = $now ?? now();

        return Event::query()
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->count();
    }

    public function activitiesCreatedThisMonth(?Carbon $now = null): int
    {
        $now = $now ?? now();

        return Activity::query()
            ->where('created_at', '>=', $now->copy()->startOfMonth())
            ->count();
    }

    /**
     * @return Builder<Activity>
     */
    private function activitiesWithEffectiveSchedule(): Builder
    {
        return Activity::query();
    }

    private function effectiveStartsAtExpression(): string
    {
        return 'COALESCE(
            (SELECT slots.starts_at FROM slots WHERE slots.activity_id = activities.id ORDER BY slots.id ASC LIMIT 1),
            activities.starts_at
        )';
    }

    private function effectiveEndsAtExpression(): string
    {
        return 'COALESCE(
            (SELECT COALESCE(slots.ends_at, slots.starts_at) FROM slots WHERE slots.activity_id = activities.id ORDER BY slots.id ASC LIMIT 1),
            COALESCE(activities.ends_at, activities.starts_at)
        )';
    }
}
