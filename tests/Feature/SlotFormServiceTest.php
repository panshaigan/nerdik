<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Services\SlotFormService;
use App\Services\TagSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exercises venue/room helpers used by {@see SlotFormService}.
 * Requires a PDO database (e.g. MariaDB `testing` or SQLite) and {@see RefreshDatabase}.
 */
class SlotFormServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(): SlotFormService
    {
        return new SlotFormService(app(TagSelectionService::class));
    }

    #[Test]
    public function mass_form_place_data_for_all_events_returns_empty_when_no_events(): void
    {
        $data = $this->makeService()->massFormPlaceDataForAllEvents();

        $this->assertSame([], $data['eventVenuesByEventId']);
        $this->assertSame([], $data['roomsByEventAndVenue']);
    }

    #[Test]
    public function venues_for_event_mass_form_prefers_venue_type_and_lists_rooms_by_venue(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Test Event',
            'slug' => 'test-event-slot-form',
            'created_by' => $user->id,
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'is_public' => true,
        ]);

        $venue = Place::create([
            'name' => 'Main Hall',
            'type' => 'venue',
            'is_online' => false,
        ]);

        Place::create([
            'name' => 'Side Room',
            'type' => 'room',
            'parent_id' => $venue->id,
            'is_online' => false,
        ]);

        $event->places()->attach($venue->id);

        $event->refresh();
        $event->load('places');

        $service = $this->makeService();

        $venues = $service->venuesForEventMassForm($event);
        $this->assertCount(1, $venues);
        $this->assertSame('venue', $venues->first()->type);

        $roomsByVenue = $service->roomOptionsByVenueId($venues);
        $this->assertArrayHasKey($venue->id, $roomsByVenue);
        $this->assertCount(1, $roomsByVenue[$venue->id]);
        $this->assertSame('Side Room', $roomsByVenue[$venue->id][0]['name']);
    }
}
