<?php

namespace Tests\Feature\Livewire;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\EventShowProposalsTab;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanTabActivityPreviewLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_tab_renders_activity_preview_loading_markers_on_attached_slot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(['created_by' => $owner->id, 'updated_by' => $owner->id]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $activityId = (int) $activity->id;

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSeeHtml('wire:target="openActivityPreview('.$activityId.')"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:loading.delay')
            ->assertSeeHtml('loading loading-spinner loading-lg');
    }

    public function test_proposals_tab_renders_activity_preview_loading_markers(): void
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

        $activityId = (int) $activity->id;

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowProposalsTab::class, ['eventId' => $event->id])
            ->assertSeeHtml('wire:target="openActivityPreview('.$activityId.')"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:loading.delay')
            ->assertSeeHtml('loading loading-spinner loading-lg')
            ->assertSeeHtml('data-ui="event-show-proposal-open-activity-preview"');
    }

    public function test_plan_tab_toggle_empty_slots_button_has_loading_indicator(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSeeHtml('wire:target="toggleShowEmptySlots"')
            ->assertSeeHtml('wire:loading.attr="disabled"');
    }

    public function test_activity_preview_modal_join_leave_buttons_have_loading_indicator(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'requires_approval' => false,
            'max_participants' => 4,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(12, 0),
        ]);

        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'max_activities_per_user' => 2,
            'max_allowed_participants_per_activity' => 4,
            'accumulative_activities' => false,
            'created_by' => $owner->id,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(ShowEvent::class, ['event' => $event])
            ->set('tab', 'plan')
            ->call('openActivityPreview', $activity->id)
            ->assertSeeHtml('wire:target="joinPreviewActivity"')
            ->assertSeeHtml('wire:loading.attr="disabled"');
    }
}
