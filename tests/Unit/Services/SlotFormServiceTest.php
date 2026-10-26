<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Services\SlotFormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        return app(SlotFormService::class);
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

    #[Test]
    public function perform_mass_create_persists_forced_participation_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::create([
            'name' => 'Participation Event',
            'slug' => 'participation-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);

        $request = Request::create('/slots/mass', 'POST', [
            'event_id' => $event->id,
            'base_name' => 'Table',
            'count' => 1,
            'requires_approval' => 0,
            'forces_participation_settings' => 1,
            'participation_mode' => 'host_approval',
            'allows_observers' => 1,
        ]);

        $this->makeService()->performMassCreate($request);

        $slot = Slot::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertTrue($slot->forces_participation_settings);
        $this->assertSame('host_approval', $slot->participation_mode?->value);
        $this->assertTrue($slot->allows_observers);
        $this->assertNull($slot->lottery_draw_in_hours);
    }

    #[Test]
    public function perform_mass_create_clears_participation_when_not_forcing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::create([
            'name' => 'No Force Event',
            'slug' => 'no-force-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);

        $request = Request::create('/slots/mass', 'POST', [
            'event_id' => $event->id,
            'base_name' => 'Table',
            'count' => 1,
            'requires_approval' => 0,
            'forces_participation_settings' => 0,
            'participation_mode' => 'lottery',
            'lottery_draw_in_hours' => 12,
        ]);

        $this->makeService()->performMassCreate($request);

        $slot = Slot::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertFalse($slot->forces_participation_settings);
        $this->assertNull($slot->participation_mode);
        $this->assertNull($slot->lottery_draw_in_hours);
    }

    #[Test]
    public function mass_form_place_data_for_all_events_includes_attached_venues(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Mass Form Event',
            'slug' => 'mass-form-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);
        $venue = Place::create(['name' => 'Mass Venue', 'type' => 'venue', 'is_online' => false]);
        Place::create([
            'name' => 'Mass Room',
            'type' => 'room',
            'parent_id' => $venue->id,
            'is_online' => false,
        ]);
        $event->places()->attach($venue->id);

        $data = $this->makeService()->massFormPlaceDataForAllEvents();

        $this->assertArrayHasKey($event->id, $data['eventVenuesByEventId']);
        $this->assertNotEmpty($data['eventVenuesByEventId'][$event->id]);
        $this->assertArrayHasKey($event->id, $data['roomsByEventAndVenue']);
    }

    #[Test]
    public function mass_form_place_data_for_user_includes_owned_events(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owned = Event::create([
            'name' => 'Owned Event',
            'slug' => 'owned-event-slot-form',
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);
        Event::create([
            'name' => 'Other Event',
            'slug' => 'other-event-slot-form',
            'created_by' => $other->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);
        $venue = Place::create(['name' => 'Owned Venue', 'type' => 'venue', 'is_online' => false]);
        $owned->places()->attach($venue->id);

        $data = $this->makeService()->massFormPlaceDataForUser($owner->id, false);

        $this->assertArrayHasKey($owned->id, $data['eventVenuesByEventId']);
        $this->assertCount(1, $data['eventVenuesByEventId']);
    }

    #[Test]
    public function slot_venue_room_defaults_from_place_resolve_venue_and_room(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Defaults Event',
            'slug' => 'defaults-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);
        $venue = Place::create(['name' => 'Defaults Venue', 'type' => 'venue', 'is_online' => false]);
        $room = Place::create([
            'name' => 'Defaults Room',
            'type' => 'room',
            'parent_id' => $venue->id,
            'is_online' => false,
        ]);
        $event->places()->attach($venue->id);

        $venueSlot = Slot::create([
            'event_id' => $event->id,
            'name' => 'Venue Slot',
            'created_by' => $user->id,
            'place_id' => $venue->id,
            'requires_approval' => false,
        ]);
        $roomSlot = Slot::create([
            'event_id' => $event->id,
            'name' => 'Room Slot',
            'created_by' => $user->id,
            'place_id' => $room->id,
            'requires_approval' => false,
        ]);
        $emptySlot = Slot::create([
            'event_id' => $event->id,
            'name' => 'Empty Slot',
            'created_by' => $user->id,
            'place_id' => null,
            'requires_approval' => false,
        ]);

        $service = $this->makeService();
        $this->assertSame(
            ['venue_place_id' => $venue->id, 'room_name' => null],
            $service->slotVenueRoomDefaultsFromPlace($venueSlot),
        );
        $this->assertSame(
            ['venue_place_id' => $venue->id, 'room_name' => 'Defaults Room'],
            $service->slotVenueRoomDefaultsFromPlace($roomSlot),
        );
        $this->assertSame(
            ['venue_place_id' => null, 'room_name' => null],
            $service->slotVenueRoomDefaultsFromPlace($emptySlot),
        );
    }

    #[Test]
    public function ensure_venue_when_event_has_places_requires_venue_selection(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Require Venue Event',
            'slug' => 'require-venue-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);
        $venue = Place::create(['name' => 'Required Venue', 'type' => 'venue', 'is_online' => false]);
        $event->places()->attach($venue->id);

        $this->expectException(ValidationException::class);
        $this->makeService()->ensureVenueWhenEventHasPlaces(
            Request::create('/slots', 'POST', []),
            $event->fresh()->load('places'),
        );
    }

    #[Test]
    public function redirect_helpers_return_to_event_when_slug_matches(): void
    {
        $user = User::factory()->create();
        $event = Event::create([
            'name' => 'Redirect Event',
            'slug' => 'redirect-event-slot',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);
        $slot = Slot::create([
            'event_id' => $event->id,
            'name' => 'Redirect Slot',
            'created_by' => $user->id,
            'requires_approval' => false,
        ]);

        $service = $this->makeService();
        $withSlug = Request::create('/slots', 'POST', [
            'redirect_to_event_slug' => $event->slug,
            'mass' => 1,
        ]);
        $withoutSlug = Request::create('/slots', 'POST', []);

        $store = $service->redirectAfterSlotStore($withSlug, $event->id);
        $this->assertTrue($store->isRedirect());
        $this->assertSame(route('events.show', $event), $store->getTargetUrl());

        $update = $service->redirectAfterSlotUpdate($withSlug, $slot);
        $this->assertSame(route('events.show', $event), $update->getTargetUrl());

        $failed = $service->redirectSlotUpdateValidationFailed($withSlug, $slot, [
            'name' => ['Required'],
        ]);
        $this->assertSame(route('events.show', $event), $failed->getTargetUrl());
        $this->assertSame($slot->id, $failed->getSession()->get('open_slot_edit'));

        $dashboard = $service->redirectAfterSlotStore($withoutSlug, $event->id);
        $this->assertSame(route('dashboard'), $dashboard->getTargetUrl());
    }

    #[Test]
    public function mass_create_wraps_validation_errors_as_redirect(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::create([
            'name' => 'Mass Redirect Event',
            'slug' => 'mass-redirect-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);
        $venue = Place::create(['name' => 'Mass Redirect Venue', 'type' => 'venue', 'is_online' => false]);
        $event->places()->attach($venue->id);

        $request = Request::create('/slots/mass', 'POST', [
            'event_id' => $event->id,
            'base_name' => 'Table',
            'count' => 1,
            'requires_approval' => 0,
            'redirect_to_event_slug' => $event->slug,
            // missing venue_place_id while event has places
        ]);
        $request->setLaravelSession($this->app['session']->driver());

        $response = $this->makeService()->massCreate($request);

        $this->assertTrue($response->isRedirect());
        $this->assertTrue($response->getSession()->has('errors'));
    }

    #[Test]
    public function perform_mass_create_treats_zero_max_capacity_as_null(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = Event::create([
            'name' => 'Capacity Event',
            'slug' => 'capacity-event-slot',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);

        $request = Request::create('/slots/mass', 'POST', [
            'event_id' => $event->id,
            'base_name' => 'Open',
            'count' => 1,
            'requires_approval' => 0,
            'max_capacity' => 0,
        ]);

        $this->makeService()->performMassCreate($request);

        $slot = Slot::query()->where('event_id', $event->id)->firstOrFail();
        $this->assertNull($slot->max_capacity);
    }
}
