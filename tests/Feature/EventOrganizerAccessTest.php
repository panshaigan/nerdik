<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOrganizerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_organizer_cannot_open_create_event_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_event_organizer' => false,
        ]);

        $this->actingAs($user)
            ->get(route('events.create'))
            ->assertForbidden();
    }

    public function test_event_organizer_can_open_create_event_page(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_event_organizer' => true,
        ]);

        $this->actingAs($user)
            ->get(route('events.create'))
            ->assertOk();
    }

    public function test_non_organizer_cannot_copy_event_even_if_owner(): void
    {
        $owner = User::factory()->create([
            'is_admin' => false,
            'is_event_organizer' => false,
        ]);
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'organization_id' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('events.copy', $event))
            ->assertForbidden();
    }
}
