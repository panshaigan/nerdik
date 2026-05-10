<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\EventShowReadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EventShowReadCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_programme_stats_cache_is_invalidated_when_slot_is_updated(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $cache = app(EventShowReadCache::class);

        $first = $cache->programmeStats((int) $event->id);
        $this->assertIsArray($first);
        $this->assertArrayHasKey(0, $first);

        $this->assertTrue(Cache::has('event_show.programme_stats.v1.'.$event->id));

        $slot = Slot::query()->where('event_id', $event->id)->firstOrFail();
        $slot->update(['name' => 'Renamed slot for cache bust']);

        $this->assertFalse(Cache::has('event_show.programme_stats.v1.'.$event->id));
    }

    public function test_event_interested_count_cache_invalidates_after_forget(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);

        $cache = app(EventShowReadCache::class);

        $this->assertSame(0, $cache->eventInterestedCount((int) $event->id));
        $this->assertTrue(Cache::has('event_show.interested_count.v1.'.$event->id));

        $user->interestedEvents()->syncWithoutDetaching([$event->id]);

        $this->assertSame(0, $cache->eventInterestedCount((int) $event->id));

        $cache->forgetEventInterestedCount((int) $event->id);

        $this->assertSame(1, $cache->eventInterestedCount((int) $event->id));
    }
}
