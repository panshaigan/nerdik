<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_renders_browse_events_filter_shell(): void
    {
        $response = $this->get(route('search.index'));

        $response->assertOk();
        $response->assertSee('data-ui="browse-events-form"', false);
        $response->assertSee('ui-browse-events-filter-shell', false);
    }

    public function test_search_page_lists_public_event_and_self_hosted_activity_cards(): void
    {
        $user = User::factory()->create();
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);

        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Browse Neon Con Unique Name',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $place = Place::factory()->venue()->create(['name' => 'Dragons Trove Browse Venue']);

        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'name' => 'Browse Neon Activity Unique Name',
        ]);

        $response = $this->get(route('search.index'));

        $response->assertOk();
        $response->assertSee('data-ui="event-card"', false);
        $response->assertSee('data-ui="activity-card"', false);
        $response->assertSee($event->name, false);
        $response->assertSee($activity->name, false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-ui="browse-card-participants"'));
    }
}
