<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

class LifecycleMutationRateLimiter
{
    public function isRateLimitedForActivity(User $user, Activity $activity): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->activityKey($user, $activity),
            $this->maxPerMinute(),
        );
    }

    public function isRateLimitedForEvent(User $user, Event $event): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->eventKey($user, $event),
            $this->maxPerMinute(),
        );
    }

    public function recordActivityMutation(User $user, Activity $activity): void
    {
        RateLimiter::hit($this->activityKey($user, $activity), 60);
    }

    public function recordEventMutation(User $user, Event $event): void
    {
        RateLimiter::hit($this->eventKey($user, $event), 60);
    }

    private function maxPerMinute(): int
    {
        return (int) config('notification_throttle.lifecycle_mutations_per_minute', 1);
    }

    private function activityKey(User $user, Activity $activity): string
    {
        return "lifecycle:activity:{$user->id}:{$activity->id}";
    }

    private function eventKey(User $user, Event $event): string
    {
        return "lifecycle:event:{$user->id}:{$event->id}";
    }
}
