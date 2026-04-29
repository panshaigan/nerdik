<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotEditDataScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_edit_view_does_not_expose_other_users_events(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownerEvent = Event::factory()->create([
            'name' => 'Owner Event Visible',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $foreignEvent = Event::factory()->create([
            'name' => 'Foreign Event Hidden',
            'created_by' => $otherUser->id,
            'updated_by' => $otherUser->id,
        ]);

        $slot = Slot::factory()->create([
            'event_id' => $ownerEvent->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('slots.edit', $slot));

        $response->assertOk();
        $response->assertViewHas('events', function ($events) use ($ownerEvent, $foreignEvent): bool {
            $ids = $events->pluck('id')->map(fn ($id) => (int) $id)->all();

            return in_array((int) $ownerEvent->id, $ids, true)
                && ! in_array((int) $foreignEvent->id, $ids, true);
        });
        $this->assertNotNull($foreignEvent->id);
    }
}
