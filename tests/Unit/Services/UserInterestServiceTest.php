<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\EventShowReadCache;
use App\Services\UserInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserInterestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_activity_interest_on_event_hosted_activity_creates_both_rows(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $activity = Activity::factory()->scheduled()->create();
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        app(UserInterestService::class)->addActivityInterest($user, $activity);

        $this->assertTrue($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());
        $this->assertTrue($user->fresh()->interestedEvents()->whereKey($event->id)->exists());
    }

    public function test_add_activity_interest_on_self_hosted_activity_creates_activity_row_only(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        app(UserInterestService::class)->addActivityInterest($user, $activity);

        $this->assertTrue($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());
        $this->assertSame(0, $user->fresh()->interestedEvents()->count());
    }

    public function test_add_activity_interest_when_user_already_interested_in_event_does_not_inflate_count(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $event = Event::factory()->public()->create();
        $activity = Activity::factory()->scheduled()->create();
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $user->interestedEvents()->attach($event->id);
        $otherUser->interestedEvents()->attach($event->id);

        $cache = app(EventShowReadCache::class);
        $this->assertSame(2, $cache->eventInterestedCount((int) $event->id));

        app(UserInterestService::class)->addActivityInterest($user, $activity);

        $this->assertSame(2, $cache->eventInterestedCount((int) $event->id));
    }

    public function test_remove_activity_interest_does_not_remove_event_interest(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $activity = Activity::factory()->scheduled()->create();
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        app(UserInterestService::class)->addActivityInterest($user, $activity);
        app(UserInterestService::class)->removeActivityInterest($user, $activity);

        $this->assertFalse($user->fresh()->interestedActivities()->whereKey($activity->id)->exists());
        $this->assertTrue($user->fresh()->interestedEvents()->whereKey($event->id)->exists());
    }

    public function test_add_event_interest_forgets_cached_count(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $cache = app(EventShowReadCache::class);

        $this->assertSame(0, $cache->eventInterestedCount((int) $event->id));
        $this->assertTrue(Cache::has('event_show.interested_count.v1.'.$event->id));

        app(UserInterestService::class)->addEventInterest($user, $event);

        $this->assertFalse(Cache::has('event_show.interested_count.v1.'.$event->id));
        $this->assertSame(1, $cache->eventInterestedCount((int) $event->id));
    }

    public function test_remove_event_interest_forgets_cached_count(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $user->interestedEvents()->attach($event->id);

        $cache = app(EventShowReadCache::class);
        $this->assertSame(1, $cache->eventInterestedCount((int) $event->id));

        app(UserInterestService::class)->removeEventInterest($user, $event);

        $this->assertFalse(Cache::has('event_show.interested_count.v1.'.$event->id));
        $this->assertSame(0, $cache->eventInterestedCount((int) $event->id));
    }

    public function test_toggle_methods_return_added_or_removed(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $activity = Activity::factory()->scheduled()->create();
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $service = app(UserInterestService::class);

        $this->assertTrue($service->toggleEventInterest($user, $event));
        $this->assertFalse($service->toggleEventInterest($user, $event));

        $this->assertTrue($service->toggleActivityInterest($user, $activity));
        $this->assertFalse($service->toggleActivityInterest($user, $activity));
    }
}
