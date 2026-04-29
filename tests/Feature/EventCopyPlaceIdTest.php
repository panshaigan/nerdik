<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventCopyPlaceIdTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function copying_event_copies_slots_with_place_id(): void
    {
        $user = User::factory()->create([
            'is_event_organizer' => true,
        ]);
        $this->actingAs($user);

        $event = Event::create([
            'name' => 'Original Event',
            'slug' => 'original-event',
            'created_by' => $user->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'is_public' => true,
        ]);

        $venue = Place::create([
            'name' => 'Copy Venue',
            'type' => 'venue',
            'is_online' => false,
        ]);

        Slot::create([
            'event_id' => $event->id,
            'name' => 'Slot A',
            'created_by' => $user->id,
            'place_id' => $venue->id,
            'requires_approval' => false,
        ]);

        $response = $this->post(route('events.copy', $event));
        $response->assertRedirect();

        $copied = Event::query()
            ->where('id', '!=', $event->id)
            ->latest('id')
            ->firstOrFail();

        $copiedSlot = Slot::query()->where('event_id', $copied->id)->firstOrFail();
        $this->assertSame($venue->id, (int) $copiedSlot->place_id);
    }
}
