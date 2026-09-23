<?php

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
});

/**
 * Live participation updates (roster/capacity counters) for activity and event plan UIs.
 *
 * Open to authenticated users who may view the activity, so visitors see
 * current state before joining. Payload is activityId only; roster details load via HTTP.
 */
Broadcast::channel('activity.{activityId}', function (User $user, int|string $activityId): bool {
    return Activity::query()->visibleTo($user)->whereKey((int) $activityId)->exists();
});
