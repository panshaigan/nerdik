<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\EventShowPlanTab;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanTabProposalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_plan_tab_shows_propose_ctas_when_before_event_and_outside_enrollment_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('canShowPlanActivityProposalUi', true)
            ->assertSee(__('ui.events.propose_activity'))
            ->assertSee(__('ui.events.plan_propose_hero_title'));
    }

    public function test_plan_tab_hides_propose_ctas_after_event_has_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('canShowPlanActivityProposalUi', false)
            ->assertDontSee(__('ui.events.propose_activity'))
            ->assertDontSee(__('ui.events.plan_propose_hero_title'));
    }

    public function test_plan_tab_hides_propose_ctas_during_active_enrollment_window_when_no_empty_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        EventEnrollmentWindow::query()->create([
            'name' => 'Signups',
            'event_id' => $event->id,
            'starts_at' => Carbon::parse('2026-05-05 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-05 18:00:00', 'UTC'),
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('canShowPlanActivityProposalUi', false)
            ->assertDontSee(__('ui.events.propose_activity'));
    }

    public function test_plan_tab_shows_propose_ctas_during_active_enrollment_window_when_empty_slots_remain(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'name' => 'Enrollment Empty Slot',
        ]);
        EventEnrollmentWindow::query()->create([
            'name' => 'Signups',
            'event_id' => $event->id,
            'starts_at' => Carbon::parse('2026-05-05 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-05 18:00:00', 'UTC'),
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('canShowPlanActivityProposalUi', true)
            ->assertSee(__('ui.events.propose_activity'))
            ->assertSee(__('ui.events.plan_propose_hero_title'));
    }

    public function test_plan_tab_hides_propose_ctas_during_active_enrollment_window_when_all_slots_are_filled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'name' => 'Filled Enrollment Slot Activity',
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'name' => 'Filled Enrollment Slot',
        ]);
        EventEnrollmentWindow::query()->create([
            'name' => 'Signups',
            'event_id' => $event->id,
            'starts_at' => Carbon::parse('2026-05-05 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-05 18:00:00', 'UTC'),
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($user)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('canShowPlanActivityProposalUi', false)
            ->assertDontSee(__('ui.events.propose_activity'));
    }

    public function test_empty_slots_are_visible_by_default_outside_restricted_windows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $organizer = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $emptySlot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'name' => 'Plan Visibility Empty Slot',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSet('showEmptySlots', true)
            ->assertSee($emptySlot->name);
    }

    public function test_empty_slots_are_visible_by_default_for_non_organizer_during_active_enrollment_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $organizer = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $emptySlot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'name' => 'Enrollment Visible Empty Slot',
        ]);
        EventEnrollmentWindow::query()->create([
            'name' => 'Signups',
            'event_id' => $event->id,
            'starts_at' => Carbon::parse('2026-05-05 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-05 18:00:00', 'UTC'),
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSet('showEmptySlots', true)
            ->assertSee($emptySlot->name);
    }

    public function test_empty_slots_are_hidden_by_default_for_non_organizer_after_event_has_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-15 12:00:00', 'UTC'));
        $organizer = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $emptySlot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'name' => 'Started Event Empty Slot',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSet('showEmptySlots', false)
            ->assertDontSee($emptySlot->name);
    }

    public function test_activity_attached_slots_remain_visible_when_empty_slots_are_hidden(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-05 12:00:00', 'UTC'));
        $organizer = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        EventEnrollmentWindow::query()->create([
            'name' => 'Signups',
            'event_id' => $event->id,
            'starts_at' => Carbon::parse('2026-05-05 08:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-05 18:00:00', 'UTC'),
            'max_activities_per_user' => null,
            'max_allowed_participants_per_activity' => null,
            'accumulative_activities' => false,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'name' => 'Always Hidden Empty Slot',
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $organizer->id,
            'name' => 'Always Visible Activity',
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'name' => 'Attached Slot Name',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertSet('showEmptySlots', true)
            ->set('showEmptySlots', false)
            ->assertSee($activity->name)
            ->assertDontSee('Always Hidden Empty Slot');
    }

    public function test_empty_slots_toggle_is_hidden_when_event_has_no_empty_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $organizer = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $organizer->id,
            'name' => 'Filled Slot Activity',
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'name' => 'Filled Slot',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('hasEmptySlots', false)
            ->assertDontSeeHtml('data-ui="event-show-toggle-empty-slots"');
    }

    public function test_empty_slots_toggle_is_hidden_when_event_has_no_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $organizer = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('hasEmptySlots', false)
            ->assertDontSeeHtml('data-ui="event-show-toggle-empty-slots"');
    }

    public function test_empty_slots_toggle_remains_hidden_when_event_has_empty_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00', 'UTC'));
        $organizer = User::factory()->create();
        $viewer = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'starts_at' => Carbon::parse('2026-05-10 12:00:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-05-10 20:00:00', 'UTC'),
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'name' => 'Toggle Visible Empty Slot',
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->assertViewHas('hasEmptySlots', true)
            ->assertDontSeeHtml('data-ui="event-show-toggle-empty-slots"');
    }
}
