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

class ShowEventProposalsTabActivityPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_activity_preview_succeeds_for_pending_proposal_activity_without_event_slot(): void
    {
        $owner = User::factory()->create();
        $proposer = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create([
            'created_by' => $proposer->id,
            'updated_by' => $proposer->id,
        ]);

        ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $proposer->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->set('tab', 'proposals')
            ->call('openActivityPreview', (int) $activity->id)
            ->assertSet('activityPreviewModalOpen', true)
            ->assertSet('previewActivityId', (int) $activity->id);
    }
}
