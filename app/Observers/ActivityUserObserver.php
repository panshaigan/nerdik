<?php

namespace App\Observers;

use App\Models\ActivityUser;
use App\Models\Slot;
use App\Services\EventShowReadCache;

class ActivityUserObserver
{
    public function saved(ActivityUser $activityUser): void
    {
        $this->forgetForActivity((int) $activityUser->activity_id);
    }

    public function deleted(ActivityUser $activityUser): void
    {
        $this->forgetForActivity((int) $activityUser->activity_id);
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
