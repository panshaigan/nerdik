<?php

namespace Tests\Feature\Livewire;

use App\Enums\ActivityProposalStatus;
use App\Enums\ParticipationMode;
use App\Livewire\Events\EventShowActivityPreviewModal;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\EventShowProposalsTab;
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

    public function test_plan_tab_renders_preserve_scroll_activity_preview_trigger_on_attached_slot(): void
    {
        $host = User::factory()->create(['nickname' => 'Plan Tab Host']);
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $host->id]);
        $activity = Activity::factory()->create(['created_by' => $host->id, 'updated_by' => $host->id]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $activityId = (int) $activity->id;

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSee('Plan Tab Host')
            ->assertSeeHtml('data-ui="event-show-slot-host"')
            ->assertSeeHtml('inline-flex w-fit max-w-full pointer-events-auto')
            ->assertSeeHtml('wire:key="user-badge-contact-'.$host->id.'-'.$activity->id.'-0-late-0"')
            ->assertSeeHtml('wire:click.preserve-scroll="openActivityPreview('.$activityId.')"')
            ->assertSeeHtml('data-ui="event-show-slot-open-activity-preview"')
            ->assertDontSeeHtml('data-ui="event-show-slot-activity-preview-loading"');
    }

    public function test_plan_tab_open_activity_preview_dispatches_to_parent_without_re_render(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $host->id]);
        $activity = Activity::factory()->create(['created_by' => $host->id, 'updated_by' => $host->id]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->call('openActivityPreview', $activity->id)
            ->assertDispatched('open-event-activity-preview', activityId: $activity->id);
    }

    public function test_proposals_tab_renders_preserve_scroll_activity_preview_trigger(): void
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
            ->assertSeeHtml('wire:click.preserve-scroll="openActivityPreview('.$activityId.')"')
            ->assertSeeHtml('data-ui="event-show-proposal-open-activity-preview"')
            ->assertDontSeeHtml('data-ui="event-show-proposal-activity-preview-loading"')
            ->assertSeeHtml('data-ui="event-show-proposal-actions"')
            ->assertSeeHtml('data-ui="event-show-proposal-reject"')
            ->assertSeeHtml('wire:click="rejectPendingProposal(');
    }

    public function test_opening_second_activity_preview_does_not_re_render_plan_tab(): void
    {
        $host = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $host->id]);
        $firstActivity = Activity::factory()->scheduled()->create(['created_by' => $host->id, 'updated_by' => $host->id]);
        $secondActivity = Activity::factory()->scheduled()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'name' => 'Second Slot Activity',
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $firstActivity->id,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $secondActivity->id,
        ]);

        $planTab = Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id]);

        $planTab
            ->call('openActivityPreview', $firstActivity->id)
            ->assertDispatched('open-event-activity-preview', activityId: $firstActivity->id)
            ->assertSet('planCounterRefreshTick', 0)
            ->call('openActivityPreview', $secondActivity->id)
            ->assertDispatched('open-event-activity-preview', activityId: $secondActivity->id)
            ->assertSet('planCounterRefreshTick', 0);

        Livewire::actingAs($viewer)
            ->test(EventShowActivityPreviewModal::class, ['eventId' => $event->id])
            ->call('handleOpenEventActivityPreview', $firstActivity->id)
            ->assertSet('activityPreviewModalOpen', true)
            ->call('handleOpenEventActivityPreview', $secondActivity->id)
            ->assertSet('previewActivityId', $secondActivity->id);
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
            'participation_mode' => ParticipationMode::Open,
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
            ->test(EventShowActivityPreviewModal::class, ['eventId' => $event->id])
            ->call('handleOpenEventActivityPreview', $activity->id)
            ->assertSeeHtml('data-ui="overlay-sticky-tabs"')
            ->assertSeeHtml('wire:target="joinPreviewActivity"')
            ->assertSeeHtml('wire:loading.attr="disabled"');
    }

    public function test_open_activity_preview_gracefully_handles_missing_activity(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowActivityPreviewModal::class, ['eventId' => $event->id])
            ->call('handleOpenEventActivityPreview', 999_999)
            ->assertSet('activityPreviewModalOpen', false)
            ->assertSet('previewActivityId', null);
    }
}
