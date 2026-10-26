<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Services\LifecycleMutationRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class LifecycleMutationRateLimiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorded_activity_mutation_is_rate_limited_for_same_user_and_activity(): void
    {
        Config::set('notification_throttle.lifecycle_mutations_per_minute', 1);

        $user = User::factory()->create();
        $activity = Activity::factory()->create();
        $limiter = app(LifecycleMutationRateLimiter::class);

        $this->assertFalse($limiter->isRateLimitedForActivity($user, $activity));

        $limiter->recordActivityMutation($user, $activity);

        $this->assertTrue($limiter->isRateLimitedForActivity($user, $activity));
    }

    public function test_recorded_event_mutation_is_rate_limited_for_same_user_and_event(): void
    {
        Config::set('notification_throttle.lifecycle_mutations_per_minute', 1);

        $user = User::factory()->create();
        $event = Event::factory()->create();
        $limiter = app(LifecycleMutationRateLimiter::class);

        $this->assertFalse($limiter->isRateLimitedForEvent($user, $event));

        $limiter->recordEventMutation($user, $event);

        $this->assertTrue($limiter->isRateLimitedForEvent($user, $event));
    }

    public function test_activity_rate_limit_does_not_apply_to_different_user_or_activity(): void
    {
        Config::set('notification_throttle.lifecycle_mutations_per_minute', 1);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $activityA = Activity::factory()->create();
        $activityB = Activity::factory()->create();
        $limiter = app(LifecycleMutationRateLimiter::class);

        $limiter->recordActivityMutation($userA, $activityA);

        $this->assertFalse($limiter->isRateLimitedForActivity($userB, $activityA));
        $this->assertFalse($limiter->isRateLimitedForActivity($userA, $activityB));
    }

    public function test_event_rate_limit_does_not_apply_to_different_user_or_event(): void
    {
        Config::set('notification_throttle.lifecycle_mutations_per_minute', 1);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $eventA = Event::factory()->create();
        $eventB = Event::factory()->create();
        $limiter = app(LifecycleMutationRateLimiter::class);

        $limiter->recordEventMutation($userA, $eventA);

        $this->assertFalse($limiter->isRateLimitedForEvent($userB, $eventA));
        $this->assertFalse($limiter->isRateLimitedForEvent($userA, $eventB));
    }
}
