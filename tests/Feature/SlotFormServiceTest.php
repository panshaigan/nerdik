<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Services\SlotFormService;
use App\Services\TagSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    #[Test]
    public function perform_mass_create_persists_place_id_on_slots(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::create([
            'name' => 'Place ID Event',
            'slug' => 'place-id-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);

        $venue = Place::create([
            'name' => 'Venue X',
            'type' => 'venue',
            'is_online' => false,
        ]);
        $event->places()->attach($venue->id);

        $request = Request::create('/slots/mass', 'POST', [
            'event_id' => $event->id,
            'base_name' => 'Room',
            'count' => 2,
            'venue_place_id' => $venue->id,
            'requires_approval' => 1,
        ]);

        $this->makeService()->performMassCreate($request);

        $this->assertSame(2, Slot::query()->where('event_id', $event->id)->count());
        $this->assertSame(2, Slot::query()->where('event_id', $event->id)->where('place_id', $venue->id)->count());
    }

    #[Test]
    public function perform_slot_update_changes_place_id_without_pivot_sync(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::create([
            'name' => 'Update Place Event',
            'slug' => 'update-place-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);

        $venueA = Place::create(['name' => 'Venue A', 'type' => 'venue', 'is_online' => false]);
        $venueB = Place::create(['name' => 'Venue B', 'type' => 'venue', 'is_online' => false]);
        $event->places()->attach([$venueA->id, $venueB->id]);

        $slot = Slot::create([
            'event_id' => $event->id,
            'name' => 'Slot 1',
            'created_by' => $user->id,
            'place_id' => $venueA->id,
            'requires_approval' => false,
        ]);

        $request = Request::create('/slots/'.$slot->id, 'PUT', [
            'event_id' => $event->id,
            'name' => 'Slot 1 updated',
            'venue_place_id' => $venueB->id,
            'requires_approval' => 0,
        ]);

        $this->makeService()->performSlotUpdate($request, $slot);

        $slot->refresh();
        $this->assertSame($venueB->id, (int) $slot->place_id);
    }

    #[Test]
    public function resolve_mass_slot_place_id_reuses_existing_room_case_insensitively(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::create([
            'name' => 'Case Room Event',
            'slug' => 'case-room-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);

        $venue = Place::create([
            'name' => 'Venue C',
            'type' => 'venue',
            'is_online' => false,
        ]);
        $event->places()->attach($venue->id);

        $existingRoom = Place::create([
            'name' => 'Room Alpha',
            'type' => 'room',
            'parent_id' => $venue->id,
            'is_online' => false,
        ]);

        $request = Request::create('/slots/mass', 'POST', [
            'venue_place_id' => $venue->id,
            'new_room_name' => 'room alpha',
        ]);

        $resolvedPlaceId = $this->makeService()->resolveMassSlotPlaceId($request, $event);

        $this->assertSame($existingRoom->id, $resolvedPlaceId);
        $this->assertSame(1, Place::query()->where('type', 'room')->where('parent_id', $venue->id)->count());
    }
}
