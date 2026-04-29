<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceRoomsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_list_rooms_for_foreign_venue(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $venue = Place::factory()->create([
            'type' => 'venue',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Place::factory()->create([
            'type' => 'room',
            'parent_id' => $venue->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($stranger)
            ->getJson(route('places.rooms', ['venueId' => $venue->id]))
            ->assertOk()
            ->assertJson(['rooms' => []]);
    }

    public function test_owner_can_list_rooms_for_their_venue(): void
    {
        $owner = User::factory()->create();

        $venue = Place::factory()->create([
            'type' => 'venue',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $room = Place::factory()->create([
            'name' => 'Owner Room',
            'type' => 'room',
            'parent_id' => $venue->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->getJson(route('places.rooms', ['venueId' => $venue->id]))
            ->assertOk()
            ->assertJsonFragment(['id' => $room->id, 'name' => 'Owner Room']);
    }
}
