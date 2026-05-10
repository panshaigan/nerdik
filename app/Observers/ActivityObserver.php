<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Slot;
use App\Services\EventShowReadCache;

class ActivityObserver
{
    public function saved(Activity $activity): void
    {
        $this->forgetForActivity($activity->id);
    }

    public function deleted(Activity $activity): void
    {
        $this->forgetForActivity($activity->id);
    }

    private function forgetForActivity(int $activityId): void
    {
        $cache = app(EventShowReadCache::class);
        Slot::query()
            ->where('activity_id', $activityId)
            ->pluck('event_id')
            ->unique()
            ->each(fn ($eventId) => $cache->forgetProgrammeStats((int) $eventId));
    }
}
