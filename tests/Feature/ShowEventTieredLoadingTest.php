<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\EventShowProposalsTab;
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

        $slot->refresh();
        $this->assertNull($slot->ends_at);

        Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id]);

        $slot->refresh();
        $this->assertNotNull($slot->ends_at);
        $this->assertTrue($slot->ends_at->equalTo($starts->copy()->addMinutes(90)));

        Carbon::setTestNow();
    }

    public function test_pending_proposals_are_empty_on_shell_and_loaded_in_proposals_tab_component(): void
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

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowProposalsTab::class, ['eventId' => $event->id])
            ->assertViewHas('pendingProposals', fn ($c) => $c->isNotEmpty());
    }
}
