<?php

namespace Tests\Feature;

use App\Livewire\Events\EventShowPlanTab;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityHostingModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActivityCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_cancel_and_reopen_activity_via_service(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $service = app(ActivityHostingModeService::class);

        $service->cancel($activity, $host, 'Host is unavailable');
        $activity->refresh();

        $this->assertNotNull($activity->cancelled_at);
        $this->assertSame($host->id, (int) $activity->cancelled_by);
        $this->assertSame('Host is unavailable', $activity->cancel_reason);

        $service->reopen($activity, $host);
        $activity->refresh();

        $this->assertNull($activity->cancelled_at);
        $this->assertNull($activity->cancelled_by);
        $this->assertNull($activity->cancel_reason);
    }

    public function test_event_organizer_can_cancel_and_reopen_attached_activity_from_event_screen(): void
    {
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
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        $component = Livewire::withoutLazyLoading()
            ->actingAs($organizer)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id]);

        $component
            ->set('slotCancelReason.'.$slot->id, 'Venue emergency')
            ->call('cancelSlotActivity', $slot->id);

        $activity->refresh();
        $this->assertNotNull($activity->cancelled_at);
        $this->assertSame($organizer->id, (int) $activity->cancelled_by);
        $this->assertSame('Venue emergency', $activity->cancel_reason);
        $this->assertSame($activity->id, $slot->fresh()->activity_id, 'Cancelled activity should remain attached to the slot.');

        $component->call('reopenSlotActivity', $slot->id);
        $activity->refresh();
        $this->assertNull($activity->cancelled_at);
    }

    public function test_unrelated_user_cannot_cancel_slot_activity(): void
    {
        $organizer = User::factory()->create();
        $host = User::factory()->create();
        $intruder = User::factory()->create();

        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        $component = Livewire::withoutLazyLoading()
            ->actingAs($intruder)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id]);

        $this->expectException(HttpException::class);
        $component->instance()->cancelSlotActivity($slot->id, app(ActivityHostingModeService::class));
    }

    public function test_participation_endpoints_block_cancelled_and_non_joinable_modes(): void
    {
        $user = User::factory()->create();
        $venue = Place::query()->create([
            'name' => 'Test Venue',
            'type' => 'venue',
            'is_online' => false,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $cancelled = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $venue->id,
            'starts_at' => now()->addDay(),
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);
        $draft = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'place_id' => null,
            'starts_at' => null,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $cancelled->id,
            'user_id' => $user->id,
            'is_absent' => false,
        ]);

        $this->actingAs($user);

        $this->post(route('activities.join', $cancelled))
            ->assertRedirect()
            ->assertSessionHas('status', __('ui.activities.signup_blocked_cancelled'));

        $this->post(route('activities.leave', $cancelled))
            ->assertRedirect()
            ->assertSessionHas('status', __('ui.activities.signup_blocked_cancelled'));

        $this->post(route('activities.join', $draft))
            ->assertRedirect()
            ->assertSessionHas('status', __('ui.activities.signup_blocked_not_joinable_mode'));

        $this->post(route('activities.join-waitlist', $draft))
            ->assertRedirect()
            ->assertSessionHas('status', __('ui.activities.signup_blocked_not_joinable_mode'));
    }
}
