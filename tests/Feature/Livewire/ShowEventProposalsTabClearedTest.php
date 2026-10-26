<?php

namespace Tests\Feature\Livewire;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Events\EventShowProposalsTab;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\User;
use App\Services\EventShowReadCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventProposalsTabClearedTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_switches_to_plan_when_proposals_tab_has_no_pending(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        $shell = Livewire::actingAs($owner)
            ->withQueryParams(['tab' => 'proposals'])
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'plan');

        $this->assertContains('plan', $shell->get('mountedTabs'));
    }

    public function test_clearing_last_pending_proposal_switches_shell_to_plan_tab(): void
    {
        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->proposed()->create(['created_by' => $proposer->id]);
        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $shell = Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSet('tab', 'proposals');

        Livewire::actingAs($owner)
            ->test(EventShowProposalsTab::class, ['eventId' => $event->id])
            ->call('rejectPendingProposal', $proposal->id)
            ->assertHasNoErrors()
            ->assertDispatched('event-show-shell-refresh');

        $this->assertFalse(app(EventShowReadCache::class)->hasPendingProposals((int) $event->id));

        $shell->call('refreshShellFromNestedTabs')
            ->assertSet('tab', 'plan')
            ->assertDontSee(__('ui.events.show_proposals'));

        $this->assertContains('plan', $shell->get('mountedTabs'));
    }
}
