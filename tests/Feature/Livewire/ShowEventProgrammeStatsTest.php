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
            ->assertViewHas('confirmedParticipantsCount', 2)
            ->assertViewHas('availablePlacesLabel', '6')
            ->assertSeeHtml('data-ui="event-show-info"')
            ->assertSeeHtml('data-ui="page-header-info"')
            ->assertSee('2/6', false);
    }

    public function test_show_event_does_not_double_count_host_already_on_roster(): void
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
            'user_id' => $owner->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
        ]);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertViewHas('confirmedParticipantsCount', 2)
            ->assertViewHas('availablePlacesLabel', '6')
            ->assertSee('2/6', false);
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
            ->assertViewHas('confirmedParticipantsCount', 1)
            ->assertViewHas('availablePlacesLabel', '∞')
            ->assertSee('1/∞', false);
    }

    public function test_show_event_counts_a_repeated_host_once(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $host->id]);

        $first = Activity::factory()->scheduled()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'max_participants' => 4,
        ]);
        $second = Activity::factory()->scheduled()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'max_participants' => 4,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $first->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $second->id,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $first->id,
            'user_id' => $participant->id,
        ]);

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertViewHas('confirmedParticipantsCount', 2)
            ->assertViewHas('availablePlacesLabel', '9')
            ->assertSee('2/9', false);
    }

    public function test_show_event_adds_one_capacity_seat_for_a_single_host_across_three_activities(): void
    {
        $host = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $host->id]);

        foreach ([4, 5, 6] as $max) {
            $activity = Activity::factory()->scheduled()->create([
                'created_by' => $host->id,
                'updated_by' => $host->id,
                'max_participants' => $max,
            ]);
            Slot::factory()->create([
                'event_id' => $event->id,
                'activity_id' => $activity->id,
            ]);
        }

        Livewire::test(ShowEvent::class, ['event' => $event])
            ->assertViewHas('confirmedParticipantsCount', 1)
            ->assertViewHas('availablePlacesLabel', '16')
            ->assertSee('1/16', false);
    }

    public function test_show_event_does_not_add_a_seat_for_a_passive_host(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $host->id]);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'max_participants' => 5,
            'is_host_passive' => true,
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
            ->assertViewHas('confirmedParticipantsCount', 2)
            ->assertViewHas('availablePlacesLabel', '5')
            ->assertSee('2/5', false);
    }
}
