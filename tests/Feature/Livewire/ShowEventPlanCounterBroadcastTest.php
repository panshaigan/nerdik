<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanCounterBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_counter_refresh_tick_increments_for_matching_activity_when_plan_tab_is_open(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(['created_by' => $owner->id, 'updated_by' => $owner->id]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $component = Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->set('tab', 'plan');

        $component->assertSet('planCounterRefreshTick', 0);
        $component->call('refreshPlanCountersFromBroadcast', $activity->id);
        $component->assertSet('planCounterRefreshTick', 1);
    }

    public function test_plan_counter_refresh_is_ignored_for_activity_outside_current_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $otherEvent = Event::factory()->public()->create(['created_by' => $owner->id]);
        $otherActivity = Activity::factory()->create(['created_by' => $owner->id, 'updated_by' => $owner->id]);

        Slot::factory()->create([
            'event_id' => $otherEvent->id,
            'activity_id' => $otherActivity->id,
        ]);

        $component = Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->set('tab', 'plan');

        $component->assertSet('planCounterRefreshTick', 0);
        $component->call('refreshPlanCountersFromBroadcast', $otherActivity->id);
        $component->assertSet('planCounterRefreshTick', 0);
    }

    public function test_plan_counter_refresh_is_ignored_when_plan_tab_is_not_active(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(['created_by' => $owner->id, 'updated_by' => $owner->id]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $component = Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->set('tab', 'description');

        $component->assertSet('planCounterRefreshTick', 0);
        $component->call('refreshPlanCountersFromBroadcast', $activity->id);
        $component->assertSet('planCounterRefreshTick', 0);
    }

    public function test_activity_preview_opens_for_attached_activity(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('openActivityPreview', $activity->id)
            ->assertSet('activityPreviewModalOpen', true)
            ->assertSet('previewActivityId', $activity->id)
            ->assertSet('activityPreviewTab', 'info');
    }

    public function test_activity_preview_rejects_activity_outside_current_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $otherEvent = Event::factory()->public()->create(['created_by' => $owner->id]);
        $otherActivity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Slot::factory()->create([
            'event_id' => $otherEvent->id,
            'activity_id' => $otherActivity->id,
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('openActivityPreview', $otherActivity->id);
    }

    public function test_activity_preview_refresh_tick_increments_for_selected_activity_broadcast(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $component = Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->set('tab', 'plan')
            ->call('openActivityPreview', $activity->id)
            ->set('activityPreviewTab', 'participation');

        $component->assertSet('activityPreviewRefreshTick', 0);
        $component->call('refreshPlanCountersFromBroadcast', $activity->id);
        $component->assertSet('activityPreviewRefreshTick', 1);
    }

    public function test_preview_join_action_switches_to_participation_and_adds_participant(): void
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

        Livewire::actingAs($viewer)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('openActivityPreview', $activity->id)
            ->call('joinPreviewActivity')
            ->assertSet('activityPreviewTab', 'participation');

        $this->assertTrue(ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $viewer->id)
            ->exists());
    }
}
