<?php

namespace Tests\Feature\Livewire;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventOrganizerProposalBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_proposals_tab_appears_after_incoming_proposal_broadcast_handler(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $proposer = User::factory()->create();
        $activity = Activity::factory()->proposed()->create(['created_by' => $proposer->id]);

        $component = Livewire::actingAs($owner)->test(ShowEvent::class, ['event' => $event]);

        $component->assertDontSee(__('ui.events.show_proposals'));

        ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $component->call('__dispatch', 'event-proposal-submitted-broadcast', [
            'eventId' => $event->id,
        ]);

        $component->assertSee(__('ui.events.show_proposals'))
            ->assertSet('organizerProposalRefreshTick', 1);
    }

    public function test_incoming_proposal_broadcast_ignored_when_event_id_does_not_match(): void
    {
        $owner = User::factory()->create();
        $eventMounted = Event::factory()->public()->create(['created_by' => $owner->id]);
        $otherEvent = Event::factory()->public()->create(['created_by' => $owner->id]);

        $component = Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $eventMounted]);

        $component->assertSet('organizerProposalRefreshTick', 0);
        $component->call('refreshOrganizerForIncomingProposal', $otherEvent->id);
        $component->assertSet('organizerProposalRefreshTick', 0);
    }

    public function test_incoming_proposal_broadcast_ignored_when_user_cannot_manage_event(): void
    {
        $owner = User::factory()->create();
        $guest = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $component = Livewire::actingAs($guest)->test(ShowEvent::class, ['event' => $event]);

        $component->assertDontSee(__('ui.events.show_proposals'))
            ->assertSet('organizerProposalRefreshTick', 0);

        $component->call('refreshOrganizerForIncomingProposal', $event->id);

        $component->assertDontSee(__('ui.events.show_proposals'))
            ->assertSet('organizerProposalRefreshTick', 0);
    }
}
