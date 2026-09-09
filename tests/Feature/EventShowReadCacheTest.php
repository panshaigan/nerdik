<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
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

        $this->assertTrue(Cache::has('event_show.programme_stats.v2.'.$event->id));

        $slot = Slot::query()->where('event_id', $event->id)->firstOrFail();
        $slot->update(['name' => 'Renamed slot for cache bust']);

        $this->assertFalse(Cache::has('event_show.programme_stats.v2.'.$event->id));
    }

    public function test_programme_stats_sums_capacity_when_all_activities_are_capped(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);

        $first = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'max_participants' => 4,
        ]);
        $second = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'max_participants' => 6,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $first->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $second->id,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $first->id,
            'user_id' => $participant->id,
        ]);

        [$activities, $participants, $availablePlaces] = app(EventShowReadCache::class)
            ->programmeStats((int) $event->id);

        $this->assertSame(2, $activities);
        $this->assertSame(1, $participants);
        $this->assertSame(10, $availablePlaces);
    }

    public function test_programme_stats_returns_null_available_places_when_any_activity_is_uncapped(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);

        $capped = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'max_participants' => 4,
        ]);
        $uncapped = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'max_participants' => null,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $capped->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $uncapped->id,
        ]);

        [$activities, $participants, $availablePlaces] = app(EventShowReadCache::class)
            ->programmeStats((int) $event->id);

        $this->assertSame(2, $activities);
        $this->assertSame(0, $participants);
        $this->assertNull($availablePlaces);
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

    public function test_pending_proposals_flag_invalidates_when_proposal_saved(): void
    {
        config(['cache.default' => 'array']);

        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
        ]);

        $cache = app(EventShowReadCache::class);

        $this->assertFalse($cache->hasPendingProposals((int) $event->id));
        $this->assertTrue(Cache::has('event_show.pending_proposals.v1.'.$event->id));

        ActivityProposal::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $this->assertFalse(Cache::has('event_show.pending_proposals.v1.'.$event->id));

        $this->assertTrue($cache->hasPendingProposals((int) $event->id));
    }
}
