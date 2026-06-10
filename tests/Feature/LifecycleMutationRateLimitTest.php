<?php

namespace Tests\Feature;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityHostingModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LifecycleMutationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_activity_lifecycle_mutation_within_one_minute_is_rate_limited(): void
    {
        config(['notification_throttle.lifecycle_mutations_per_minute' => 1]);

        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $service = app(ActivityHostingModeService::class);

        $cancelResult = $service->cancel($activity, $host, 'Unavailable');
        $this->assertTrue($cancelResult->performed);

        $reopenResult = $service->reopen($activity->fresh(), $host);
        $this->assertTrue($reopenResult->rateLimited);
        $this->assertNotNull($activity->fresh()->cancelled_at);
    }

    public function test_lifecycle_mutations_on_different_activities_do_not_share_rate_limit(): void
    {
        config(['notification_throttle.lifecycle_mutations_per_minute' => 1]);

        $host = User::factory()->create();
        $activityA = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        $activityB = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $service = app(ActivityHostingModeService::class);

        $this->assertTrue($service->cancel($activityA, $host, 'A')->performed);
        $this->assertTrue($service->cancel($activityB, $host, 'B')->performed);
        $this->assertNotNull($activityA->fresh()->cancelled_at);
        $this->assertNotNull($activityB->fresh()->cancelled_at);
    }

    public function test_second_event_lifecycle_mutation_within_one_minute_is_rate_limited(): void
    {
        config(['notification_throttle.lifecycle_mutations_per_minute' => 1]);

        $organizer = User::factory()->create();
        $host = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        Livewire::withoutLazyLoading()
            ->actingAs($organizer)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('cancelEvent')
            ->assertDispatched('slot-mutations-refresh');

        $event->refresh();
        $this->assertNotNull($event->cancelled_at);

        Livewire::withoutLazyLoading()
            ->actingAs($organizer)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('reopenEvent');

        $event->refresh();
        $this->assertNotNull($event->cancelled_at);
    }
}
