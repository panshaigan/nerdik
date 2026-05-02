<?php

namespace Tests\Feature\Notifications;

use App\Enums\ActivityProposalStatus;
use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Notifications\ActivityCancelledNotification;
use App\Notifications\EventCancelledNotification;
use App\Services\ActivityHostingModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CancellationNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_cancellation_notifies_participants_and_host_when_actor_is_not_the_host(): void
    {
        Notification::fake();

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
            'is_host_passive' => true,
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

        app(ActivityHostingModeService::class)->cancel($activity->fresh(), $organizer, 'Organizer cancelled');

        Notification::assertSentTo($participant, ActivityCancelledNotification::class);
        Notification::assertSentTo($host, ActivityCancelledNotification::class);
        Notification::assertNotSentTo($organizer, ActivityCancelledNotification::class);
    }

    public function test_activity_cancellation_by_host_notifies_participants_but_not_the_host(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $participant = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
        ]);

        app(ActivityHostingModeService::class)->cancel($activity->fresh(), $host, 'Sorry');

        Notification::assertSentTo($participant, ActivityCancelledNotification::class);
        Notification::assertNotSentTo($host, ActivityCancelledNotification::class);
    }

    public function test_activity_cancellation_notifies_waitlist_users(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $waitlistUser = User::factory()->create();

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'requires_approval' => true,
        ]);

        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $waitlistUser->id,
            'position' => 1,
        ]);

        app(ActivityHostingModeService::class)->cancel($activity->fresh(), $host, 'Sorry');

        Notification::assertSentTo($waitlistUser, ActivityCancelledNotification::class);
        Notification::assertNotSentTo($host, ActivityCancelledNotification::class);
    }

    public function test_event_deletion_notifies_pending_proposers_when_no_slot_roster(): void
    {
        Notification::fake();

        $organizer = User::factory()->create();
        $proposer = User::factory()->create();

        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        $proposedActivity = Activity::factory()->create([
            'created_by' => $proposer->id,
            'updated_by' => $proposer->id,
            'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
        ]);

        ActivityProposal::query()->create([
            'activity_id' => $proposedActivity->id,
            'event_id' => $event->id,
            'status' => ActivityProposalStatus::Pending,
            'created_by' => $proposer->id,
            'updated_by' => $proposer->id,
        ]);

        $this->actingAs($organizer);

        $this->delete(route('events.destroy', $event));

        Notification::assertSentTo($proposer, EventCancelledNotification::class);
        Notification::assertNotSentTo($organizer, EventCancelledNotification::class);
    }

    public function test_event_cancel_notifies_waitlist_on_scheduled_activity(): void
    {
        Notification::fake();

        $organizer = User::factory()->create();
        $host = User::factory()->create();
        $waitlistUser = User::factory()->create();

        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'requires_approval' => true,
            'max_participants' => 1,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);

        ActivityWaitlistEntry::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $waitlistUser->id,
            'position' => 1,
        ]);

        $this->actingAs($organizer);
        Livewire::test(ShowEvent::class, ['event' => $event])
            ->call('cancelEvent');

        Notification::assertSentTo($waitlistUser, EventCancelledNotification::class);
    }
}
