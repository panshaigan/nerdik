<?php

namespace App\Services;

use App\Events\ActivityParticipationUpdated;

/**
 * Dispatches realtime roster-change signals for activity show subscribers.
 */
final class ActivityParticipationBroadcaster
{
    public static function rosterChanged(int $activityId): void
    {
        broadcast(new ActivityParticipationUpdated($activityId))->toOthers();
    }
}
