<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseActivities;
use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseFullTextSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_browse_activities_filters_by_name_using_search_vector(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $matching = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => 'FTS Browse Activity Marker',
        ]);

        $other = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => 'Unrelated Workshop Title',
        ]);

        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $matching->id]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $other->id]);

        Livewire::withoutLazyLoading()
            ->test(BrowseActivities::class)
            ->set('q', 'FTS Browse Activity Marker')
            ->assertSee('FTS Browse Activity Marker')
            ->assertDontSee('Unrelated Workshop Title');
    }

    public function test_browse_events_filters_by_name_using_search_vector(): void
    {
        $owner = User::factory()->create();
        $startsAt = now()->addDays(5)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(4);

        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'name' => 'FTS Browse Event Marker',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'name' => 'Unrelated Conference Title',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('only_events', true)
            ->set('q', 'FTS Browse Event Marker')
            ->assertSee('FTS Browse Event Marker')
            ->assertDontSee('Unrelated Conference Title');
    }

    public function test_browse_activities_matches_description_via_search_vector(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => 'Regular Workshop Title',
            'description' => 'FTS hidden description marker for browse search',
        ]);

        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $activity->id]);

        Livewire::withoutLazyLoading()
            ->test(BrowseActivities::class)
            ->set('q', 'FTS hidden description marker')
            ->assertSee('Regular Workshop Title');
    }

    public function test_empty_query_does_not_filter_browse_listings(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addDays(10)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(3);

        Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'FTS Empty Query Event',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $place = Place::factory()->venue()->create();

        Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'name' => 'FTS Empty Query Activity',
        ]);

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('q', '')
            ->assertSee('FTS Empty Query Event')
            ->assertSee('FTS Empty Query Activity');
    }
}
