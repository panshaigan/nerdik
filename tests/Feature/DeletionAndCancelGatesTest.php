<?php

namespace Tests\Feature;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeletionAndCancelGatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_destroy_forbidden_when_scheduled_on_event(): void
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
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        $this->actingAs($host);

        $this->delete(route('activities.destroy', $activity))->assertForbidden();
    }

    public function test_activity_destroy_allowed_for_draft_without_roster(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $this->actingAs($host);

        $this->delete(route('activities.destroy', $activity))
            ->assertRedirect(route('search.index'));
    }

    public function test_event_destroy_forbidden_when_scheduled_activity_has_participants(): void
    {
        $organizer = User::factory()->create();
        $host = User::factory()->create();
        $participant = User::factory()->create();

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

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
        ]);

        $this->actingAs($organizer);

        $this->delete(route('events.destroy', $event))->assertForbidden();
    }

    public function test_event_cancel_sets_cancelled_at_and_clears_on_reopen(): void
    {
        $organizer = User::factory()->create();
        $host = User::factory()->create();
        $participant = User::factory()->create();

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

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
        ]);

        $this->actingAs($organizer);
        Livewire::test(ShowEvent::class, ['event' => $event])
            ->call('cancelEvent');

        $event->refresh();
        $this->assertTrue($event->isCancelled());

        $this->actingAs($organizer);
        Livewire::test(ShowEvent::class, ['event' => $event])
            ->call('reopenEvent');

        $event->refresh();
        $this->assertFalse($event->isCancelled());
    }

    public function test_activity_join_blocked_when_parent_event_cancelled(): void
    {
        $organizer = User::factory()->create();
        $host = User::factory()->create();
        $joiner = User::factory()->create();

        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
            'cancelled_at' => now(),
            'cancelled_by' => $organizer->id,
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'max_participants' => 10,
            'requires_approval' => false,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        $this->actingAs($joiner);

        $this->post(route('activities.join', $activity))
            ->assertRedirect()
            ->assertSessionHas('status', __('ui.events.signup_blocked_event_cancelled'));
    }
}
