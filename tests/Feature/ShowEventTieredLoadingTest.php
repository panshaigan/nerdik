<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventTieredLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_end_sync_runs_when_plan_tab_is_shown_not_on_description_tab(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 12:00:00', 'UTC'));

        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'duration_in_minutes' => 90,
        ]);
        $starts = Carbon::parse('2026-06-01 10:00:00', 'UTC');
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => $starts,
            'ends_at' => null,
            'created_by' => $user->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event]);

        $slot->refresh();
        $this->assertNull($slot->ends_at);

        $component->set('tab', 'plan');

        $slot->refresh();
        $this->assertNotNull($slot->ends_at);
        $this->assertTrue($slot->ends_at->equalTo($starts->copy()->addMinutes(90)));

        Carbon::setTestNow();
    }

    public function test_pending_proposals_collection_is_empty_on_description_tab_and_loaded_on_proposals_tab(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
        ]);
        ActivityProposal::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Pending,
        ]);

        $component = Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event]);

        $component->assertViewHas('pendingProposals', fn ($c) => $c->isEmpty());

        $component->set('tab', 'proposals');

        $component->assertViewHas('pendingProposals', fn ($c) => $c->isNotEmpty());
    }
}
