<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('activity.{activityId}', function ($user, int|string $activityId): bool {
    return Activity::query()->whereKey((int) $activityId)->exists();
});
