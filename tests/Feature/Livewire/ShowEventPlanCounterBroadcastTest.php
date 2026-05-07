<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
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
}
