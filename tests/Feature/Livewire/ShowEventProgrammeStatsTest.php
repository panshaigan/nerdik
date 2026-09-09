<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventProgrammeStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_event_exposes_participants_over_available_places(): void
    {
        $owner = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'max_participants' => 5,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
        ]);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertViewHas('confirmedParticipantsCount', 1)
            ->assertViewHas('availablePlacesLabel', '5')
            ->assertSee('1/5', false);
    }

    public function test_show_event_uses_infinity_when_any_activity_is_uncapped(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'max_participants' => null,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertViewHas('confirmedParticipantsCount', 0)
            ->assertViewHas('availablePlacesLabel', '∞')
            ->assertSee('0/∞', false);
    }
}
